<?php
session_start();
require_once 'config/database.php';
require_once 'admin/optimize_enhanced.php';

// Set up session as admin
$_SESSION['is_admin'] = true;
$_SESSION['event_id'] = 1;

$db = getDBConnection();

// Get participant count
$stmt = $db->query('SELECT COUNT(*) as count FROM users WHERE event_id = 1');
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total participants: " . $result['count'] . "\n\n";

// Run optimization with target of 5 vehicles
$optimizer = new CarpoolOptimizer($db, 1);
$result = $optimizer->optimizeForExactTarget(5);

if ($result['success']) {
    echo "Optimization successful!\n";
    echo "Routes created: " . count($result['routes']) . "\n\n";

    // Look for Robert Taylor's route
    foreach ($result['routes'] as $route) {
        if (strpos($route['driver']['name'], 'Robert') !== false) {
            echo "Found Robert Taylor's route:\n";
            echo "  Driver: " . $route['driver']['name'] . "\n";
            echo "  Driver location: (" . $route['driver']['lat'] . ", " . $route['driver']['lng'] . ")\n";
            echo "  Number of passengers: " . count($route['passengers']) . "\n";

            if (count($route['passengers']) > 0) {
                echo "  Pickup order:\n";
                foreach ($route['passengers'] as $index => $passenger) {
                    echo "    " . ($index + 1) . ". " . $passenger['name'];
                    echo " at (" . $passenger['lat'] . ", " . $passenger['lng'] . ")\n";
                }

                // Calculate distances to verify optimization
                echo "\n  Distance verification:\n";
                $locations = [['lat' => $route['driver']['lat'], 'lng' => $route['driver']['lng']]];
                foreach ($route['passengers'] as $p) {
                    $locations[] = ['lat' => $p['lat'], 'lng' => $p['lng']];
                }
                // Add event location
                $stmt = $db->query('SELECT event_lat, event_lng FROM events WHERE event_id = 1');
                $event = $stmt->fetch(PDO::FETCH_ASSOC);
                $locations[] = ['lat' => $event['event_lat'], 'lng' => $event['event_lng']];

                $total_distance = 0;
                for ($i = 0; $i < count($locations) - 1; $i++) {
                    $dist = calculateDistance(
                        $locations[$i]['lat'], $locations[$i]['lng'],
                        $locations[$i+1]['lat'], $locations[$i+1]['lng']
                    );
                    echo "    Leg " . ($i + 1) . ": " . round($dist, 2) . " miles\n";
                    $total_distance += $dist;
                }
                echo "  Total route distance: " . round($total_distance, 2) . " miles\n";
            }
            echo "\n";
            break;
        }
    }

    // Show all routes summary
    echo "All routes summary:\n";
    foreach ($result['routes'] as $index => $route) {
        echo ($index + 1) . ". " . $route['driver']['name'] . " - ";
        echo count($route['passengers']) . " passengers\n";
    }

} else {
    echo "Optimization failed: " . $result['message'] . "\n";
}

function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 3959; // miles
    $lat1Rad = deg2rad($lat1);
    $lat2Rad = deg2rad($lat2);
    $deltaLat = deg2rad($lat2 - $lat1);
    $deltaLng = deg2rad($lng2 - $lng1);

    $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
         cos($lat1Rad) * cos($lat2Rad) *
         sin($deltaLng / 2) * sin($deltaLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}