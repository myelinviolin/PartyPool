<?php
session_start();
$_SESSION['admin_id'] = 1;

include_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

try {
    $query = "SELECT id, name, email, willing_to_drive, vehicle_capacity, vehicle_make, vehicle_model, vehicle_color
              FROM users
              WHERE event_id = 1
              ORDER BY willing_to_drive DESC, name";
    $stmt = $db->prepare($query);
    $stmt->execute();

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $drivers = array_filter($users, function($u) { return $u['willing_to_drive'] == 1; });
    $riders = array_filter($users, function($u) { return $u['willing_to_drive'] == 0; });

    echo json_encode([
        'success' => true,
        'total' => count($users),
        'drivers_count' => count($drivers),
        'riders_count' => count($riders),
        'drivers' => array_values($drivers),
        'riders' => array_values($riders)
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>