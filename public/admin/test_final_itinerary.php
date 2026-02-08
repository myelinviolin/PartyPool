<?php
session_start();
$_SESSION['admin_id'] = 1;

include_once '../config/database.php';
require_once 'optimize_enhanced.php';

echo "========== FINAL ITINERARY TEST ==========\n\n";

$database = new Database();
$db = $database->getConnection();

// Run optimization and save it
$optimizer = new EnhancedCarpoolOptimizer($db, 1, null, 50);
$result = $optimizer->optimize();

if (!$result['success']) {
    echo "Optimization failed: " . $result['message'] . "\n";
    exit(1);
}

// Save optimization result
$save_query = "INSERT INTO optimization_results (event_id, routes, vehicles_used, created_at)
               VALUES (1, :routes, :vehicles_used, NOW())
               ON DUPLICATE KEY UPDATE routes = VALUES(routes), vehicles_used = VALUES(vehicles_used), created_at = NOW()";
$save_stmt = $db->prepare($save_query);
$routes_json = json_encode($result['routes']);
$save_stmt->bindParam(':routes', $routes_json);
$vehicles_used = count($result['routes']);
$save_stmt->bindParam(':vehicles_used', $vehicles_used);
$save_stmt->execute();

// Now include the generate_itinerary logic to show output
$event_id = 1;

// Get event information
$event_query = "SELECT * FROM events WHERE id = :event_id";
$event_stmt = $db->prepare($event_query);
$event_stmt->bindParam(':event_id', $event_id);
$event_stmt->execute();
$event = $event_stmt->fetch(PDO::FETCH_ASSOC);

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
    $avgSpeed = 25;
    $time = ($distance / $avgSpeed) * 60;
    return max(ceil($time), 2);
}

function calculatePickupTimesFromRoute($driver, $passengers, $departure_time) {
    $pickup_times = [];
    $current_time = strtotime($departure_time);
    $current_lat = $driver['lat'] ?? 0;
    $current_lng = $driver['lng'] ?? 0;

    foreach ($passengers as $passenger) {
        if (isset($passenger['lat']) && isset($passenger['lng'])) {
            $distance = calculateDistance($current_lat, $current_lng, $passenger['lat'], $passenger['lng']);
            $travel_time = calculateTravelTime($distance);
            $current_time += ($travel_time * 60);
            $pickup_times[] = date('g:i A', $current_time);
            $current_time += (3 * 60); // 3 minutes for pickup
            $current_lat = $passenger['lat'];
            $current_lng = $passenger['lng'];
        } else {
            $current_time += (10 * 60);
            $pickup_times[] = date('g:i A', $current_time);
        }
    }

    return $pickup_times;
}

// Show sample itinerary output
echo "CARPOOL ITINERARY\n";
echo "=================\n";
echo "Event: " . $event['event_name'] . "\n";
echo "Date: " . date('F j, Y', strtotime($event['event_date'])) . "\n";
echo "Time: " . date('g:i A', strtotime($event['event_time'])) . "\n";
echo "Location: " . $event['event_address'] . "\n\n";

// Show first route with passengers
$route_with_passengers = null;
foreach ($result['routes'] as $route) {
    if (!empty($route['passengers'])) {
        $route_with_passengers = $route;
        break;
    }
}

if ($route_with_passengers) {
    echo "EXAMPLE DRIVER ITINERARY (With Passengers):\n";
    echo str_repeat("-", 50) . "\n";
    echo "Driver: " . $route_with_passengers['driver_name'] . "\n";
    echo "Vehicle: " . ($route_with_passengers['vehicle'] ?? 'Not specified') . "\n";
    echo "Departure Time: " . ($route_with_passengers['departure_time'] ?? 'TBD') . "\n\n";

    // Get driver coordinates
    $driver_query = "SELECT lat, lng FROM users WHERE id = :driver_id";
    $driver_stmt = $db->prepare($driver_query);
    $driver_stmt->bindParam(':driver_id', $route_with_passengers['driver_id']);
    $driver_stmt->execute();
    $driver_data = $driver_stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate pickup times
    $pickup_times = calculatePickupTimesFromRoute($driver_data, $route_with_passengers['passengers'], $route_with_passengers['departure_time']);

    echo "PICKUP SCHEDULE:\n";
    foreach ($route_with_passengers['passengers'] as $index => $passenger) {
        echo "\n  Stop #" . ($index + 1) . ":\n";
        echo "  • Name: " . $passenger['name'] . "\n";
        echo "  • Address: " . $passenger['address'] . "\n";
        echo "  • Pickup Time: " . $pickup_times[$index] . " (based on " .
             round(calculateDistance(
                 $index === 0 ? $driver_data['lat'] : $route_with_passengers['passengers'][$index-1]['lat'],
                 $index === 0 ? $driver_data['lng'] : $route_with_passengers['passengers'][$index-1]['lng'],
                 $passenger['lat'],
                 $passenger['lng']
             ), 1) . " miles)\n";
    }

    echo "\n  Final Stop:\n";
    echo "  • " . $event['event_address'] . " ← ADDRESS ONLY (no event name)\n";
}

// Show route without passengers
$solo_route = null;
foreach ($result['routes'] as $route) {
    if (empty($route['passengers'])) {
        $solo_route = $route;
        break;
    }
}

if ($solo_route) {
    echo "\n\nEXAMPLE DRIVER ITINERARY (Solo):\n";
    echo str_repeat("-", 50) . "\n";
    echo "Driver: " . $solo_route['driver_name'] . "\n";
    echo "Vehicle: " . ($solo_route['vehicle'] ?? 'Not specified') . "\n";
    echo "Departure Time: " . ($solo_route['departure_time'] ?? 'TBD') . "\n\n";
    echo "DRIVING DIRECTLY TO:\n";
    echo "• " . $event['event_address'] . " ← ADDRESS ONLY\n";
    echo "(No passengers to pick up)\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "KEY IMPROVEMENTS:\n";
echo "• Pickup times calculated based on ACTUAL DISTANCES\n";
echo "• Travel times use 25 mph average city speed\n";
echo "• 3-minute stop time added for each pickup\n";
echo "• Event name removed - only address shown\n";
echo "• More accurate arrival predictions\n\n";
echo "========== END OF TEST ==========\n";
?>