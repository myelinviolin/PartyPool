<?php
session_start();
$_SESSION['admin_id'] = 1;

include_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get all users for event 1
$query = "SELECT id, name, willing_to_drive FROM users WHERE event_id = 1 ORDER BY id";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<!DOCTYPE html><html><head><title>User Names Check</title></head><body>";
echo "<h2>Users in Database:</h2>";
echo "<pre>";
foreach ($users as $user) {
    echo "ID: {$user['id']}, Name: \"{$user['name']}\", Can Drive: {$user['willing_to_drive']}\n";
}
echo "</pre>";
echo "</body></html>";
?>