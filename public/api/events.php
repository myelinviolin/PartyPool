<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(503);
    echo json_encode(["message" => "Database connection failed"]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // Get event details
        $event_id = isset($_GET['id']) ? $_GET['id'] : 1;

        $query = "SELECT e.*,
                    (SELECT COUNT(DISTINCT u.id) FROM users u WHERE u.event_id = e.id AND u.willing_to_drive = 1) as total_drivers,
                    (SELECT COUNT(DISTINCT u.id) FROM users u WHERE u.event_id = e.id AND u.willing_to_drive = 0) as total_riders,
                    (SELECT COUNT(DISTINCT u.id) FROM users u WHERE u.event_id = e.id) as total_participants,
                    (SELECT SUM(u.vehicle_capacity) FROM users u WHERE u.event_id = e.id AND u.willing_to_drive = 1) as total_vehicle_capacity
                  FROM events e
                  WHERE e.id = :event_id";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':event_id', $event_id);
        $stmt->execute();

        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            http_response_code(404);
            echo json_encode(["message" => "Event not found"]);
        } else {
            echo json_encode($event);
        }
        break;

    case 'POST':
        // Create new event
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['event_name']) || empty($data['event_date']) || empty($data['event_time']) || empty($data['event_address'])) {
            http_response_code(400);
            echo json_encode(["message" => "Event name, date, time, and address are required"]);
            exit();
        }

        $query = "INSERT INTO events (event_name, event_date, event_time, event_address, event_lat, event_lng, description)
                  VALUES (:name, :date, :time, :address, :lat, :lng, :description)";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $data['event_name']);
        $stmt->bindParam(':date', $data['event_date']);
        $stmt->bindParam(':time', $data['event_time']);
        $stmt->bindParam(':address', $data['event_address']);
        $stmt->bindParam(':lat', $data['event_lat']);
        $stmt->bindParam(':lng', $data['event_lng']);
        $stmt->bindParam(':description', $data['description']);

        try {
            $stmt->execute();
            $event_id = $db->lastInsertId();
            http_response_code(201);
            echo json_encode(["message" => "Event created successfully", "event_id" => $event_id]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error creating event: " . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
        break;
}
?>