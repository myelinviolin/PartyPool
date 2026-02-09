<?php
// Test to verify 3-minute pickup time calculation
echo "=== TESTING 3-MINUTE PICKUP TIME CALCULATION ===" . PHP_EOL;
echo date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

session_start();
$_SESSION['admin_id'] = 1;

// Change to admin directory
chdir('/var/www/partycarpool.clodhost.com/public/admin');

// Include necessary files
include_once '../config/database.php';
include_once 'optimize_enhanced.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed\n");
}

// Clear existing optimization
$clear_query = "DELETE FROM optimization_results WHERE event_id = 1";
$db->exec($clear_query);
echo "Cleared existing optimization results." . PHP_EOL . PHP_EOL;

// Run optimization with different vehicle targets to test
$targets = [3, 4, 6];

foreach ($targets as $target) {
    echo "Testing with $target vehicles:" . PHP_EOL;
    echo str_repeat("-", 50) . PHP_EOL;

    try {
        $optimizer = new EnhancedCarpoolOptimizer($db, 1, $target);
        $result = $optimizer->optimize();

        if ($result['success']) {
            echo "✅ Optimization successful" . PHP_EOL;
            echo "Routes created: " . count($result['routes']) . PHP_EOL . PHP_EOL;

            // Check each route
            foreach ($result['routes'] as $index => $route) {
                echo "Route " . ($index + 1) . ": " . $route['driver_name'] . PHP_EOL;

                $num_passengers = count($route['passengers']);
                echo "  Passengers: $num_passengers" . PHP_EOL;

                if ($num_passengers > 0) {
                    echo "  Passenger names: ";
                    foreach ($route['passengers'] as $p) {
                        echo $p['name'] . ", ";
                    }
                    echo PHP_EOL;
                }

                // Extract numeric values from time strings
                $direct_time = intval($route['direct_time']);
                $overhead_time = intval($route['overhead_time']);
                $estimated_time = intval($route['estimated_travel_time']);

                echo "  Direct time: $direct_time minutes" . PHP_EOL;
                echo "  Overhead time: $overhead_time minutes" . PHP_EOL;
                echo "  Total travel time: $estimated_time minutes" . PHP_EOL;

                // Calculate expected overhead based on passenger count
                if ($num_passengers > 0) {
                    $expected_pickup_time = $num_passengers * 3;
                    echo "  Expected pickup time: $expected_pickup_time minutes (3 min × $num_passengers passengers)" . PHP_EOL;

                    // The overhead should include the pickup time plus any extra distance
                    $distance_overhead = $overhead_time - $expected_pickup_time;
                    echo "  Distance overhead: $distance_overhead minutes" . PHP_EOL;

                    if ($overhead_time < $expected_pickup_time) {
                        echo "  ⚠️ WARNING: Overhead time ($overhead_time min) is less than expected pickup time ($expected_pickup_time min)!" . PHP_EOL;
                    } else {
                        echo "  ✅ Pickup time properly included in overhead" . PHP_EOL;
                    }
                }

                echo PHP_EOL;
            }
        } else {
            echo "❌ Optimization failed: " . $result['message'] . PHP_EOL;
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . PHP_EOL;
    }

    echo PHP_EOL;
}

// Restore directory
chdir('/var/www/partycarpool.clodhost.com/public');

echo "=== TEST COMPLETE ===" . PHP_EOL;
?>