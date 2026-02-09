<?php
// Direct optimization regeneration script for testing
include_once '../config/database.php';

// Include the optimizer class directly
class CarpoolOptimizer {
    private $conn;
    private $table_name = "users";
    private $participants = [];
    private $routes = [];
    private $event = null;
    private $target_vehicles = 8; // Default target

    public function __construct($db) {
        $this->conn = $db;
    }

    public function optimize($event_id, $target_vehicles = null) {
        if ($target_vehicles) {
            $this->target_vehicles = $target_vehicles;
        }

        // Get event details
        $query = "SELECT * FROM events WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$event_id]);
        $this->event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$this->event) {
            return ['success' => false, 'message' => 'Event not found'];
        }

        // Get all participants with current coordinates
        $query = "SELECT * FROM users WHERE event_id = ? ORDER BY id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$event_id]);
        $this->participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Run optimization
        $this->optimizeForExactTarget($this->target_vehicles);

        // Save results
        $this->saveOptimization($event_id);

        return [
            'success' => true,
            'routes' => $this->routes,
            'summary' => $this->getSummary()
        ];
    }

    private function optimizeForExactTarget($target_vehicles) {
        // Reset routes
        $this->routes = [];

        // Separate drivers and riders
        $potential_drivers = [];
        $riders_only = [];

        foreach ($this->participants as $p) {
            if ($p['willing_to_drive'] == 1) {
                $p['distance_to_event'] = $this->calculateDistance(
                    $p['lat'], $p['lng'],
                    $this->event['event_lat'], $this->event['event_lng']
                );
                $potential_drivers[] = $p;
            } else {
                $riders_only[] = $p;
            }
        }

        // Sort drivers by distance to event (farthest first)
        usort($potential_drivers, function($a, $b) {
            return $b['distance_to_event'] <=> $a['distance_to_event'];
        });

        // Select exactly target_vehicles drivers
        $selected_drivers = array_slice($potential_drivers, 0, $target_vehicles);
        $remaining_as_passengers = array_slice($potential_drivers, $target_vehicles);

        // Combine remaining drivers and riders as potential passengers
        $all_passengers = array_merge($remaining_as_passengers, $riders_only);

        // Create routes for each selected driver
        foreach ($selected_drivers as $driver) {
            $route = [
                'driver_id' => $driver['id'],
                'driver_name' => 'Driver ' . $driver['id'] . ' - ' . $driver['name'],
                'passengers' => [],
                'coordinates' => [[$driver['lat'], $driver['lng']]],
                'total_distance' => 0,
                'pickup_order' => []
            ];

            // Calculate capacity
            $capacity = $driver['vehicle_capacity'] - 1;

            if ($capacity > 0 && !empty($all_passengers)) {
                // Find best passengers for this driver
                $best_passengers = $this->findBestPassengers($driver, $all_passengers, $capacity);

                foreach ($best_passengers as $pax) {
                    $route['passengers'][] = [
                        'id' => $pax['id'],
                        'name' => $pax['willing_to_drive'] == 1 ?
                            'Driver ' . $pax['id'] . ' - ' . $pax['name'] :
                            'Rider ' . $pax['id'] . ' - ' . $pax['name']
                    ];
                    $route['coordinates'][] = [$pax['lat'], $pax['lng']];
                    $route['pickup_order'][] = $pax['name'];

                    // Remove from available passengers
                    $all_passengers = array_filter($all_passengers, function($p) use ($pax) {
                        return $p['id'] != $pax['id'];
                    });
                }
            }

            // Add event as final destination
            $route['coordinates'][] = [$this->event['event_lat'], $this->event['event_lng']];
            $this->routes[] = $route;
        }

        // Safety check: Assign any remaining unassigned participants
        if (!empty($all_passengers)) {
            foreach ($all_passengers as $unassigned) {
                // Find driver with capacity
                foreach ($this->routes as &$route) {
                    $current_passengers = count($route['passengers']);
                    $driver_id = $route['driver_id'];

                    // Get driver's capacity
                    $driver = array_filter($this->participants, function($p) use ($driver_id) {
                        return $p['id'] == $driver_id;
                    });
                    $driver = array_values($driver)[0];
                    $capacity = $driver['vehicle_capacity'] - 1;

                    if ($current_passengers < $capacity) {
                        $route['passengers'][] = [
                            'id' => $unassigned['id'],
                            'name' => $unassigned['willing_to_drive'] == 1 ?
                                'Driver ' . $unassigned['id'] . ' - ' . $unassigned['name'] :
                                'Rider ' . $unassigned['id'] . ' - ' . $unassigned['name']
                        ];
                        // Insert before event coordinates
                        array_splice($route['coordinates'], -1, 0, [[$unassigned['lat'], $unassigned['lng']]]);
                        $route['pickup_order'][] = $unassigned['name'];
                        break;
                    }
                }
            }
        }
    }

    private function findBestPassengers($driver, &$passengers, $capacity) {
        $selected = [];
        $driver_lat = $driver['lat'];
        $driver_lng = $driver['lng'];

        // Score passengers by proximity to driver
        $scored_passengers = [];
        foreach ($passengers as $pax) {
            $distance = $this->calculateDistance(
                $driver_lat, $driver_lng,
                $pax['lat'], $pax['lng']
            );
            $scored_passengers[] = [
                'passenger' => $pax,
                'distance' => $distance
            ];
        }

        // Sort by distance (closest first)
        usort($scored_passengers, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        // Select closest passengers up to capacity
        foreach ($scored_passengers as $scored) {
            if (count($selected) >= $capacity) break;
            $selected[] = $scored['passenger'];
        }

        return $selected;
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 3959; // miles
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }

    private function saveOptimization($event_id) {
        $routes_json = json_encode($this->routes);
        $summary = $this->getSummary();

        // Delete old optimization
        $query = "DELETE FROM optimization_results WHERE event_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$event_id]);

        // Save new optimization
        $query = "INSERT INTO optimization_results (event_id, routes, vehicles_used, target_vehicles, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$event_id, $routes_json, $summary['total_vehicles'], $this->target_vehicles]);
    }

    private function getSummary() {
        $total_drivers = count($this->routes);
        $total_passengers = 0;
        $solo_drivers = 0;

        foreach ($this->routes as $route) {
            $passenger_count = count($route['passengers']);
            $total_passengers += $passenger_count;
            if ($passenger_count == 0) {
                $solo_drivers++;
            }
        }

        return [
            'total_vehicles' => $total_drivers,
            'total_passengers' => $total_passengers,
            'solo_drivers' => $solo_drivers,
            'empty_seats' => $this->calculateEmptySeats()
        ];
    }

    private function calculateEmptySeats() {
        $empty = 0;
        foreach ($this->routes as $route) {
            $driver_id = $route['driver_id'];
            $driver = array_filter($this->participants, function($p) use ($driver_id) {
                return $p['id'] == $driver_id;
            });
            $driver = array_values($driver)[0];
            $capacity = $driver['vehicle_capacity'] - 1; // -1 for driver
            $passengers = count($route['passengers']);
            $empty += ($capacity - $passengers);
        }
        return $empty;
    }
}

// Run the optimization
$database = new Database();
$db = $database->getConnection();

$optimizer = new CarpoolOptimizer($db);
echo "Regenerating optimization with corrected coordinates...\n\n";

$result = $optimizer->optimize(1, 8);

if ($result['success']) {
    echo "✅ Optimization regenerated successfully!\n\n";
    echo "Summary:\n";
    echo "- Total vehicles: " . $result['summary']['total_vehicles'] . "\n";
    echo "- Total passengers: " . $result['summary']['total_passengers'] . "\n";
    echo "- Solo drivers: " . $result['summary']['solo_drivers'] . "\n";
    echo "- Empty seats: " . $result['summary']['empty_seats'] . "\n";

    echo "\nRoutes generated:\n";
    foreach ($result['routes'] as $route) {
        echo "- " . $route['driver_name'];
        if (empty($route['passengers'])) {
            echo " (Solo driver)";
        } else {
            echo " with " . count($route['passengers']) . " passenger(s)";
        }
        echo "\n";
    }
} else {
    echo "❌ Error: " . $result['message'] . "\n";
}
?>