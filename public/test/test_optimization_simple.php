<?php
// Simple test to run optimization
echo "=== SIMPLE OPTIMIZATION TEST ===" . PHP_EOL;
echo date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

// Set up environment
session_start();
$_SESSION['admin_id'] = 1;

// Save current directory and change to admin
$originalDir = getcwd();
chdir('/var/www/partycarpool.clodhost.com/public/admin');

// Include necessary files
include_once '../config/database.php';
include_once 'pre_optimize_checks.php';

// First run pre-checks
echo "Step 1: Running pre-optimization checks..." . PHP_EOL;
$checker = new PreOptimizeChecks();
$checks_passed = $checker->runChecks();

if (!$checks_passed) {
    echo "❌ Pre-checks failed:" . PHP_EOL;
    foreach ($checker->getErrors() as $error) {
        echo "  - ERROR: $error" . PHP_EOL;
    }
    chdir($originalDir);
    exit(1);
} else {
    echo "✅ Pre-checks passed!" . PHP_EOL . PHP_EOL;
}

// Now run the optimization directly
echo "Step 2: Running optimization..." . PHP_EOL;

// Include the optimizer class
include_once 'optimize_enhanced.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo "❌ Database connection failed!" . PHP_EOL;
    chdir($originalDir);
    exit(1);
}

try {
    // Create optimizer instance
    $optimizer = new EnhancedCarpoolOptimizer($db, 1, 4, 50);

    // Run optimization
    $result = $optimizer->optimize();

    echo "✅ Optimization successful!" . PHP_EOL;
    echo "Vehicles needed: " . $result['vehicles_needed'] . PHP_EOL;
    echo "Routes created: " . count($result['routes']) . PHP_EOL;
    echo "Vehicles saved: " . $result['vehicles_saved'] . PHP_EOL;

    // Save to database
    $save_query = "INSERT INTO optimization_results (event_id, routes, vehicles_used, target_vehicles, created_at)
                   VALUES (:event_id, :routes, :vehicles_used, :target_vehicles, NOW())";
    $save_stmt = $db->prepare($save_query);
    $save_stmt->bindValue(':event_id', 1);
    $routes_json = json_encode($result['routes']);
    $save_stmt->bindValue(':routes', $routes_json);
    $save_stmt->bindValue(':vehicles_used', $result['vehicles_needed']);
    $save_stmt->bindValue(':target_vehicles', $result['target_vehicles']);

    if ($save_stmt->execute()) {
        echo "✅ Results saved to database!" . PHP_EOL;
    } else {
        echo "⚠️ Failed to save to database" . PHP_EOL;
        print_r($save_stmt->errorInfo());
    }

} catch (Exception $e) {
    echo "❌ Optimization failed with error:" . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    echo PHP_EOL . "Stack trace:" . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}

// Restore directory
chdir($originalDir);

echo PHP_EOL . "=== TEST COMPLETE ===" . PHP_EOL;
?>