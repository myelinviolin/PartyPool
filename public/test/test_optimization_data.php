<?php
// Test script to verify optimization data is being calculated and saved properly
include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "=== OPTIMIZATION DATA TEST ===\n\n";

// Check what's in the database
$query = "SELECT routes FROM optimization_results WHERE event_id = 1 ORDER BY created_at DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    $routes = json_decode($result['routes'], true);

    echo "Found " . count($routes) . " routes in database\n\n";

    foreach ($routes as $index => $route) {
        echo "Route " . ($index + 1) . ":\n";
        echo "  Driver: " . $route['driver_name'] . "\n";
        echo "  Passengers: " . count($route['passengers']) . "\n";

        // Check for missing fields
        $expected_fields = ['capacity', 'total_distance', 'direct_distance', 'vehicle'];
        $missing_fields = [];

        foreach ($expected_fields as $field) {
            if (!isset($route[$field])) {
                $missing_fields[] = $field;
            } else {
                echo "  $field: " . $route[$field] . "\n";
            }
        }

        if (!empty($missing_fields)) {
            echo "  ⚠️ MISSING FIELDS: " . implode(', ', $missing_fields) . "\n";
        }

        echo "\n";
    }

    // Display the raw JSON for first route
    echo "Raw JSON for first route:\n";
    echo json_encode($routes[0], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No optimization results found in database.\n";
}
?>