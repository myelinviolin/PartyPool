<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include_once '../config/database.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$event_id = $input['event_id'] ?? 1;
$routes = $input['routes'] ?? null;

// Initialize database
$database = new Database();
$db = $database->getConnection();

try {
    // Begin transaction
    $db->beginTransaction();

    // Clear previous assignments for this event
    $clear_query = "UPDATE users SET is_assigned_driver = FALSE, assigned_driver_id = NULL WHERE event_id = :event_id";
    $clear_stmt = $db->prepare($clear_query);
    $clear_stmt->bindParam(':event_id', $event_id);
    $clear_stmt->execute();

    // If routes are provided, save them
    if ($routes) {
        foreach ($routes as $route) {
            // Mark driver
            $driver_query = "UPDATE users SET is_assigned_driver = TRUE WHERE id = :driver_id";
            $driver_stmt = $db->prepare($driver_query);
            $driver_stmt->bindParam(':driver_id', $route['driver_id']);
            $driver_stmt->execute();

            // Assign passengers to driver
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

        // Save optimization result for future reference
        $save_query = "INSERT INTO optimization_results (event_id, routes, vehicles_used, created_at)
                       VALUES (:event_id, :routes, :vehicles_used, NOW())
                       ON DUPLICATE KEY UPDATE routes = VALUES(routes), vehicles_used = VALUES(vehicles_used), created_at = NOW()";
        $save_stmt = $db->prepare($save_query);
        $save_stmt->bindParam(':event_id', $event_id);
        $routes_json = json_encode($routes);
        $save_stmt->bindParam(':routes', $routes_json);
        $vehicles_used = count($routes);
        $save_stmt->bindParam(':vehicles_used', $vehicles_used);
        $save_stmt->execute();
    }

    // Commit transaction
    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Assignments saved successfully!',
        'vehicles_used' => isset($routes) ? count($routes) : 0
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error saving assignments: ' . $e->getMessage()]);
}
?>