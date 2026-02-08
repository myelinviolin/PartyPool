<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

include_once '../config/database.php';

class CarpoolOptimizer {
    private $db;
    private $event_id;
    private $event;
    private $participants;
    private $drivers;
    private $assignments;

    public function __construct($db, $event_id) {
        $this->db = $db;
        $this->event_id = $event_id;
        $this->assignments = [];
    }

    public function optimize() {
        // Load event data
        $this->loadEventData();

        // Load participants
        $this->loadParticipants();

        // Run optimization algorithm
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

    private function runOptimizationAlgorithm() {
        // If no drivers willing, return error
        if (empty($this->drivers)) {
            return [
                'success' => false,
                'message' => 'No drivers available for optimization'
            ];
        }

        // Clear previous assignments
        $clear_query = "UPDATE carpool_assignments SET is_active = FALSE
                       WHERE event_id = :event_id";
        $clear_stmt = $this->db->prepare($clear_query);
        $clear_stmt->bindParam(':event_id', $this->event_id);
        $clear_stmt->execute();

        // Reset all users' assigned driver status
        $reset_query = "UPDATE users SET is_assigned_driver = FALSE WHERE event_id = :event_id";
        $reset_stmt = $this->db->prepare($reset_query);
        $reset_stmt->bindParam(':event_id', $this->event_id);
        $reset_stmt->execute();

        // K-means clustering based on geographic location
        $clusters = $this->performClustering();

        // Assign drivers to clusters
        $assignments = $this->assignDriversToClusters($clusters);

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
            'routes' => $assignments,
            'assignments' => $assignments
        ];
    }

    private function performClustering() {
        // Group participants by geographic proximity using simple grid-based clustering
        $clusters = [];

        // Calculate center point (event location)
        $center_lat = (float)$this->event['event_lat'];
        $center_lng = (float)$this->event['event_lng'];

        // Create grid cells (approximately 5 mile squares)
        $grid_size = 0.07; // Roughly 5 miles at this latitude

        $participant_grid = [];

        foreach ($this->participants as $participant) {
            if (!$participant['lat'] || !$participant['lng']) {
                // Participants without location go to a default cluster
                $participant_grid['no_location'][] = $participant;
                continue;
            }

            $lat = (float)$participant['lat'];
            $lng = (float)$participant['lng'];

            // Calculate grid cell
            $grid_x = floor(($lat - $center_lat) / $grid_size);
            $grid_y = floor(($lng - $center_lng) / $grid_size);
            $grid_key = $grid_x . '_' . $grid_y;

            $participant_grid[$grid_key][] = $participant;
        }

        // Merge small clusters with nearby larger ones
        $final_clusters = [];
        $min_cluster_size = 3;

        foreach ($participant_grid as $key => $cluster) {
            if (count($cluster) >= $min_cluster_size || $key === 'no_location') {
                $final_clusters[] = $cluster;
            } else {
                // Merge with nearest cluster
                if (!empty($final_clusters)) {
                    $final_clusters[count($final_clusters) - 1] = array_merge(
                        $final_clusters[count($final_clusters) - 1],
                        $cluster
                    );
                } else {
                    $final_clusters[] = $cluster;
                }
            }
        }

        return $final_clusters;
    }

    private function assignDriversToClusters($clusters) {
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

                // Calculate score based on:
                // 1. Distance from driver to cluster center
                // 2. Capacity match
                $cluster_center = $this->calculateClusterCenter($cluster);
                $distance = $this->calculateDistance(
                    $driver['lat'] ?? $this->event['event_lat'],
                    $driver['lng'] ?? $this->event['event_lng'],
                    $cluster_center['lat'],
                    $cluster_center['lng']
                );

                $capacity_diff = abs($driver['vehicle_capacity'] - count($cluster));
                $score = $distance + ($capacity_diff * 2); // Weight capacity matching

                if ($score < $best_score && $driver['vehicle_capacity'] >= count($cluster) - 1) {
                    $best_driver = $driver;
                    $best_score = $score;
                }
            }

            if ($best_driver) {
                $used_drivers[] = $best_driver['id'];

                // Create assignment
                $passengers = [];
                $route_distance = 0;
                $coordinates = [];

                // Add driver's starting location
                if ($best_driver['lat'] && $best_driver['lng']) {
                    $coordinates[] = [(float)$best_driver['lat'], (float)$best_driver['lng']];
                }

                foreach ($cluster as $participant) {
                    if ($participant['id'] === $best_driver['id']) {
                        continue; // Skip the driver themselves
                    }

                    if (count($passengers) >= $best_driver['vehicle_capacity']) {
                        break; // Vehicle full
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

                // Calculate total route distance and pickup times
                $pickup_times = [];
                $cumulative_time = 0;

                for ($i = 0; $i < count($coordinates) - 1; $i++) {
                    $segment_distance = $this->calculateDistance(
                        $coordinates[$i][0],
                        $coordinates[$i][1],
                        $coordinates[$i + 1][0],
                        $coordinates[$i + 1][1]
                    );
                    $route_distance += $segment_distance;

                    // Calculate travel time for this segment
                    $segment_time = $this->calculateTravelTime($segment_distance);
                    $cumulative_time += $segment_time;

                    // Store pickup time for passengers (not for the last coordinate which is the event)
                    if ($i < count($passengers)) {
                        $pickup_times[$i] = $cumulative_time;
                        // Add 3 minutes for pickup/loading time at each stop
                        $cumulative_time += 3;
                    }
                }

                // Calculate driver departure time
                $departure_time = $this->calculateDepartureTime($cumulative_time);

                // Add pickup times to passengers
                for ($i = 0; $i < count($passengers); $i++) {
                    if (isset($pickup_times[$i])) {
                        $pickup_timestamp = strtotime($departure_time) + ($pickup_times[$i] * 60);
                        $passengers[$i]['pickup_time'] = date('g:i A', $pickup_timestamp);
                    }
                }

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
                    'coordinates' => $coordinates
                ];

                // Mark driver as assigned
                $update_query = "UPDATE users SET is_assigned_driver = TRUE WHERE id = :driver_id";
                $update_stmt = $this->db->prepare($update_query);
                $update_stmt->bindParam(':driver_id', $best_driver['id']);
                $update_stmt->execute();
            }
        }

        // Handle any unassigned participants
        foreach ($this->participants as $participant) {
            if (!in_array($participant['id'], $assigned_passengers) &&
                !in_array($participant['id'], $used_drivers)) {

                // Find vehicle with available space
                foreach ($assignments as &$assignment) {
                    if (count($assignment['passengers']) < $assignment['capacity']) {
                        $assignment['passengers'][] = [
                            'id' => $participant['id'],
                            'name' => $participant['name'],
                            'address' => $participant['address'],
                            'lat' => $participant['lat'],
                            'lng' => $participant['lng']
                        ];
                        $assigned_passengers[] = $participant['id'];
                        break;
                    }
                }
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
        // Haversine formula for distance between two points
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
        // Assume average speed of 30 mph in city driving with stops
        // Add 5 minutes buffer for each stop
        $avg_speed = 30; // mph
        $travel_minutes = ($distance_miles / $avg_speed) * 60;
        return $travel_minutes;
    }

    private function calculateDepartureTime($total_route_time_minutes) {
        // Get event time
        $event_datetime = $this->event['event_date'] . ' ' . $this->event['event_time'];
        $event_timestamp = strtotime($event_datetime);

        // Add 10 minutes buffer to arrive early
        $buffer_minutes = 10;
        $total_time_needed = $total_route_time_minutes + $buffer_minutes;

        // Calculate departure time
        $departure_timestamp = $event_timestamp - ($total_time_needed * 60);

        return date('Y-m-d H:i:s', $departure_timestamp);
    }

    private function saveAssignments($result) {
        if (!$result['success']) {
            return;
        }

        foreach ($result['routes'] as $route) {
            // Save assignment
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
                $distance = 0; // Could calculate individual distances here
                $passenger_stmt->bindParam(':distance', $distance);
                $pickup_time = isset($passenger['pickup_time']) ? $passenger['pickup_time'] : null;
                $passenger_stmt->bindParam(':pickup_time', $pickup_time);
                $passenger_stmt->execute();

                $order++;
            }
        }

        // Save to optimization_results table for persistence
        // First check if a result already exists for this event
        $check_query = "SELECT id FROM optimization_results WHERE event_id = :event_id";
        $check_stmt = $this->db->prepare($check_query);
        $check_stmt->bindParam(':event_id', $this->event_id);
        $check_stmt->execute();

        $routes_json = json_encode($result['routes']);
        $vehicles_used = $result['vehicles_needed'];

        if ($check_stmt->rowCount() > 0) {
            // Update existing record
            $opt_query = "UPDATE optimization_results
                         SET routes = :routes,
                             vehicles_used = :vehicles_used,
                             created_at = NOW()
                         WHERE event_id = :event_id";
        } else {
            // Insert new record
            $opt_query = "INSERT INTO optimization_results
                         (event_id, routes, vehicles_used, created_at)
                         VALUES (:event_id, :routes, :vehicles_used, NOW())";
        }

        $opt_stmt = $this->db->prepare($opt_query);
        $opt_stmt->bindParam(':event_id', $this->event_id);
        $opt_stmt->bindParam(':routes', $routes_json);
        $opt_stmt->bindParam(':vehicles_used', $vehicles_used);
        $opt_stmt->execute();

        // Update event optimization status
        $update_query = "UPDATE events
                        SET optimization_status = 'completed',
                            optimization_run_at = NOW()
                        WHERE id = :event_id";
        $update_stmt = $this->db->prepare($update_query);
        $update_stmt->bindParam(':event_id', $this->event_id);
        $update_stmt->execute();
    }
}

// Main execution
$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$event_id = $data['event_id'] ?? 1;

$optimizer = new CarpoolOptimizer($db, $event_id);
$result = $optimizer->optimize();

echo json_encode($result);