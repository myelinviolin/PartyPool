<?php
// Test version without auth requirement for debugging
session_start();

// Set a temporary admin session for testing
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = 1;
}

// Prevent any output before JSON
ob_start();

// Set JSON header
header('Content-Type: application/json');

// Suppress warnings
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

try {
    include_once '../config/database.php';

    // Test database connection first
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Get input
    $input = file_get_contents('php://input');
    if (empty($input)) {
        $input = '{"event_id":1,"target_vehicles":null,"max_drive_time":50}';
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    // Simple test: just count users and drivers
    $event_id = $data['event_id'] ?? 1;

    // Get user counts
    $query = "SELECT
                COUNT(*) as total_users,
                SUM(CASE WHEN willing_to_drive = 1 THEN 1 ELSE 0 END) as drivers,
                SUM(CASE WHEN willing_to_drive = 0 THEN 1 ELSE 0 END) as riders
              FROM users
              WHERE event_id = :event_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':event_id', $event_id);
    $stmt->execute();
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    // Return simple result
    $result = [
        'success' => true,
        'message' => 'Test optimization successful',
        'event_id' => $event_id,
        'total_participants' => $counts['total_users'],
        'drivers' => $counts['drivers'],
        'riders' => $counts['riders'],
        'vehicles_needed' => ceil($counts['total_users'] / 4), // Simple estimate
        'routes' => [] // Empty for now
    ];

    // Clean buffer and output
    ob_clean();
    echo json_encode($result);

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

ob_end_flush();
?>