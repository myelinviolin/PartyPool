<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
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
$data = json_decode(file_get_contents("php://input"), true);

switch($method) {
    case 'POST':
        // Create carpool match
        if (empty($data['driver_id']) || empty($data['rider_id']) || empty($data['event_id'])) {
            http_response_code(400);
            echo json_encode(["message" => "Driver ID, Rider ID, and Event ID are required"]);
            exit();
        }

        // Check if rider already has a carpool for this event
        $check_query = "SELECT id FROM carpools WHERE rider_id = :rider_id AND event_id = :event_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':rider_id', $data['rider_id']);
        $check_stmt->bindParam(':event_id', $data['event_id']);
        $check_stmt->execute();

        if ($check_stmt->rowCount() > 0) {
            http_response_code(409);
            echo json_encode(["message" => "Rider already has a carpool for this event"]);
            exit();
        }

        // Check available seats for driver
        $seats_query = "SELECT
                          d.available_seats,
                          (SELECT COUNT(*) FROM carpools WHERE driver_id = :driver_id AND event_id = :event_id AND status != 'cancelled') as used_seats
                        FROM drivers d
                        JOIN users u ON d.user_id = u.id
                        WHERE u.id = :driver_id";
        $seats_stmt = $db->prepare($seats_query);
        $seats_stmt->bindParam(':driver_id', $data['driver_id']);
        $seats_stmt->bindParam(':event_id', $data['event_id']);
        $seats_stmt->execute();
        $seats_info = $seats_stmt->fetch(PDO::FETCH_ASSOC);

        if ($seats_info['used_seats'] >= $seats_info['available_seats']) {
            http_response_code(409);
            echo json_encode(["message" => "No available seats for this driver"]);
            exit();
        }

        $query = "INSERT INTO carpools (driver_id, rider_id, event_id, pickup_address, pickup_lat, pickup_lng, pickup_time, status, notes)
                  VALUES (:driver_id, :rider_id, :event_id, :pickup_address, :pickup_lat, :pickup_lng, :pickup_time, :status, :notes)";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':driver_id', $data['driver_id']);
        $stmt->bindParam(':rider_id', $data['rider_id']);
        $stmt->bindParam(':event_id', $data['event_id']);
        $stmt->bindParam(':pickup_address', $data['pickup_address']);
        $stmt->bindParam(':pickup_lat', $data['pickup_lat']);
        $stmt->bindParam(':pickup_lng', $data['pickup_lng']);
        $stmt->bindParam(':pickup_time', $data['pickup_time']);
        $status = isset($data['status']) ? $data['status'] : 'pending';
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':notes', $data['notes']);

        try {
            $stmt->execute();
            $carpool_id = $db->lastInsertId();

            http_response_code(201);
            echo json_encode(["message" => "Carpool created successfully", "carpool_id" => $carpool_id]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error creating carpool: " . $e->getMessage()]);
        }
        break;

    case 'GET':
        // Get carpools
        $event_id = isset($_GET['event_id']) ? $_GET['event_id'] : 1;

        $query = "SELECT
                    c.*,
                    driver.name as driver_name,
                    driver.email as driver_email,
                    driver.phone as driver_phone,
                    rider.name as rider_name,
                    rider.email as rider_email,
                    rider.phone as rider_phone,
                    d.available_seats,
                    d.car_make,
                    d.car_model,
                    d.car_color,
                    d.departure_time
                  FROM carpools c
                  JOIN users driver ON c.driver_id = driver.id
                  JOIN users rider ON c.rider_id = rider.id
                  LEFT JOIN drivers d ON driver.id = d.user_id
                  WHERE c.event_id = :event_id";

        if (isset($_GET['driver_id'])) {
            $query .= " AND c.driver_id = :driver_id";
        }
        if (isset($_GET['rider_id'])) {
            $query .= " AND c.rider_id = :rider_id";
        }
        if (isset($_GET['status'])) {
            $query .= " AND c.status = :status";
        }

        $stmt = $db->prepare($query);
        $stmt->bindParam(':event_id', $event_id);

        if (isset($_GET['driver_id'])) {
            $stmt->bindParam(':driver_id', $_GET['driver_id']);
        }
        if (isset($_GET['rider_id'])) {
            $stmt->bindParam(':rider_id', $_GET['rider_id']);
        }
        if (isset($_GET['status'])) {
            $stmt->bindParam(':status', $_GET['status']);
        }

        $stmt->execute();
        $carpools = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($carpools);
        break;

    case 'PUT':
        // Update carpool status
        if (empty($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["message" => "Carpool ID required"]);
            exit();
        }

        if (empty($data['status'])) {
            http_response_code(400);
            echo json_encode(["message" => "Status is required"]);
            exit();
        }

        $query = "UPDATE carpools SET status = :status WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $data['status']);
        $stmt->bindParam(':id', $_GET['id']);

        try {
            $stmt->execute();
            echo json_encode(["message" => "Carpool status updated successfully"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error updating carpool: " . $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Cancel carpool
        if (empty($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["message" => "Carpool ID required"]);
            exit();
        }

        $query = "UPDATE carpools SET status = 'cancelled' WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_GET['id']);

        try {
            $stmt->execute();
            echo json_encode(["message" => "Carpool cancelled successfully"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error cancelling carpool: " . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
        break;
}
?>