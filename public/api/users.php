<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(503);
    echo json_encode(["message" => "Database connection failed"]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents("php://input");
$data = json_decode($input, true);

switch($method) {
    case 'POST':
        // Register new user
        if (empty($data['name']) || empty($data['email'])) {
            http_response_code(400);
            echo json_encode(["message" => "Name and email are required"]);
            exit();
        }

        $query = "INSERT INTO users (name, email, phone, willing_to_drive, vehicle_capacity,
                  vehicle_make, vehicle_model, vehicle_color, preferred_departure_time,
                  address, lat, lng, special_notes, event_id)
                  VALUES (:name, :email, :phone, :willing_to_drive, :vehicle_capacity,
                  :vehicle_make, :vehicle_model, :vehicle_color, :departure_time,
                  :address, :lat, :lng, :special_notes, :event_id)";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':phone', $data['phone']);

        $willing_to_drive = isset($data['willing_to_drive']) ? (bool)$data['willing_to_drive'] : false;
        $stmt->bindParam(':willing_to_drive', $willing_to_drive, PDO::PARAM_BOOL);

        // Vehicle details only if willing to drive
        if ($willing_to_drive) {
            $stmt->bindParam(':vehicle_capacity', $data['vehicle_capacity']);
            $stmt->bindParam(':vehicle_make', $data['vehicle_make']);
            $stmt->bindParam(':vehicle_model', $data['vehicle_model']);
            $stmt->bindParam(':vehicle_color', $data['vehicle_color']);
            // departure_time is not provided in the form, set to null
            $null = null;
            $stmt->bindParam(':departure_time', $null);
        } else {
            $null = null;
            $stmt->bindParam(':vehicle_capacity', $null);
            $stmt->bindParam(':vehicle_make', $null);
            $stmt->bindParam(':vehicle_model', $null);
            $stmt->bindParam(':vehicle_color', $null);
            $stmt->bindParam(':departure_time', $null);
        }

        $stmt->bindParam(':address', $data['address']);
        $stmt->bindParam(':lat', $data['lat']);
        $stmt->bindParam(':lng', $data['lng']);
        $stmt->bindParam(':special_notes', $data['special_notes']);
        $stmt->bindParam(':event_id', $data['event_id']);

        try {
            $stmt->execute();
            $user_id = $db->lastInsertId();

            http_response_code(201);
            echo json_encode(["message" => "User created successfully", "user_id" => $user_id]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error creating user: " . $e->getMessage()]);
        }
        break;

    case 'GET':
        // Get users
        $event_id = isset($_GET['event_id']) ? $_GET['event_id'] : 1;
        $willing = isset($_GET['willing_to_drive']) ? $_GET['willing_to_drive'] : null;

        $query = "SELECT u.* FROM users u WHERE u.event_id = :event_id";

        if ($willing !== null) {
            $query .= " AND u.willing_to_drive = :willing_to_drive";
        }

        $query .= " ORDER BY u.willing_to_drive DESC, u.name";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':event_id', $event_id);
        if ($willing !== null) {
            $stmt->bindParam(':willing_to_drive', $willing);
        }

        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get assignment info for each user
        foreach ($users as &$user) {
            // Check if user is assigned as driver
            if ($user['is_assigned_driver']) {
                $assignment_query = "SELECT ca.*,
                                    (SELECT GROUP_CONCAT(u2.name ORDER BY cp.pickup_order SEPARATOR ', ')
                                     FROM carpool_passengers cp
                                     JOIN users u2 ON cp.passenger_user_id = u2.id
                                     WHERE cp.assignment_id = ca.id) as passengers_list
                                    FROM carpool_assignments ca
                                    WHERE ca.driver_user_id = :user_id AND ca.is_active = TRUE";
                $assignment_stmt = $db->prepare($assignment_query);
                $assignment_stmt->bindParam(':user_id', $user['id']);
                $assignment_stmt->execute();
                $user['assignment'] = $assignment_stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // Check if user is assigned as passenger
                $passenger_query = "SELECT ca.*, u.name as driver_name
                                   FROM carpool_passengers cp
                                   JOIN carpool_assignments ca ON cp.assignment_id = ca.id
                                   JOIN users u ON ca.driver_user_id = u.id
                                   WHERE cp.passenger_user_id = :user_id AND ca.is_active = TRUE";
                $passenger_stmt = $db->prepare($passenger_query);
                $passenger_stmt->bindParam(':user_id', $user['id']);
                $passenger_stmt->execute();
                $user['passenger_assignment'] = $passenger_stmt->fetch(PDO::FETCH_ASSOC);
            }
        }

        echo json_encode($users);
        break;

    case 'PUT':
        // Update user
        if (empty($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["message" => "User ID required"]);
            exit();
        }

        $user_id = $_GET['id'];
        $update_fields = [];
        $params = [':id' => $user_id];

        $allowed_fields = ['name', 'email', 'phone', 'address', 'lat', 'lng',
                          'willing_to_drive', 'vehicle_capacity', 'vehicle_make',
                          'vehicle_model', 'vehicle_color', 'preferred_departure_time',
                          'special_notes'];

        foreach($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($update_fields)) {
            http_response_code(400);
            echo json_encode(["message" => "No fields to update"]);
            exit();
        }

        $query = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = :id";
        $stmt = $db->prepare($query);

        try {
            $stmt->execute($params);
            echo json_encode(["message" => "User updated successfully"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error updating user: " . $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Delete user
        if (empty($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["message" => "User ID required"]);
            exit();
        }

        $query = "DELETE FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_GET['id']);

        try {
            $stmt->execute();
            echo json_encode(["message" => "User deleted successfully"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error deleting user: " . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
        break;
}
?>