<?php
session_start();
$_SESSION['admin_id'] = 1;

include_once '../config/database.php';
require_once 'optimize_enhanced.php';

echo "========== TESTING ITINERARY GENERATION ==========\n\n";

$database = new Database();
$db = $database->getConnection();

// Step 1: Run optimization
echo "Step 1: Running optimization...\n";
$optimizer = new EnhancedCarpoolOptimizer($db, 1, null, 50);
$result = $optimizer->optimize();

if (!$result['success']) {
    echo "✗ Optimization failed: " . $result['message'] . "\n";
    exit(1);
}

echo "✓ Optimization successful\n";
echo "  - Routes created: " . count($result['routes']) . "\n\n";

// Step 2: Save assignments
echo "Step 2: Saving assignments...\n";

// Clear previous assignments
$clear_query = "UPDATE users SET is_assigned_driver = FALSE, assigned_driver_id = NULL WHERE event_id = 1";
$db->exec($clear_query);

// Save new assignments
foreach ($result['routes'] as $route) {
    // Mark driver
    $driver_query = "UPDATE users SET is_assigned_driver = TRUE WHERE id = :driver_id";
    $driver_stmt = $db->prepare($driver_query);
    $driver_stmt->bindParam(':driver_id', $route['driver_id']);
    $driver_stmt->execute();

    // Assign passengers
    if (isset($route['passengers']) && !empty($route['passengers'])) {
        foreach ($route['passengers'] as $passenger) {
            $passenger_query = "UPDATE users SET assigned_driver_id = :driver_id WHERE id = :passenger_id";
            $passenger_stmt = $db->prepare($passenger_query);
            $passenger_stmt->bindParam(':driver_id', $route['driver_id']);
            $passenger_stmt->bindParam(':passenger_id', $passenger['id']);
            $passenger_stmt->execute();
        }
    }
}

// Save optimization result
$save_query = "INSERT INTO optimization_results (event_id, routes, vehicles_used, created_at)
               VALUES (1, :routes, :vehicles_used, NOW())
               ON DUPLICATE KEY UPDATE routes = VALUES(routes), vehicles_used = VALUES(vehicles_used), created_at = NOW()";
$save_stmt = $db->prepare($save_query);
$routes_json = json_encode($result['routes']);
$save_stmt->bindParam(':routes', $routes_json);
$vehicles_used = count($result['routes']);
$save_stmt->bindParam(':vehicles_used', $vehicles_used);
$save_stmt->execute();

echo "✓ Assignments saved\n\n";

// Step 3: Generate itinerary
echo "Step 3: Generating itinerary...\n";
echo "====================================\n\n";

// Get event info
$event_query = "SELECT * FROM events WHERE id = 1";
$event_stmt = $db->prepare($event_query);
$event_stmt->execute();
$event = $event_stmt->fetch(PDO::FETCH_ASSOC);

// Display sample itinerary content
echo "CARPOOL ITINERARY\n";
echo "=================\n";
echo "Event: " . $event['event_name'] . "\n";
echo "Date: " . date('F j, Y', strtotime($event['event_date'])) . "\n";
echo "Time: " . date('g:i A', strtotime($event['event_time'])) . "\n";
echo "Location: " . $event['event_address'] . "\n\n";

// Show first 2 routes as example
$count = 0;
foreach ($result['routes'] as $route) {
    if ($count >= 2) {
        echo "\n[... Additional routes omitted for brevity ...]\n";
        break;
    }

    echo "\nDRIVER ITINERARY\n";
    echo str_repeat("-", 50) . "\n";
    echo "Driver: " . $route['driver_name'] . "\n";
    echo "Vehicle: " . ($route['vehicle'] ?? 'Not specified') . "\n";
    echo "Departure Time: " . ($route['departure_time'] ?? 'TBD') . "\n";

    if (isset($route['estimated_travel_time'])) {
        echo "Total Travel Time: " . $route['estimated_travel_time'] . "\n";
    }

    if (isset($route['has_passengers']) && $route['has_passengers'] && !empty($route['passengers'])) {
        echo "\nPICKUP SCHEDULE:\n";
        $stop_number = 1;
        foreach ($route['passengers'] as $passenger) {
            echo "  Stop #" . $stop_number . ": " . $passenger['name'] . "\n";
            echo "  Address: " . ($passenger['address'] ?? 'TBD') . "\n";
            $stop_number++;
        }
    } else {
        echo "\nDRIVING DIRECTLY TO EVENT (No passengers)\n";
    }

    $count++;
}

echo "\n====================================\n";
echo "✓ Itinerary generation successful!\n\n";
echo "To download the full itinerary, click 'Save These Assignments' in the dashboard.\n";
echo "The file will be named: carpool_itinerary_" . date('Y-m-d') . ".txt\n\n";

echo "========== END OF TEST ==========\n";
?>