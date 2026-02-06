<?php
session_start();
$_SESSION['admin_id'] = 1; // Set admin session

// Set JSON headers
header('Content-Type: application/json');

// Enable error reporting to see issues
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, but log

include_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit();
    }

    // Test direct instantiation
    $event_id = 1;
    $target_vehicles = null;
    $max_drive_time = 50;

    // Include the optimizer class definition from optimize_enhanced.php
    class TestOptimizer {
        public function test() {
            // Simple test query
            global $db;
            $query = "SELECT COUNT(*) as count FROM users WHERE event_id = 1";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'message' => 'Test successful',
                'user_count' => $result['count']
            ];
        }
    }

    $tester = new TestOptimizer();
    $result = $tester->test();

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>