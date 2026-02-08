<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(503);
    echo json_encode(["message" => "Database connection failed"]);
    exit();
}

// Get event ID from query parameter
$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : 1;

// Retrieve optimization results from database
$query = "SELECT
            or_result.routes,
            or_result.vehicles_used,
            or_result.target_vehicles,
            or_result.created_at,
            e.optimization_status,
            e.optimization_run_at,
            (SELECT COUNT(*) FROM users WHERE event_id = :event_id1) as total_participants,
            (SELECT COUNT(*) FROM users WHERE event_id = :event_id2 AND willing_to_drive = 1) as total_drivers,
            (SELECT SUM(vehicle_capacity) FROM users WHERE event_id = :event_id3 AND willing_to_drive = 1) as total_capacity
          FROM optimization_results or_result
          JOIN events e ON or_result.event_id = e.id
          WHERE or_result.event_id = :event_id4
          ORDER BY or_result.created_at DESC
          LIMIT 1";

$stmt = $db->prepare($query);
$stmt->bindParam(':event_id1', $event_id);
$stmt->bindParam(':event_id2', $event_id);
$stmt->bindParam(':event_id3', $event_id);
$stmt->bindParam(':event_id4', $event_id);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Parse the JSON routes
    $routes = json_decode($result['routes'], true);

    // Calculate vehicles saved
    $vehicles_saved = $result['total_drivers'] - $result['vehicles_used'];

    // Build response
    $response = [
        'success' => true,
        'optimization_exists' => true,
        'routes' => $routes,
        'vehicles_needed' => $result['vehicles_used'],
        'target_vehicles' => $result['target_vehicles'],
        'vehicles_saved' => $vehicles_saved,
        'total_participants' => $result['total_participants'],
        'total_drivers' => $result['total_drivers'],
        'total_capacity' => $result['total_capacity'],
        'optimization_status' => $result['optimization_status'],
        'optimization_run_at' => $result['optimization_run_at'],
        'created_at' => $result['created_at']
    ];

    echo json_encode($response);
} else {
    // No optimization results found
    echo json_encode([
        'success' => true,
        'optimization_exists' => false,
        'message' => 'No optimization results found. Admin needs to run optimization first.',
        'routes' => [],
        'vehicles_needed' => 0,
        'vehicles_saved' => 0
    ]);
}
?>