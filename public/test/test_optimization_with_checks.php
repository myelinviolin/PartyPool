<?php
// Test optimization with pre-checks
session_start();
$_SESSION['admin_id'] = 1;

include_once '../config/database.php';
include_once '../admin/pre_optimize_checks.php';
include_once '../admin/optimize_enhanced.php';

$database = new Database();
$db = $database->getConnection();

echo "=== TESTING OPTIMIZATION WITH PRE-CHECKS ===" . PHP_EOL;
echo date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

// First run pre-checks separately to see what happens
echo "Running pre-optimization checks..." . PHP_EOL;
$checker = new PreOptimizeChecks();
$checks_passed = $checker->runChecks();

if (!$checks_passed) {
    echo "❌ Pre-checks failed:" . PHP_EOL;
    foreach ($checker->getErrors() as $error) {
        echo "  - ERROR: $error" . PHP_EOL;
    }
} else {
    echo "✅ Pre-checks passed!" . PHP_EOL;
}

// Now try optimization
echo PHP_EOL . "Running optimization..." . PHP_EOL;

try {
    $optimizer = new EnhancedCarpoolOptimizer($db, 1, 4);
    $result = $optimizer->optimize();

    echo "✅ Optimization successful!" . PHP_EOL;
    echo "Vehicles used: " . $result['vehicles_needed'] . PHP_EOL;
    echo "Routes created: " . count($result['routes']) . PHP_EOL;

    // Save to database
    $save_query = "INSERT INTO optimization_results (event_id, routes, vehicles_used, created_at)
                   VALUES (:event_id, :routes, :vehicles_used, NOW())";
    $save_stmt = $db->prepare($save_query);
    $save_stmt->bindParam(':event_id', $result['event_id']);
    $routes_json = json_encode($result['routes']);
    $save_stmt->bindParam(':routes', $routes_json);
    $save_stmt->bindParam(':vehicles_used', $result['vehicles_needed']);

    if ($save_stmt->execute()) {
        echo "✅ Results saved to database!" . PHP_EOL;
    } else {
        echo "⚠️ Failed to save to database" . PHP_EOL;
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . PHP_EOL;
    echo PHP_EOL . "Stack trace:" . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}

echo PHP_EOL . "=== TEST COMPLETE ===" . PHP_EOL;
?>