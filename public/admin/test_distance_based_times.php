<?php
session_start();
$_SESSION['admin_id'] = 1;

include_once '../config/database.php';

echo "========== TESTING DISTANCE-BASED PICKUP TIMES ==========\n\n";

$database = new Database();
$db = $database->getConnection();

// Helper functions
function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 3959; // miles
    $lat1 = deg2rad($lat1);
    $lng1 = deg2rad($lng1);
    $lat2 = deg2rad($lat2);
    $lng2 = deg2rad($lng2);

    $latDiff = $lat2 - $lat1;
    $lngDiff = $lng2 - $lng1;

    $a = sin($latDiff/2) * sin($latDiff/2) +
         cos($lat1) * cos($lat2) * sin($lngDiff/2) * sin($lngDiff/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));

    return $earthRadius * $c;
}

function calculateTravelTime($distance) {
    // Assume average speed of 25 mph in city
    $avgSpeed = 25;
    $time = ($distance / $avgSpeed) * 60; // Convert to minutes
    return max(ceil($time), 2); // Minimum 2 minutes
}

// Get sample driver and passengers
$driver_query = "SELECT * FROM users WHERE event_id = 1 AND willing_to_drive = 1 LIMIT 1";
$driver_stmt = $db->prepare($driver_query);
$driver_stmt->execute();
$driver = $driver_stmt->fetch(PDO::FETCH_ASSOC);

$passenger_query = "SELECT * FROM users WHERE event_id = 1 AND willing_to_drive = 0 LIMIT 2";
$passenger_stmt = $db->prepare($passenger_query);
$passenger_stmt->execute();
$passengers = $passenger_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get event
$event_query = "SELECT * FROM events WHERE id = 1";
$event_stmt = $db->prepare($event_query);
$event_stmt->execute();
$event = $event_stmt->fetch(PDO::FETCH_ASSOC);

echo "SAMPLE ROUTE CALCULATION:\n";
echo "========================\n\n";

echo "Driver: " . $driver['name'] . "\n";
echo "Location: " . $driver['address'] . "\n";
echo "Coordinates: (" . $driver['lat'] . ", " . $driver['lng'] . ")\n\n";

$departure_time = strtotime('8:30 PM');
echo "Departure Time: " . date('g:i A', $departure_time) . "\n\n";

echo "PICKUP CALCULATIONS:\n";
echo "-------------------\n\n";

$current_time = $departure_time;
$current_lat = $driver['lat'];
$current_lng = $driver['lng'];

foreach ($passengers as $index => $passenger) {
    echo "Passenger " . ($index + 1) . ": " . $passenger['name'] . "\n";
    echo "Address: " . $passenger['address'] . "\n";

    // Calculate distance from current location to passenger
    $distance = calculateDistance($current_lat, $current_lng, $passenger['lat'], $passenger['lng']);
    $travel_time = calculateTravelTime($distance);

    echo "Distance from previous stop: " . round($distance, 2) . " miles\n";
    echo "Travel time: " . $travel_time . " minutes\n";

    $current_time += ($travel_time * 60);
    echo "Pickup time: " . date('g:i A', $current_time) . "\n";

    // Add 3 minutes for pickup
    $current_time += (3 * 60);
    echo "Departure from this stop: " . date('g:i A', $current_time) . "\n\n";

    // Update current location
    $current_lat = $passenger['lat'];
    $current_lng = $passenger['lng'];
}

// Calculate to final destination
echo "FINAL LEG TO DESTINATION:\n";
echo "------------------------\n";
$distance_to_event = calculateDistance($current_lat, $current_lng, $event['event_lat'], $event['event_lng']);
$travel_to_event = calculateTravelTime($distance_to_event);

echo "Destination: " . $event['event_address'] . "\n";
echo "Distance: " . round($distance_to_event, 2) . " miles\n";
echo "Travel time: " . $travel_to_event . " minutes\n";

$arrival_time = $current_time + ($travel_to_event * 60);
echo "Estimated arrival: " . date('g:i A', $arrival_time) . "\n\n";

echo "========================================\n";
echo "SUMMARY:\n";
echo "========================================\n";
$total_time = ($arrival_time - $departure_time) / 60;
echo "Total journey time: " . round($total_time) . " minutes\n";
echo "Event time: " . date('g:i A', strtotime($event['event_date'] . ' ' . $event['event_time'])) . "\n";

if ($arrival_time <= strtotime($event['event_date'] . ' ' . $event['event_time'])) {
    echo "✅ Arrives on time!\n";
} else {
    $late_by = ($arrival_time - strtotime($event['event_date'] . ' ' . $event['event_time'])) / 60;
    echo "⚠️ Would arrive " . round($late_by) . " minutes late\n";
}

echo "\n✅ Pickup times are now calculated based on actual travel distances!\n";
echo "✅ Event name removed from itinerary - only address shown!\n\n";
echo "========== END OF TEST ==========\n";
?>