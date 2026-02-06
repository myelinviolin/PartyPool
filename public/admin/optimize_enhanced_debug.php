<?php
session_start();

// Log all errors to a file for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/optimize_debug.log');

// Start output buffering to catch any unwanted output
ob_start();

// Authorization check
if (!isset($_SESSION['admin_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    include_once '../config/database.php';

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Get input data
    $input = file_get_contents('php://input');
    error_log("Raw input: " . $input);

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    $event_id = $data['event_id'] ?? 1;
    $target_vehicles = isset($data['target_vehicles']) ? (int)$data['target_vehicles'] : null;
    $max_drive_time = isset($data['max_drive_time']) ? (int)$data['max_drive_time'] : 50;

    error_log("Parameters: event_id=$event_id, target_vehicles=$target_vehicles, max_drive_time=$max_drive_time");

    // Include the optimizer class
    require_once 'optimize_enhanced.php';

    // Clear any output that might have been generated
    ob_clean();

    // Create optimizer and run
    $optimizer = new EnhancedCarpoolOptimizer($db, $event_id, $target_vehicles, $max_drive_time);
    $result = $optimizer->optimize();

    // Ensure we have a valid result
    if (!is_array($result)) {
        throw new Exception('Optimizer returned invalid result');
    }

    // Clear buffer one more time and output JSON
    ob_clean();
    echo json_encode($result);

} catch (Exception $e) {
    // Log the error
    error_log("Optimization error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // Clean output buffer
    ob_clean();

    // Return error as JSON
    echo json_encode([
        'success' => false,
        'message' => 'Optimization error: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?>