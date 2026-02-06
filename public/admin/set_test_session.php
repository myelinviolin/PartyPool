<?php
session_start();

// Set a test admin session
$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'admin';

// Also try to get event data to verify database connection
include_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

$result = [
    'session_set' => true,
    'session_id' => session_id(),
    'admin_id' => $_SESSION['admin_id'],
    'database_connected' => ($db !== null)
];

header('Content-Type: application/json');
echo json_encode($result);
?>