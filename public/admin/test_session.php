<?php
session_start();

// Set header
header('Content-Type: application/json');

// Test 1: Check session
$session_status = [
    'session_id' => session_id(),
    'admin_id' => $_SESSION['admin_id'] ?? null,
    'is_logged_in' => isset($_SESSION['admin_id'])
];

// Test 2: Check database
$db_status = ['connected' => false];
try {
    include_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    if ($db) {
        $db_status['connected'] = true;

        // Count users
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE event_id = 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $db_status['user_count'] = $result['count'];
    }
} catch (Exception $e) {
    $db_status['error'] = $e->getMessage();
}

// Output result
echo json_encode([
    'success' => true,
    'session' => $session_status,
    'database' => $db_status,
    'php_version' => phpversion(),
    'timestamp' => date('Y-m-d H:i:s')
]);
?>