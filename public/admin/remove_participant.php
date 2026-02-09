<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');

// Include database
include_once '../config/database.php';

// Get posted data
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['user_id']) || !isset($data['event_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$user_id = $data['user_id'];
$event_id = $data['event_id'];

try {
    $database = new Database();
    $db = $database->getConnection();

    // Start transaction
    $db->beginTransaction();

    // First, remove the user from any carpool passenger assignments
    $delete_passenger_query = "DELETE cp FROM carpool_passengers cp
                               INNER JOIN carpool_assignments ca ON cp.assignment_id = ca.id
                               WHERE cp.passenger_user_id = :user_id
                               AND ca.event_id = :event_id";
    $stmt = $db->prepare($delete_passenger_query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':event_id', $event_id);
    $stmt->execute();

    // Remove any assignments where this user is the driver
    $delete_assignment_query = "DELETE FROM carpool_assignments
                                WHERE driver_user_id = :user_id
                                AND event_id = :event_id";
    $stmt = $db->prepare($delete_assignment_query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':event_id', $event_id);
    $stmt->execute();

    // Finally, delete the user from the users table
    $delete_user_query = "DELETE FROM users
                         WHERE id = :user_id
                         AND event_id = :event_id";
    $stmt = $db->prepare($delete_user_query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':event_id', $event_id);
    $stmt->execute();

    // Check if user was actually deleted
    if ($stmt->rowCount() > 0) {
        // Clear any existing optimization results since they're now invalid
        $clear_optimization_query = "DELETE FROM optimization_results WHERE event_id = :event_id";
        $stmt = $db->prepare($clear_optimization_query);
        $stmt->bindParam(':event_id', $event_id);
        $stmt->execute();

        // Update event optimization status
        $update_event_query = "UPDATE events SET optimization_status = 'pending' WHERE id = :event_id";
        $stmt = $db->prepare($update_event_query);
        $stmt->bindParam(':event_id', $event_id);
        $stmt->execute();

        // Commit transaction
        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Participant removed successfully'
        ]);
    } else {
        // Rollback if no user was deleted
        $db->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Participant not found'
        ]);
    }
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db)) {
        $db->rollback();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>