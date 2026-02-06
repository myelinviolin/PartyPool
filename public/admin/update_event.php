<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);

// Geocode the address to get coordinates
$address = $data['event_address'];
$geocode_url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($address);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $geocode_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'PartyCarpool/1.0');
$geocode_result = curl_exec($ch);
curl_close($ch);

$location_data = json_decode($geocode_result, true);
$lat = null;
$lng = null;

if (!empty($location_data)) {
    $lat = $location_data[0]['lat'];
    $lng = $location_data[0]['lon'];
}

$query = "UPDATE events SET
          event_name = :name,
          event_address = :address,
          event_date = :date,
          event_time = :time,
          event_lat = :lat,
          event_lng = :lng
          WHERE id = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':name', $data['event_name']);
$stmt->bindParam(':address', $data['event_address']);
$stmt->bindParam(':date', $data['event_date']);
$stmt->bindParam(':time', $data['event_time']);
$stmt->bindParam(':lat', $lat);
$stmt->bindParam(':lng', $lng);
$stmt->bindParam(':id', $data['id']);

try {
    $stmt->execute();
    echo json_encode(['success' => true, 'lat' => $lat, 'lng' => $lng]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}