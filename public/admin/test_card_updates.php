<?php
session_start();
$_SESSION['admin_id'] = 1;

include_once '../config/database.php';
require_once 'optimize_enhanced.php';

$database = new Database();
$db = $database->getConnection();

echo "========== TESTING PARTICIPANT CARD UPDATES ==========\n\n";

// Step 1: Reset all assignments
echo "Step 1: Resetting all assignments...\n";
$reset_query = "UPDATE users SET is_assigned_driver = FALSE WHERE event_id = 1";
$db->exec($reset_query);
echo "✓ All assignments reset\n\n";

// Step 2: Run optimization
echo "Step 2: Running optimization...\n";
$optimizer = new EnhancedCarpoolOptimizer($db, 1, null, 50);
$result = $optimizer->optimize();

if (!$result['success']) {
    echo "✗ Optimization failed: " . $result['message'] . "\n";
    exit(1);
}
echo "✓ Optimization successful\n";
echo "  - Routes created: " . count($result['routes']) . "\n\n";

// Step 3: Check database assignments
echo "Step 3: Checking database assignments...\n";
$query = "SELECT id, name, is_assigned_driver, willing_to_drive FROM users WHERE event_id = 1 ORDER BY id";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Track which users should be drivers based on optimization
$optimization_drivers = [];
$optimization_passengers = [];

foreach ($result['routes'] as $route) {
    $optimization_drivers[] = $route['driver_id'];
    if (isset($route['passengers'])) {
        foreach ($route['passengers'] as $p) {
            $optimization_passengers[] = $p['id'];
        }
    }
}

echo "Database vs Optimization Comparison:\n";
echo "====================================\n\n";

$errors = [];
foreach ($users as $user) {
    $id = $user['id'];
    $name = $user['name'];
    $is_driver_in_db = $user['is_assigned_driver'];
    $is_driver_in_opt = in_array($id, $optimization_drivers);
    $is_passenger_in_opt = in_array($id, $optimization_passengers);

    echo sprintf("%-30s | DB Driver: %s | Opt Role: ",
        $name,
        $is_driver_in_db ? 'YES' : 'NO '
    );

    if ($is_driver_in_opt) {
        echo "DRIVER";
        if (!$is_driver_in_db) {
            $errors[] = "$name should have is_assigned_driver = TRUE";
            echo " ✗ MISMATCH";
        } else {
            echo " ✓";
        }
    } elseif ($is_passenger_in_opt) {
        echo "PASSENGER";
        if ($is_driver_in_db) {
            $errors[] = "$name should have is_assigned_driver = FALSE";
            echo " ✗ MISMATCH";
        } else {
            echo " ✓";
        }
    } else {
        echo "UNASSIGNED ✗";
        $errors[] = "$name is not assigned in optimization";
    }
    echo "\n";
}

echo "\n====================================\n";
echo "TEST RESULTS:\n";
echo "====================================\n\n";

if (count($errors) === 0) {
    echo "✅ ALL TESTS PASSED!\n";
    echo "All participant cards should display correctly:\n";
    echo "  - Drivers have blue cards with 'Driving' badge\n";
    echo "  - Passengers have orange cards with 'Riding with' badge\n";
} else {
    echo "❌ ERRORS FOUND:\n";
    foreach ($errors as $error) {
        echo "  ✗ $error\n";
    }
}

echo "\n========== END OF TEST ==========\n";
?>