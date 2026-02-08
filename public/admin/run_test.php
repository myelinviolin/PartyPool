<?php
session_start();
$_SESSION['admin_id'] = 1;

include_once '../config/database.php';

// Function to run optimization
function runOptimization($event_id = 1, $target_vehicles = null) {
    // Simulate JSON POST data
    $input_data = json_encode([
        'event_id' => $event_id,
        'target_vehicles' => $target_vehicles,
        'max_drive_time' => 50
    ]);

    // Set up the environment for the optimization script
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'application/json';

    // Capture output
    ob_start();

    // Override php://input with our data
    $temp = tmpfile();
    fwrite($temp, $input_data);
    fseek($temp, 0);
    stream_filter_register("myinput", "MyInputStream");
    stream_filter_append($temp, "myinput");

    // Execute the optimization
    $optimizer = new CarpoolOptimizer();
    $result = $optimizer->optimize($event_id, $target_vehicles, 50);

    ob_end_clean();

    return $result;
}

// Include the optimizer class
require_once 'optimize_enhanced.php';

// Function to get participant cards from database
function getParticipantCards($event_id = 1) {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT id, name, willing_to_drive, vehicle_capacity FROM users WHERE event_id = :event_id ORDER BY id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':event_id', $event_id);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Run the test
echo "========== OPTIMIZATION ACCURACY TEST ==========\n\n";

// Step 1: Run optimization
echo "Step 1: Running optimization...\n";
$optimization = runOptimization(1, null);

if (!$optimization || !$optimization['success']) {
    echo "ERROR: Optimization failed\n";
    if ($optimization) {
        echo "Message: " . $optimization['message'] . "\n";
    }
    exit(1);
}

echo "✓ Optimization completed successfully\n";
echo "  - Vehicles needed: " . $optimization['vehicles_needed'] . "\n";
echo "  - Total participants: " . $optimization['total_participants'] . "\n";
echo "  - Routes created: " . count($optimization['routes']) . "\n\n";

// Step 2: Get participant data
echo "Step 2: Getting participant cards...\n";
$participants = getParticipantCards(1);
echo "✓ Found " . count($participants) . " participants\n\n";

// Create participant map for easy lookup
$participantMap = [];
foreach ($participants as $p) {
    $participantMap[$p['name']] = $p;
    // Also map without prefixes
    if (preg_match('/^(Driver|Rider) \d+ - (.+)$/', $p['name'], $matches)) {
        $participantMap[$matches[2]] = $p;
    }
}

// Step 3: Verify results
echo "Step 3: Verifying accuracy...\n";
echo "=" . str_repeat("=", 50) . "\n\n";

$errors = [];
$warnings = [];
$passes = [];

// Track assignments
$verifiedDrivers = [];
$verifiedPassengers = [];

// Check each route
foreach ($optimization['routes'] as $index => $route) {
    echo "Route " . ($index + 1) . ":\n";
    echo "  Driver: " . $route['driver_name'] . "\n";

    // Find driver in participants
    $driverFound = false;
    foreach ($participantMap as $name => $participant) {
        if (strpos($name, $route['driver_name']) !== false ||
            strpos($route['driver_name'], $name) !== false) {
            $driverFound = true;
            $verifiedDrivers[] = $route['driver_name'];

            // Check if this person can drive
            if ($participant['willing_to_drive']) {
                $passes[] = "Driver '{$route['driver_name']}' is marked as willing to drive";
                echo "    ✓ Driver is marked as willing to drive\n";
            } else {
                $errors[] = "Driver '{$route['driver_name']}' is NOT marked as willing to drive!";
                echo "    ✗ ERROR: Driver is NOT marked as willing to drive!\n";
            }
            break;
        }
    }

    if (!$driverFound) {
        $errors[] = "Driver '{$route['driver_name']}' not found in participants";
        echo "    ✗ ERROR: Driver not found in participants!\n";
    }

    // Check passengers
    if (!empty($route['passengers'])) {
        echo "  Passengers (" . count($route['passengers']) . "):\n";
        foreach ($route['passengers'] as $passenger) {
            echo "    - " . $passenger['name'] . "\n";

            $passengerFound = false;
            foreach ($participantMap as $name => $participant) {
                if (strpos($name, $passenger['name']) !== false ||
                    strpos($passenger['name'], $name) !== false) {
                    $passengerFound = true;
                    $verifiedPassengers[] = $passenger['name'];
                    $passes[] = "Passenger '{$passenger['name']}' found in participants";
                    echo "      ✓ Found in participants\n";
                    break;
                }
            }

            if (!$passengerFound) {
                $errors[] = "Passenger '{$passenger['name']}' not found in participants";
                echo "      ✗ ERROR: Not found in participants!\n";
            }
        }
    } else {
        echo "  No passengers (driving solo)\n";
    }
    echo "\n";
}

// Summary statistics
echo "=" . str_repeat("=", 50) . "\n";
echo "SUMMARY:\n";
echo "=" . str_repeat("=", 50) . "\n\n";

$totalDrivers = count($optimization['routes']);
$totalPassengers = 0;
foreach ($optimization['routes'] as $route) {
    if (!empty($route['passengers'])) {
        $totalPassengers += count($route['passengers']);
    }
}

echo "Optimization Results:\n";
echo "  - Total drivers assigned: $totalDrivers\n";
echo "  - Total passengers assigned: $totalPassengers\n";
echo "  - Total people in carpool: " . ($totalDrivers + $totalPassengers) . "\n\n";

echo "Verification Results:\n";
echo "  - Verified drivers: " . count($verifiedDrivers) . "\n";
echo "  - Verified passengers: " . count($verifiedPassengers) . "\n\n";

// Check driver eligibility
$eligibleDrivers = 0;
foreach ($participants as $p) {
    if ($p['willing_to_drive'] && $p['vehicle_capacity'] > 0) {
        $eligibleDrivers++;
    }
}

echo "Participant Analysis:\n";
echo "  - Total participants: " . count($participants) . "\n";
echo "  - Eligible drivers: $eligibleDrivers\n";
echo "  - Non-drivers: " . (count($participants) - $eligibleDrivers) . "\n\n";

// Display test results
echo "TEST RESULTS:\n";
echo "=" . str_repeat("=", 50) . "\n\n";

if (count($errors) === 0) {
    echo "✅ ALL TESTS PASSED!\n\n";
} else {
    echo "❌ SOME TESTS FAILED\n\n";
}

echo "Passes: " . count($passes) . "\n";
echo "Errors: " . count($errors) . "\n";
echo "Warnings: " . count($warnings) . "\n\n";

if (count($errors) > 0) {
    echo "ERRORS:\n";
    foreach ($errors as $error) {
        echo "  ✗ $error\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "  ⚠ $warning\n";
    }
    echo "\n";
}

// Final verification
echo "CRITICAL VERIFICATION:\n";
echo "=" . str_repeat("=", 50) . "\n";

$allDriversEligible = true;
foreach ($optimization['routes'] as $route) {
    $driverName = $route['driver_name'];
    $found = false;

    foreach ($participantMap as $name => $p) {
        if (strpos($name, $driverName) !== false || strpos($driverName, $name) !== false) {
            if (!$p['willing_to_drive']) {
                echo "❌ CRITICAL ERROR: Driver '$driverName' is NOT eligible to drive!\n";
                $allDriversEligible = false;
            } else {
                echo "✅ Driver '$driverName' is eligible to drive (has vehicle)\n";
            }
            $found = true;
            break;
        }
    }

    if (!$found) {
        echo "❌ CRITICAL ERROR: Driver '$driverName' not found in database!\n";
        $allDriversEligible = false;
    }
}

echo "\n";
if ($allDriversEligible) {
    echo "✅ PASS: All drivers in optimization are eligible drivers\n";
} else {
    echo "❌ FAIL: Some drivers in optimization are not eligible!\n";
}

echo "\n========== END OF TEST ==========\n";
?>