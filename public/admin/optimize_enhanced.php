<?php
session_start();

// Prevent any output before JSON
ob_start();

// Check authorization
if (!isset($_SESSION['admin_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please log in']);
    exit();
}

// Set JSON header
header('Content-Type: application/json');

// Suppress any warnings/notices that might break JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

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

        // If no target vehicles specified, calculate optimal number
        if ($this->target_vehicles === null) {
            // Start with minimum vehicles
            $this->target_vehicles = $this->calculateMinimumVehicles();

            // Run initial optimization
            $result = $this->runOptimizationAlgorithm();

            // Check if any driver with passengers has overhead > 20 minutes
            $max_overhead = 0;
            if (isset($result['routes'])) {
                foreach ($result['routes'] as $route) {
                    // Only check overhead for drivers with passengers
                    if (isset($route['has_passengers']) && $route['has_passengers'] && isset($route['overhead_time'])) {
                        $overhead = intval($route['overhead_time']);
                        if ($overhead > $max_overhead) {
                            $max_overhead = $overhead;
                        }
                    }
                }
            }

            // If overhead is too high, increase vehicles and re-optimize
            while ($max_overhead > 20 && $this->target_vehicles < count($this->drivers)) {
                $this->target_vehicles++;
                $result = $this->runOptimizationAlgorithm();

                // Recalculate max overhead
                $max_overhead = 0;
                if (isset($result['routes'])) {
                    foreach ($result['routes'] as $route) {
                        if (isset($route['overhead_time'])) {
                            $overhead = intval($route['overhead_time']);
                            if ($overhead > $max_overhead) {
                                $max_overhead = $overhead;
                            }
                        }
                    }
                }
            }

            // Add note about overhead optimization
            if (isset($result['success']) && $result['success']) {
                $result['overhead_optimized'] = true;
                $result['max_overhead'] = $max_overhead;
            }
        } else {
            // Run with specified target vehicles
            $result = $this->runOptimizationAlgorithm();
        }

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

        // If no drivers available, return 0
        if (empty($this->drivers)) {
            return 0;
        }

        // Calculate basic minimum based on capacity
        $min_vehicles = 0;
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

        // Consider overhead constraints - use more vehicles if needed
        // Rule of thumb: each vehicle handles about 3-4 participants to keep overhead low
        $overhead_based_minimum = max(1, ceil($total_participants / 3.5));

        // Return the larger of the two minimums to prioritize lower overhead
        return min(max($min_vehicles, $overhead_based_minimum), count($this->drivers));
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

        // Handle any unassigned participants
        $assignments = $this->ensureAllParticipantsAssigned($assignments);

        // Verify all participants are assigned exactly once
        $all_assigned = [];
        $unassigned = [];

        // Track all assigned people
        foreach ($assignments as $assignment) {
            // Add driver
            $all_assigned[] = $assignment['driver_id'];

            // Add passengers
            if (isset($assignment['passengers'])) {
                foreach ($assignment['passengers'] as $passenger) {
                    $all_assigned[] = $passenger['id'];
                }
            }
        }

        // Check for unassigned participants
        foreach ($this->participants as $participant) {
            if (!in_array($participant['id'], $all_assigned)) {
                $unassigned[] = $participant;
            }
        }

        // Check for duplicate assignments
        $assignment_counts = array_count_values($all_assigned);
        $duplicates = array_filter($assignment_counts, function($count) {
            return $count > 1;
        });

        // If there are unassigned people or duplicates, return error
        if (count($unassigned) > 0 || count($duplicates) > 0) {
            $error_msg = '';

            if (count($duplicates) > 0) {
                $error_msg .= 'Some participants assigned multiple times. ';
            }

            if (count($unassigned) > 0) {
                $error_msg .= count($unassigned) . ' participants not assigned. ';
                // Try to provide reason
                if ($actual_vehicles < $num_clusters) {
                    $error_msg .= 'Not enough drivers available. ';
                }
            }

            return [
                'success' => false,
                'message' => $error_msg,
                'unassigned' => $unassigned,
                'duplicates' => $duplicates,
                'routes' => $assignments
            ];
        }

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
            'assignments' => $assignments,
            'assigned_count' => count($all_assigned),
            'unique_assigned' => count(array_unique($all_assigned))
        ];
    }

    private function performEnhancedClustering($num_clusters) {
        // Use geographic clustering to minimize travel overhead
        $clusters = [];

        // Initialize clusters
        for ($i = 0; $i < $num_clusters; $i++) {
            $clusters[$i] = [];
        }

        // Sort participants by location (simple geographic grouping)
        $participants_with_coords = [];
        $participants_without_coords = [];

        foreach ($this->participants as $participant) {
            if (!empty($participant['lat']) && !empty($participant['lng'])) {
                $participants_with_coords[] = $participant;
            } else {
                $participants_without_coords[] = $participant;
            }
        }

        // Sort by latitude then longitude for geographic grouping
        usort($participants_with_coords, function($a, $b) {
            // Create geographic zones
            $lat_diff = $a['lat'] - $b['lat'];
            if (abs($lat_diff) > 0.01) {
                return $lat_diff > 0 ? 1 : -1;
            }
            return $a['lng'] > $b['lng'] ? 1 : -1;
        });

        // Distribute participants evenly across clusters, keeping nearby people together
        $all_participants = array_merge($participants_with_coords, $participants_without_coords);

        // Calculate ideal cluster size
        $ideal_size = ceil(count($all_participants) / $num_clusters);

        $current_cluster = 0;
        foreach ($all_participants as $participant) {
            // Move to next cluster if current is at ideal size
            if (count($clusters[$current_cluster]) >= $ideal_size && $current_cluster < $num_clusters - 1) {
                $current_cluster++;
            }
            $clusters[$current_cluster][] = $participant;
        }

        // Balance clusters if last one is too small
        if ($num_clusters > 1 && count($clusters[$num_clusters - 1]) < 2) {
            // Redistribute last cluster's participants
            $last_cluster = array_pop($clusters);
            foreach ($last_cluster as $participant) {
                // Add to smallest cluster
                $min_idx = 0;
                $min_size = count($clusters[0]);
                for ($i = 1; $i < count($clusters); $i++) {
                    if (count($clusters[$i]) < $min_size) {
                        $min_idx = $i;
                        $min_size = count($clusters[$i]);
                    }
                }
                $clusters[$min_idx][] = $participant;
            }
        }

        return $clusters;
    }

    private function assignDriversWithOverhead($clusters) {
        $assignments = [];
        $assigned_participants = []; // Track ALL assigned participants (drivers AND passengers)

        // Sort drivers by capacity (largest first)
        usort($this->drivers, function($a, $b) {
            return $b['vehicle_capacity'] - $a['vehicle_capacity'];
        });

        // FIRST PASS: Select best driver for each cluster
        $cluster_drivers = [];
        $used_driver_ids = [];

        foreach ($clusters as $cluster_index => $cluster) {
            // Find best driver for this cluster
            $best_driver = null;
            $best_score = PHP_FLOAT_MAX;

            foreach ($this->drivers as $driver) {
                if (in_array($driver['id'], $used_driver_ids)) {
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
                $cluster_drivers[$cluster_index] = $best_driver;
                $used_driver_ids[] = $best_driver['id'];
                $assigned_participants[] = $best_driver['id']; // Mark driver as assigned
            }
        }

        // SECOND PASS: Assign passengers and create routes
        foreach ($clusters as $cluster_index => $cluster) {
            if (!isset($cluster_drivers[$cluster_index])) {
                continue; // No driver available for this cluster
            }

            $best_driver = $cluster_drivers[$cluster_index];

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
                // Skip if this participant is already assigned (as driver or passenger)
                if (in_array($participant['id'], $assigned_participants)) {
                    continue;
                }

                // Skip if vehicle is full
                if (count($passengers) >= $best_driver['vehicle_capacity']) {
                    break;
                }

                // Add as passenger
                $passengers[] = [
                    'id' => $participant['id'],
                    'name' => $participant['name'],
                    'address' => $participant['address'],
                    'lat' => $participant['lat'],
                    'lng' => $participant['lng']
                ];
                $assigned_participants[] = $participant['id']; // Track as assigned

                if ($participant['lat'] && $participant['lng']) {
                    $coordinates[] = [(float)$participant['lat'], (float)$participant['lng']];
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

            // Calculate departure time
            $departure_time = $this->calculateDepartureTime($cumulative_time);

            // Check if driver has passengers
            if (count($passengers) > 0) {
                // Calculate overhead (extra time due to carpooling)
                $overhead_time = $cumulative_time - $direct_time;
                $overhead_percentage = $direct_time > 0 ? round(($overhead_time / $direct_time) * 100) : 0;

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
                        'coordinates' => $coordinates,
                        'has_passengers' => true
                    ];
                } else {
                    // Driver with no passengers - drives directly
                    $assignments[] = [
                        'driver_id' => $best_driver['id'],
                        'driver_name' => $best_driver['name'],
                        'vehicle' => ($best_driver['vehicle_make'] ?? 'Vehicle') . ' ' .
                                    ($best_driver['vehicle_model'] ?? ''),
                        'capacity' => $best_driver['vehicle_capacity'],
                        'passengers' => [],
                        'total_distance' => round($direct_distance, 2),
                        'departure_time' => date('g:i A', strtotime($departure_time)),
                        'estimated_travel_time' => round($direct_time) . ' minutes',
                        'direct_distance' => round($direct_distance, 2),
                        'direct_time' => round($direct_time) . ' minutes',
                        'overhead_time' => '0 minutes',
                        'overhead_percentage' => '0%',
                        'coordinates' => $coordinates,
                        'has_passengers' => false,
                        'direct_to_destination' => true
                    ];
                }

            // Mark driver as assigned
            $update_query = "UPDATE users SET is_assigned_driver = TRUE WHERE id = :driver_id";
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->bindParam(':driver_id', $best_driver['id']);
            $update_stmt->execute();
        }

        return $assignments;
    }

    private function ensureAllParticipantsAssigned($assignments) {
        // Track who's been assigned
        $assigned_ids = [];

        foreach ($assignments as $assignment) {
            // Add driver
            $assigned_ids[] = $assignment['driver_id'];

            // Add passengers
            if (isset($assignment['passengers'])) {
                foreach ($assignment['passengers'] as $passenger) {
                    $assigned_ids[] = $passenger['id'];
                }
            }
        }

        // Find unassigned participants
        $unassigned = [];
        foreach ($this->participants as $participant) {
            if (!in_array($participant['id'], $assigned_ids)) {
                $unassigned[] = $participant;
            }
        }

        // If there are unassigned participants, try to fit them into existing routes
        if (count($unassigned) > 0) {
            foreach ($unassigned as $participant) {
                $assigned = false;

                // Try to add to an existing route with capacity
                for ($i = 0; $i < count($assignments); $i++) {
                    $current_passengers = isset($assignments[$i]['passengers']) ? count($assignments[$i]['passengers']) : 0;
                    $capacity = $assignments[$i]['capacity'];

                    if ($current_passengers < $capacity) {
                        // Add this participant as a passenger
                        if (!isset($assignments[$i]['passengers'])) {
                            $assignments[$i]['passengers'] = [];
                        }

                        $assignments[$i]['passengers'][] = [
                            'id' => $participant['id'],
                            'name' => $participant['name'],
                            'address' => $participant['address'],
                            'lat' => $participant['lat'],
                            'lng' => $participant['lng']
                        ];

                        $assigned = true;
                        break;
                    }
                }

                // If still not assigned and participant can drive, have them drive solo
                if (!$assigned && $participant['willing_to_drive'] && $participant['vehicle_capacity'] > 0) {
                    // Create solo driver assignment
                    $assignments[] = [
                        'driver_id' => $participant['id'],
                        'driver_name' => $participant['name'],
                        'vehicle' => ($participant['vehicle_make'] ?? 'Vehicle') . ' ' .
                                   ($participant['vehicle_model'] ?? ''),
                        'capacity' => $participant['vehicle_capacity'],
                        'passengers' => [],
                        'total_distance' => 0,
                        'departure_time' => $this->calculateDepartureTime(30),
                        'estimated_travel_time' => '30 minutes',
                        'direct_distance' => 0,
                        'direct_time' => '30 minutes',
                        'overhead_time' => '0 minutes',
                        'overhead_percentage' => '0%',
                        'coordinates' => [],
                        'has_passengers' => false,
                        'direct_to_destination' => true
                    ];
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

// Handle request
try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    $event_id = $data['event_id'] ?? 1;
    $target_vehicles = isset($data['target_vehicles']) ? (int)$data['target_vehicles'] : null;
    $max_drive_time = isset($data['max_drive_time']) ? (int)$data['max_drive_time'] : 50;

    $optimizer = new EnhancedCarpoolOptimizer($db, $event_id, $target_vehicles, $max_drive_time);
    $result = $optimizer->optimize();

    // Clean any buffered output
    ob_clean();

    // Output result
    echo json_encode($result);

} catch (Exception $e) {
    // Clean any buffered output
    ob_clean();

    // Return error as JSON
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?>