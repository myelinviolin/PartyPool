<?php
// Final verification that optimization works
echo "=== OPTIMIZATION VERIFICATION ===" . PHP_EOL;
echo date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

// Setup
session_start();
$_SESSION['admin_id'] = 1;

// Change to admin directory
$originalDir = getcwd();
chdir('/var/www/partycarpool.clodhost.com/public/admin');

// Test 1: Pre-checks
echo "Test 1: Pre-optimization checks" . PHP_EOL;
include_once 'pre_optimize_checks.php';
$checker = new PreOptimizeChecks();
if ($checker->runChecks()) {
    echo "✅ PASS - All pre-checks passed" . PHP_EOL;
} else {
    echo "❌ FAIL - Pre-checks failed:" . PHP_EOL;
    foreach ($checker->getErrors() as $error) {
        echo "  - $error" . PHP_EOL;
    }
}
echo PHP_EOL;

// Test 2: Lake location check specifically
echo "Test 2: Lake location check" . PHP_EOL;
$output = [];
$return_code = 0;
exec('php lake_location_check.php', $output, $return_code);
if ($return_code === 0) {
    echo "✅ PASS - No participants in water bodies" . PHP_EOL;
} else {
    echo "❌ FAIL - Lake check failed" . PHP_EOL;
}
echo PHP_EOL;

// Test 3: Can create optimizer instance
echo "Test 3: Optimizer instantiation" . PHP_EOL;
include_once '../config/database.php';
include_once 'optimize_enhanced.php';

$database = new Database();
$db = $database->getConnection();

if ($db) {
    try {
        $optimizer = new EnhancedCarpoolOptimizer($db, 1, 4);
        echo "✅ PASS - Optimizer created successfully" . PHP_EOL;
    } catch (Exception $e) {
        echo "❌ FAIL - " . $e->getMessage() . PHP_EOL;
    }
} else {
    echo "❌ FAIL - Database connection failed" . PHP_EOL;
}
echo PHP_EOL;

// Test 4: Can run optimization
echo "Test 4: Run optimization" . PHP_EOL;
if (isset($optimizer)) {
    try {
        // First clear any existing results
        $clear_query = "DELETE FROM optimization_results WHERE event_id = 1";
        $db->exec($clear_query);

        $result = $optimizer->optimize();
        if ($result['success']) {
            echo "✅ PASS - Optimization completed" . PHP_EOL;
            echo "  - Vehicles used: " . $result['vehicles_needed'] . PHP_EOL;
            echo "  - Routes created: " . count($result['routes']) . PHP_EOL;
        } else {
            echo "❌ FAIL - Optimization failed: " . $result['message'] . PHP_EOL;
        }
    } catch (Exception $e) {
        echo "❌ FAIL - " . $e->getMessage() . PHP_EOL;
    }
}

// Restore directory
chdir($originalDir);

echo PHP_EOL . "=== VERIFICATION COMPLETE ===" . PHP_EOL;
?>