<?php
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
$_SESSION['admin_id'] = 1; // Set admin session for testing

echo "Testing optimize_enhanced.php for errors...\n\n";

// Test 1: Check if class file exists and loads
echo "1. Testing class file load:\n";
try {
    // First just try to include the file
    ob_start();
    $included = include_once 'optimize_enhanced.php';
    $output = ob_get_clean();

    if ($included) {
        echo "   ✅ File included successfully\n";
        if (!empty($output)) {
            echo "   ⚠️ File produced output: " . substr($output, 0, 100) . "\n";
        }
    } else {
        echo "   ❌ Failed to include file\n";
    }

    // Check if class exists
    if (class_exists('EnhancedCarpoolOptimizer')) {
        echo "   ✅ EnhancedCarpoolOptimizer class exists\n";
    } else {
        echo "   ❌ EnhancedCarpoolOptimizer class not found\n";
    }
} catch (Exception $e) {
    echo "   ❌ Exception: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "   ❌ Fatal Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

echo "\n2. Testing database connection:\n";
try {
    include_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    if ($db) {
        echo "   ✅ Database connected\n";

        // Test a simple query
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE event_id = 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   ✅ Found " . $result['count'] . " users for event 1\n";
    } else {
        echo "   ❌ Database connection failed\n";
    }
} catch (Exception $e) {
    echo "   ❌ Database Exception: " . $e->getMessage() . "\n";
}

echo "\n3. Testing optimizer instantiation:\n";
try {
    if (class_exists('EnhancedCarpoolOptimizer') && isset($db)) {
        $optimizer = new EnhancedCarpoolOptimizer($db, 1, null, 50);
        echo "   ✅ Optimizer created successfully\n";

        echo "\n4. Testing optimize() method:\n";
        $result = $optimizer->optimize();
        echo "   ✅ optimize() completed\n";

        if (is_array($result)) {
            echo "   ✅ Result is array\n";
            echo "   Success: " . ($result['success'] ? 'true' : 'false') . "\n";
            if (isset($result['message'])) {
                echo "   Message: " . $result['message'] . "\n";
            }
            if (isset($result['total_participants'])) {
                echo "   Participants: " . $result['total_participants'] . "\n";
            }
        } else {
            echo "   ❌ Result is not an array\n";
        }
    } else {
        echo "   ❌ Cannot create optimizer (class or db missing)\n";
    }
} catch (Exception $e) {
    echo "   ❌ Optimizer Exception: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "   ❌ Optimizer Fatal Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n5. Checking PHP configuration:\n";
echo "   PHP Version: " . phpversion() . "\n";
echo "   Memory Limit: " . ini_get('memory_limit') . "\n";
echo "   Max Execution Time: " . ini_get('max_execution_time') . "\n";
echo "   Error Reporting: " . error_reporting() . "\n";
?>