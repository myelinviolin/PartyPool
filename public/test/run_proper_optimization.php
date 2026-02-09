<?php
// Properly run optimization with all data fields
session_start();

// Simulate admin session
$_SESSION['admin_id'] = 1;

// Change to admin directory
chdir('../admin');
include_once '../config/database.php';

// Include the optimizer class file without executing it
$optimizer_code = file_get_contents('optimize_enhanced.php');

// Extract just the class definition (remove the execution part at the end)
$class_end = strpos($optimizer_code, '// Handle request');
if ($class_end !== false) {
    $class_code = substr($optimizer_code, 0, $class_end);
    eval('?>' . $class_code);
}

// Now run the optimizer directly
$database = new Database();
$db = $database->getConnection();

$optimizer = new EnhancedCarpoolOptimizer($db, 1, 8, 50);
$result = $optimizer->optimize();

echo "=== OPTIMIZATION RUN RESULT ===\n\n";

if ($result && isset($result['success'])) {
    if ($result['success']) {
        echo "✅ Optimization successful!\n";
        echo "Vehicles used: " . $result['vehicles_needed'] . "\n";
        echo "Routes created: " . count($result['routes']) . "\n\n";

        // Check first route for all fields
        if (!empty($result['routes'])) {
            $first_route = $result['routes'][0];
            echo "First route details:\n";
            echo "  Driver: " . $first_route['driver_name'] . "\n";
            echo "  Capacity: " . ($first_route['capacity'] ?? 'MISSING') . "\n";
            echo "  Vehicle: " . ($first_route['vehicle'] ?? 'MISSING') . "\n";
            echo "  Direct Distance: " . ($first_route['direct_distance'] ?? 'MISSING') . " miles\n";
            echo "  Total Distance: " . ($first_route['total_distance'] ?? 'MISSING') . " miles\n";
            echo "  Has Passengers: " . (isset($first_route['has_passengers']) ? ($first_route['has_passengers'] ? 'Yes' : 'No') : 'MISSING') . "\n";
        }
    } else {
        echo "❌ Optimization failed: " . $result['message'] . "\n";
    }
} else {
    echo "❌ Failed to parse result\n";
    echo "Raw output:\n$output\n";
}

// Now check what was saved to database
echo "\n=== DATABASE CHECK ===\n\n";

$database = new Database();
$db = $database->getConnection();

$query = "SELECT routes FROM optimization_results WHERE event_id = 1 ORDER BY created_at DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$db_result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($db_result) {
    $routes = json_decode($db_result['routes'], true);
    if (!empty($routes)) {
        $first_route = $routes[0];
        echo "First route in database:\n";
        echo "  Driver: " . $first_route['driver_name'] . "\n";
        echo "  Capacity: " . ($first_route['capacity'] ?? 'MISSING') . "\n";
        echo "  Vehicle: " . ($first_route['vehicle'] ?? 'MISSING') . "\n";
        echo "  Direct Distance: " . ($first_route['direct_distance'] ?? 'MISSING') . " miles\n";
        echo "  Total Distance: " . ($first_route['total_distance'] ?? 'MISSING') . " miles\n";
    }
} else {
    echo "No data found in database\n";
}
?>