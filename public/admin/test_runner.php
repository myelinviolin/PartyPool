<?php
session_start();
$_SESSION['admin_id'] = 1;

include_once '../config/database.php';

// Initialize database
$database = new Database();
$db = $database->getConnection();

// Get event info
$event_query = "SELECT * FROM events WHERE id = 1";
$event_stmt = $db->prepare($event_query);
$event_stmt->execute();
$event = $event_stmt->fetch(PDO::FETCH_ASSOC);

// Get all participants
$users_query = "SELECT * FROM users WHERE event_id = 1 ORDER BY willing_to_drive DESC, name";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get drivers only
$drivers = array_filter($users, function($user) {
    return $user['willing_to_drive'] && $user['vehicle_capacity'] > 0;
});

// Get non-drivers
$non_drivers = array_filter($users, function($user) {
    return !$user['willing_to_drive'];
});

// Run optimization using the class directly
require_once 'optimize_enhanced.php';
$optimizer = new EnhancedCarpoolOptimizer($db, 1, null, 50);
$result = $optimizer->optimize();

echo "========== OPTIMIZATION ACCURACY TEST ==========\n\n";

echo "PARTICIPANTS:\n";
echo "  - Total participants: " . count($users) . "\n";
echo "  - Potential drivers: " . count($drivers) . "\n";
echo "  - Non-drivers: " . count($non_drivers) . "\n\n";

echo "OPTIMIZATION RESULTS:\n";
if ($result['success']) {
    echo "  ✓ Optimization successful\n";
    echo "  - Vehicles used: " . $result['vehicles_needed'] . "\n";
    echo "  - Vehicles saved: " . $result['vehicles_saved'] . "\n";
    echo "  - Routes created: " . count($result['routes']) . "\n";

    if ($result['overhead_optimized']) {
        echo "  - Overhead optimized: Yes (max " . $result['max_overhead'] . " min)\n";
    }
    echo "\n";

    // Verify each route
    echo "ROUTE VERIFICATION:\n";
    echo str_repeat("-", 60) . "\n";

    $allDriversValid = true;
    $driverList = [];
    $passengerList = [];

    foreach ($result['routes'] as $index => $route) {
        echo "\nRoute " . ($index + 1) . ":\n";
        echo "  Driver: " . $route['driver_name'] . "\n";

        // Find driver in users list
        $driverFound = false;
        $driverCanDrive = false;

        foreach ($users as $user) {
            if ($user['name'] == $route['driver_name'] ||
                strpos($user['name'], $route['driver_name']) !== false ||
                strpos($route['driver_name'], $user['name']) !== false) {

                $driverFound = true;
                $driverCanDrive = $user['willing_to_drive'];
                $driverList[] = $route['driver_name'];

                if ($driverCanDrive) {
                    echo "    ✓ Driver is eligible (willing_to_drive = 1)\n";
                } else {
                    echo "    ✗ ERROR: Driver is NOT eligible (willing_to_drive = 0)\n";
                    $allDriversValid = false;
                }
                break;
            }
        }

        if (!$driverFound) {
            echo "    ✗ ERROR: Driver not found in database!\n";
            $allDriversValid = false;
        }

        // Check passengers
        if (!empty($route['passengers'])) {
            echo "  Passengers (" . count($route['passengers']) . "):\n";
            foreach ($route['passengers'] as $p) {
                echo "    - " . $p['name'] . "\n";
                $passengerList[] = $p['name'];
            }
        } else {
            echo "  No passengers (driving solo)\n";
        }

        // Show overhead
        if (isset($route['has_passengers'])) {
            if ($route['has_passengers']) {
                echo "  Overhead: " . $route['overhead_time'] . "\n";
            } else {
                echo "  Direct to destination (no overhead)\n";
            }
        }
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "SUMMARY VERIFICATION:\n";
    echo str_repeat("=", 60) . "\n\n";

    // Count unique drivers and passengers
    $uniqueDrivers = count(array_unique($driverList));
    $uniquePassengers = count(array_unique($passengerList));
    $totalAssigned = $uniqueDrivers + $uniquePassengers;

    echo "Assignment Summary:\n";
    echo "  - Unique drivers assigned: $uniqueDrivers\n";
    echo "  - Unique passengers assigned: $uniquePassengers\n";
    echo "  - Total people assigned: $totalAssigned\n";
    echo "  - Total participants: " . count($users) . "\n";

    if ($totalAssigned == count($users)) {
        echo "  ✓ All participants assigned\n";
    } else {
        $unassigned = count($users) - $totalAssigned;
        echo "  ⚠ Warning: $unassigned participants not assigned\n";
    }

    echo "\nCRITICAL TEST: All drivers eligible?\n";
    if ($allDriversValid) {
        echo "  ✅ PASS: All drivers in optimization are eligible to drive\n";
    } else {
        echo "  ❌ FAIL: Some drivers are NOT eligible to drive!\n";
    }

    // Additional verification
    echo "\nADDITIONAL CHECKS:\n";

    // Check no duplicate assignments
    $allAssigned = array_merge($driverList, $passengerList);
    if (count($allAssigned) == count(array_unique($allAssigned))) {
        echo "  ✓ No duplicate assignments\n";
    } else {
        echo "  ✗ ERROR: Some people assigned multiple times\n";
    }

    // Check vehicle capacity
    $capacityOk = true;
    foreach ($result['routes'] as $route) {
        if (isset($route['capacity']) && isset($route['passengers'])) {
            if (count($route['passengers']) > $route['capacity']) {
                echo "  ✗ ERROR: Route exceeds vehicle capacity\n";
                $capacityOk = false;
                break;
            }
        }
    }
    if ($capacityOk) {
        echo "  ✓ All routes within vehicle capacity\n";
    }

} else {
    echo "  ✗ Optimization failed: " . $result['message'] . "\n";
}

echo "\n========== END OF TEST ==========\n";
?>