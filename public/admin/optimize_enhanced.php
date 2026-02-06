<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

include_once '../config/database.php';

class EnhancedCarpoolOptimizer {
    private $db;
    private $event_id;
    private $event;
    private $participants;
    private $drivers;
    private $assignments;
    private $target_vehicles; // NEW: Allow specifying target number of vehicles
    private $max_drive_time; // NEW: Maximum drive time per driver in minutes

    public function __construct($db, $event_id, $target_vehicles = null, $max_drive_time = 50) {
        $this->db = $db;
        $this->event_id = $event_id;
        $this->assignments = [];
        $this->target_vehicles = $target_vehicles;
        $this->max_drive_time = $max_drive_time;
    }

    public function optimize() {
        // Load event data
        $this->loadEventData();

        // Load participants
        $this->loadParticipants();

        // If no target vehicles specified, minimize vehicles used
        if ($this->target_vehicles === null) {
            $this->target_vehicles = $this->calculateMinimumVehicles();
        }

        // Run optimization algorithm with target vehicles
        $result = $this->runOptimizationAlgorithm();

        // Save to database
        $this->saveAssignments($result);

        return $result;
    }

    private function loadEventData() {
        $query = "SELECT * FROM events WHERE id = :event_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':event_id', $this->event_id);
        $stmt->execute();
        $this->event = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function loadParticipants() {
        $query = "SELECT * FROM users WHERE event_id = :event_id ORDER BY willing_to_drive DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':event_id', $this->event_id);
        $stmt->execute();
        $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->participants = [];
        $this->drivers = [];

        foreach ($all_users as $user) {
            if ($user['willing_to_drive'] && $user['vehicle_capacity'] > 0) {
                $this->drivers[] = $user;
            }
            $this->participants[] = $user;
        }
    }

    private function calculateMinimumVehicles() {
        // Calculate theoretical minimum vehicles needed
        $total_participants = count($this->participants);
        $total_capacity = 0;

        foreach ($this->drivers as $driver) {
            $total_capacity += $driver['vehicle_capacity'];
        }

        // Minimum is either all drivers or enough to carry everyone
        $min_vehicles = 1;
        $cumulative_capacity = 0;

        // Sort drivers by capacity (largest first)
        $sorted_drivers = $this->drivers;
        usort($sorted_drivers, function($a, $b) {
            return $b['vehicle_capacity'] - $a['vehicle_capacity'];
        });

        foreach ($sorted_drivers as $driver) {
            $cumulative_capacity += $driver['vehicle_capacity'];
            $min_vehicles++;
            if ($cumulative_capacity >= $total_participants) {
                break;
            }
        }

        return min($min_vehicles, count($this->drivers));
    }

    private function runOptimizationAlgorithm() {
        // If no drivers willing, return error
        if (empty($this->drivers)) {
            return [
                'success' => false,
                'message' => 'No drivers available for optimization'
            ];
        }

        // Clear previous assignments
        $clear_query = "UPDATE carpool_assignments SET is_active = FALSE WHERE event_id = :event_id";
        $clear_stmt = $this->db->prepare($clear_query);
        $clear_stmt->bindParam(':event_id', $this->event_id);
        $clear_stmt->execute();

        // Reset all users' assigned driver status
        $reset_query = "UPDATE users SET is_assigned_driver = FALSE WHERE event_id = :event_id";
        $reset_stmt = $this->db->prepare($reset_query);
        $reset_stmt->bindParam(':event_id', $this->event_id);
        $reset_stmt->execute();

        // Ensure we don't try to use more vehicles than available drivers
        $actual_vehicles = min($this->target_vehicles, count($this->drivers));

        // Perform clustering with target number of vehicles
        $clusters = $this->performEnhancedClustering($actual_vehicles);

        // Assign drivers to clusters
        $assignments = $this->assignDriversWithOverhead($clusters);

        // Calculate statistics
        $total_participants = count($this->participants);
        $vehicles_needed = count($assignments);
        $max_possible_drivers = count($this->drivers);
        $vehicles_saved = $max_possible_drivers - $vehicles_needed;

        return [
            'success' => true,
            'total_participants' => $total_participants,
            'vehicles_needed' => $vehicles_needed,
            'vehicles_saved' => $vehicles_saved,
            'target_vehicles' => $this->target_vehicles,
            'actual_vehicles' => $actual_vehicles,
            'max_drive_time' => $this->max_drive_time,
            'routes' => $assignments,
            'assignments' => $assignments
        ];
    }

    private function performEnhancedClustering($num_clusters) {
        // Use K-means-like clustering to create exactly num_clusters groups
        $clusters = [];

        // Initialize clusters with random participants
        for ($i = 0; $i < $num_clusters; $i++) {
            $clusters[$i] = [];
        }

        // Assign each participant to nearest cluster
        foreach ($this->participants as $participant) {
            // Simple round-robin for now, but could use geographic clustering
            $cluster_index = count($clusters[0]);
            foreach ($clusters as $idx => $cluster) {
                if (count($cluster) < count($clusters[$cluster_index])) {
                    $cluster_index = $idx;
                }
            }
            $clusters[$cluster_index][] = $participant;
        }

        return $clusters;
    }

    private function assignDriversWithOverhead($clusters) {
        $assignments = [];
        $used_drivers = [];
        $assigned_passengers = [];

        // Sort drivers by capacity (largest first)
        usort($this->drivers, function($a, $b) {
            return $b['vehicle_capacity'] - $a['vehicle_capacity'];
        });

        foreach ($clusters as $cluster) {
            // Find best driver for this cluster
            $best_driver = null;
            $best_score = PHP_FLOAT_MAX;

            foreach ($this->drivers as $driver) {
                if (in_array($driver['id'], $used_drivers)) {
                    continue;
                }

                // Calculate score based on distance and capacity
                $cluster_center = $this->calculateClusterCenter($cluster);
                $distance = $this->calculateDistance(
                    $driver['lat'] ?? $this->event['event_lat'],
                    $driver['lng'] ?? $this->event['event_lng'],
                    $cluster_center['lat'],
                    $cluster_center['lng']
                );

                $capacity_diff = abs($driver['vehicle_capacity'] - count($cluster));
                $score = $distance + ($capacity_diff * 2);

                if ($score < $best_score && $driver['vehicle_capacity'] >= count($cluster) - 1) {
                    $best_driver = $driver;
                    $best_score = $score;
                }
            }

            if ($best_driver) {
                $used_drivers[] = $best_driver['id'];

                // Calculate direct drive time for driver (home to event)
                $direct_distance = 0;
                $direct_time = 0;
                if ($best_driver['lat'] && $best_driver['lng']) {
                    $direct_distance = $this->calculateDistance(
                        $best_driver['lat'],
                        $best_driver['lng'],
                        $this->event['event_lat'],
                        $this->event['event_lng']
                    );
                    $direct_time = $this->calculateTravelTime($direct_distance);
                }

                // Create assignment with route
                $passengers = [];
                $route_distance = 0;
                $coordinates = [];

                // Add driver's starting location
                if ($best_driver['lat'] && $best_driver['lng']) {
                    $coordinates[] = [(float)$best_driver['lat'], (float)$best_driver['lng']];
                }

                // Add passengers
                foreach ($cluster as $participant) {
                    if ($participant['id'] === $best_driver['id']) {
                        continue;
                    }

                    if (count($passengers) >= $best_driver['vehicle_capacity']) {
                        break;
                    }

                    if (!in_array($participant['id'], $assigned_passengers)) {
                        $passengers[] = [
                            'id' => $participant['id'],
                            'name' => $participant['name'],
                            'address' => $participant['address'],
                            'lat' => $participant['lat'],
                            'lng' => $participant['lng']
                        ];
                        $assigned_passengers[] = $participant['id'];

                        if ($participant['lat'] && $participant['lng']) {
                            $coordinates[] = [(float)$participant['lat'], (float)$participant['lng']];
                        }
                    }
                }

                // Add event location as final destination
                if ($this->event['event_lat'] && $this->event['event_lng']) {
                    $coordinates[] = [(float)$this->event['event_lat'], (float)$this->event['event_lng']];
                }

                // Calculate total route distance and time
                $cumulative_time = 0;
                for ($i = 0; $i < count($coordinates) - 1; $i++) {
                    $segment_distance = $this->calculateDistance(
                        $coordinates[$i][0],
                        $coordinates[$i][1],
                        $coordinates[$i + 1][0],
                        $coordinates[$i + 1][1]
                    );
                    $route_distance += $segment_distance;

                    $segment_time = $this->calculateTravelTime($segment_distance);
                    $cumulative_time += $segment_time;

                    // Add 3 minutes for pickup time at each stop (except last which is event)
                    if ($i < count($passengers)) {
                        $cumulative_time += 3;
                    }
                }

                // Calculate overhead (extra time due to carpooling)
                $overhead_time = $cumulative_time - $direct_time;
                $overhead_percentage = $direct_time > 0 ? round(($overhead_time / $direct_time) * 100) : 0;

                // Calculate departure time
                $departure_time = $this->calculateDepartureTime($cumulative_time);

                $assignments[] = [
                    'driver_id' => $best_driver['id'],
                    'driver_name' => $best_driver['name'],
                    'vehicle' => ($best_driver['vehicle_make'] ?? 'Vehicle') . ' ' .
                                ($best_driver['vehicle_model'] ?? ''),
                    'capacity' => $best_driver['vehicle_capacity'],
                    'passengers' => $passengers,
                    'total_distance' => round($route_distance, 2),
                    'departure_time' => date('g:i A', strtotime($departure_time)),
                    'estimated_travel_time' => round($cumulative_time) . ' minutes',
                    'direct_distance' => round($direct_distance, 2),
                    'direct_time' => round($direct_time) . ' minutes',
                    'overhead_time' => round($overhead_time) . ' minutes',
                    'overhead_percentage' => $overhead_percentage . '%',
                    'coordinates' => $coordinates
                ];

                // Mark driver as assigned
                $update_query = "UPDATE users SET is_assigned_driver = TRUE WHERE id = :driver_id";
                $update_stmt = $this->db->prepare($update_query);
                $update_stmt->bindParam(':driver_id', $best_driver['id']);
                $update_stmt->execute();
            }
        }

        return $assignments;
    }

    private function calculateClusterCenter($cluster) {
        $total_lat = 0;
        $total_lng = 0;
        $count = 0;

        foreach ($cluster as $participant) {
            if ($participant['lat'] && $participant['lng']) {
                $total_lat += (float)$participant['lat'];
                $total_lng += (float)$participant['lng'];
                $count++;
            }
        }

        if ($count === 0) {
            return [
                'lat' => $this->event['event_lat'],
                'lng' => $this->event['event_lng']
            ];
        }

        return [
            'lat' => $total_lat / $count,
            'lng' => $total_lng / $count
        ];
    }

    private function calculateDistance($lat1, $lng1, $lat2, $lng2) {
        // Haversine formula
        $earth_radius = 3959; // miles

        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        $lng1 = deg2rad($lng1);
        $lng2 = deg2rad($lng2);

        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;

        $a = sin($dlat / 2) * sin($dlat / 2) +
             cos($lat1) * cos($lat2) *
             sin($dlng / 2) * sin($dlng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earth_radius * $c;
    }

    private function calculateTravelTime($distance_miles) {
        // Assume average speed of 30 mph in city driving
        $avg_speed = 30;
        $travel_minutes = ($distance_miles / $avg_speed) * 60;
        return $travel_minutes;
    }

    private function calculateDepartureTime($total_route_time_minutes) {
        $event_datetime = $this->event['event_date'] . ' ' . $this->event['event_time'];
        $event_timestamp = strtotime($event_datetime);

        // Add 10 minutes buffer to arrive early
        $buffer_minutes = 10;
        $total_time_needed = $total_route_time_minutes + $buffer_minutes;

        $departure_timestamp = $event_timestamp - ($total_time_needed * 60);

        return date('Y-m-d H:i:s', $departure_timestamp);
    }

    private function saveAssignments($result) {
        if (!$result['success']) {
            return;
        }

        foreach ($result['routes'] as $route) {
            $query = "INSERT INTO carpool_assignments
                     (event_id, driver_user_id, total_distance, total_passengers, route_order, optimization_score, departure_time, is_active)
                     VALUES (:event_id, :driver_id, :distance, :passengers, :route, :score, :departure_time, TRUE)";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':event_id', $this->event_id);
            $stmt->bindParam(':driver_id', $route['driver_id']);
            $stmt->bindParam(':distance', $route['total_distance']);
            $passenger_count = count($route['passengers']);
            $stmt->bindParam(':passengers', $passenger_count);
            $route_json = json_encode(array_column($route['passengers'], 'id'));
            $stmt->bindParam(':route', $route_json);
            $score = $route['total_distance'] / max(1, count($route['passengers']));
            $stmt->bindParam(':score', $score);
            $departure_time = isset($route['departure_time']) ? $route['departure_time'] : null;
            $stmt->bindParam(':departure_time', $departure_time);
            $stmt->execute();

            $assignment_id = $this->db->lastInsertId();

            // Save passengers
            $order = 1;
            foreach ($route['passengers'] as $passenger) {
                $passenger_query = "INSERT INTO carpool_passengers
                                   (assignment_id, passenger_user_id, pickup_order, pickup_distance, pickup_time)
                                   VALUES (:assignment_id, :passenger_id, :order, :distance, :pickup_time)";

                $passenger_stmt = $this->db->prepare($passenger_query);
                $passenger_stmt->bindParam(':assignment_id', $assignment_id);
                $passenger_stmt->bindParam(':passenger_id', $passenger['id']);
                $passenger_stmt->bindParam(':order', $order);
                $distance = 0;
                $passenger_stmt->bindParam(':distance', $distance);
                $pickup_time = isset($passenger['pickup_time']) ? $passenger['pickup_time'] : null;
                $passenger_stmt->bindParam(':pickup_time', $pickup_time);
                $passenger_stmt->execute();

                $order++;
            }
        }
    }
}

// Handle request
$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);

$event_id = $data['event_id'] ?? 1;
$target_vehicles = isset($data['target_vehicles']) ? (int)$data['target_vehicles'] : null;
$max_drive_time = isset($data['max_drive_time']) ? (int)$data['max_drive_time'] : 50;

$optimizer = new EnhancedCarpoolOptimizer($db, $event_id, $target_vehicles, $max_drive_time);
$result = $optimizer->optimize();

echo json_encode($result);
?>