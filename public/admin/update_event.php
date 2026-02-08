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
$formatted_address = $data['event_address']; // Default to original input

if (!empty($location_data)) {
    $lat = $location_data[0]['lat'];
    $lng = $location_data[0]['lon'];
    // Use the display_name from Nominatim as the formatted address
    if (isset($location_data[0]['display_name'])) {
        $formatted_address = $location_data[0]['display_name'];

        // Simplify the address by removing country and excessive detail
        $parts = explode(', ', $formatted_address);

        // Remove "United States" or "USA" if present at the end
        if (count($parts) > 3) {
            $last = end($parts);
            if (strpos($last, 'United States') !== false || $last == 'USA') {
                array_pop($parts);
            }
        }

        // If address is still very long, try to keep only the most important parts
        if (count($parts) > 5) {
            // Try to keep: Name, Street Number, Street, City, State, Zip
            $important_parts = [];

            // Keep the first part (usually the place name)
            $important_parts[] = array_shift($parts);

            // Look for parts that look like addresses, city, state, or zip
            foreach ($parts as $part) {
                if (preg_match('/\d+/', $part) || // Has numbers (street address or zip)
                    preg_match('/Street|Avenue|Road|Boulevard|Drive|Lane|Way|Court|Place/i', $part) || // Street names
                    preg_match('/Madison|Wisconsin|WI|Dane County/i', $part)) { // Known local places
                    $important_parts[] = $part;
                }
            }

            // Rebuild the address
            if (count($important_parts) > 1) {
                $formatted_address = implode(', ', $important_parts);
            }
        } else {
            $formatted_address = implode(', ', $parts);
        }

        // Final cleanup - remove duplicate commas and extra spaces
        $formatted_address = preg_replace('/,\s*,/', ',', $formatted_address);
        $formatted_address = trim($formatted_address);
    }
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
$stmt->bindParam(':address', $formatted_address);
$stmt->bindParam(':date', $data['event_date']);
$stmt->bindParam(':time', $data['event_time']);
$stmt->bindParam(':lat', $lat);
$stmt->bindParam(':lng', $lng);
$stmt->bindParam(':id', $data['id']);

try {
    $stmt->execute();
    echo json_encode([
        'success' => true,
        'lat' => $lat,
        'lng' => $lng,
        'formatted_address' => $formatted_address
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}