<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include_once '../config/database.php';

// Get event ID from request
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 1;

// Initialize database
$database = new Database();
$db = $database->getConnection();

// Get event information
$event_query = "SELECT * FROM events WHERE id = :event_id";
$event_stmt = $db->prepare($event_query);
$event_stmt->bindParam(':event_id', $event_id);
$event_stmt->execute();
$event = $event_stmt->fetch(PDO::FETCH_ASSOC);

// Get the last optimization result
$opt_query = "SELECT * FROM optimization_results WHERE event_id = :event_id ORDER BY created_at DESC LIMIT 1";
$opt_stmt = $db->prepare($opt_query);
$opt_stmt->bindParam(':event_id', $event_id);
$opt_stmt->execute();
$optimization = $opt_stmt->fetch(PDO::FETCH_ASSOC);

if (!$optimization) {
    // If no saved optimization, get current assignments from users table
    $routes_data = generateRoutesFromCurrentAssignments($db, $event_id, $event);
} else {
    $routes_data = json_decode($optimization['routes'], true);
}

// Function to generate routes from current assignments
function generateRoutesFromCurrentAssignments($db, $event_id, $event) {
    $routes = [];

    // Get all drivers
    $driver_query = "SELECT * FROM users WHERE event_id = :event_id AND is_assigned_driver = TRUE ORDER BY name";
    $driver_stmt = $db->prepare($driver_query);
    $driver_stmt->bindParam(':event_id', $event_id);
    $driver_stmt->execute();
    $drivers = $driver_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($drivers as $driver) {
        // Get passengers for this driver
        $passenger_query = "SELECT * FROM users WHERE event_id = :event_id AND assigned_driver_id = :driver_id ORDER BY name";
        $passenger_stmt = $db->prepare($passenger_query);
        $passenger_stmt->bindParam(':event_id', $event_id);
        $passenger_stmt->bindParam(':driver_id', $driver['id']);
        $passenger_stmt->execute();
        $passengers = $passenger_stmt->fetchAll(PDO::FETCH_ASSOC);

        $route = [
            'driver_id' => $driver['id'],
            'driver_name' => $driver['name'],
            'driver_address' => $driver['address'],
            'driver_phone' => $driver['phone'] ?? 'Not provided',
            'vehicle' => ($driver['vehicle_make'] ?? 'Vehicle') . ' ' . ($driver['vehicle_model'] ?? ''),
            'capacity' => $driver['vehicle_capacity'],
            'passengers' => [],
            'departure_time' => calculateDepartureTime($driver, $passengers, $event),
            'has_passengers' => count($passengers) > 0
        ];

        // Calculate pickup times based on actual distances
        $pickup_times = calculatePickupTimesFromRoute($driver, $passengers, $route['departure_time']);

        foreach ($passengers as $index => $passenger) {
            $route['passengers'][] = [
                'id' => $passenger['id'],
                'name' => $passenger['name'],
                'address' => $passenger['address'],
                'phone' => $passenger['phone'] ?? 'Not provided',
                'lat' => $passenger['lat'] ?? null,
                'lng' => $passenger['lng'] ?? null,
                'pickup_time' => $pickup_times[$index]
            ];
        }

        $routes[] = $route;
    }

    return $routes;
}

// Helper function to calculate distance using Haversine formula
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

// Helper function to calculate travel time based on distance
function calculateTravelTime($distance) {
    // Assume average speed of 25 mph in city
    $avgSpeed = 25;
    $time = ($distance / $avgSpeed) * 60; // Convert to minutes
    return max(ceil($time), 2); // Minimum 2 minutes for very short distances
}

// Helper function to calculate departure time
function calculateDepartureTime($driver, $passengers, $event) {
    // Calculate total route time from driver -> passengers -> event
    $total_time = 0;
    $current_lat = $driver['lat'] ?? $event['event_lat'];
    $current_lng = $driver['lng'] ?? $event['event_lng'];

    // Time from driver to each passenger
    foreach ($passengers as $passenger) {
        if (isset($passenger['lat']) && isset($passenger['lng'])) {
            $distance = calculateDistance($current_lat, $current_lng, $passenger['lat'], $passenger['lng']);
            $total_time += calculateTravelTime($distance);
            $total_time += 3; // Add 3 minutes for pickup
            $current_lat = $passenger['lat'];
            $current_lng = $passenger['lng'];
        }
    }

    // Time from last passenger to event
    $distance = calculateDistance($current_lat, $current_lng, $event['event_lat'], $event['event_lng']);
    $total_time += calculateTravelTime($distance);

    // Calculate departure time
    $event_time = strtotime($event['event_date'] . ' ' . $event['event_time']);
    $departure_time = $event_time - ($total_time * 60);
    return date('g:i A', $departure_time);
}

// Helper function to calculate pickup times based on actual distances
function calculatePickupTimesFromRoute($driver, $passengers, $departure_time) {
    $pickup_times = [];
    $current_time = strtotime($departure_time);
    $current_lat = $driver['lat'] ?? 0;
    $current_lng = $driver['lng'] ?? 0;

    foreach ($passengers as $passenger) {
        if (isset($passenger['lat']) && isset($passenger['lng'])) {
            // Calculate travel time from current location to passenger
            $distance = calculateDistance($current_lat, $current_lng, $passenger['lat'], $passenger['lng']);
            $travel_time = calculateTravelTime($distance);

            // Add travel time to current time
            $current_time += ($travel_time * 60);
            $pickup_times[] = date('g:i A', $current_time);

            // Add 3 minutes for pickup and update current location
            $current_time += (3 * 60);
            $current_lat = $passenger['lat'];
            $current_lng = $passenger['lng'];
        } else {
            // Fallback if coordinates missing
            $current_time += (10 * 60);
            $pickup_times[] = date('g:i A', $current_time);
        }
    }

    return $pickup_times;
}

// Generate the itinerary content
$content = "CARPOOL ITINERARY\n";
$content .= "=================\n";
$content .= "Event: " . $event['event_name'] . "\n";
$content .= "Date: " . date('F j, Y', strtotime($event['event_date'])) . "\n";
$content .= "Time: " . date('g:i A', strtotime($event['event_time'])) . "\n";
$content .= "Location: " . $event['event_address'] . "\n";
$content .= "\n";
$content .= "Generated: " . date('F j, Y g:i A') . "\n";
$content .= str_repeat("=", 70) . "\n\n";

// Track all participants
$all_participants = [];

// Generate driver itineraries
foreach ($routes_data as $route) {
    $content .= "DRIVER ITINERARY\n";
    $content .= str_repeat("-", 50) . "\n";
    $content .= "Driver: " . $route['driver_name'] . "\n";
    if (isset($route['driver_phone'])) {
        $content .= "Phone: " . $route['driver_phone'] . "\n";
    }
    $content .= "Vehicle: " . ($route['vehicle'] ?? 'Not specified') . "\n";
    $content .= "Departure Time: " . ($route['departure_time'] ?? 'TBD') . "\n";

    if (isset($route['estimated_travel_time'])) {
        $content .= "Total Travel Time: " . $route['estimated_travel_time'] . "\n";
    }
    if (isset($route['overhead_time']) && $route['overhead_time'] != '0 minutes') {
        $content .= "Overhead Time: " . $route['overhead_time'] . "\n";
    }
    $content .= "\n";

    // Track driver
    $all_participants[$route['driver_id']] = [
        'name' => $route['driver_name'],
        'role' => 'driver',
        'departure_time' => $route['departure_time']
    ];

    if (isset($route['has_passengers']) && $route['has_passengers'] && !empty($route['passengers'])) {
        $content .= "PICKUP SCHEDULE:\n";

        // Build driver info for distance calculation
        $driver_info = [
            'lat' => null,
            'lng' => null
        ];

        // Try to get driver coordinates from users table
        if (isset($route['driver_id'])) {
            $driver_query = "SELECT lat, lng FROM users WHERE id = :driver_id";
            $driver_stmt = $db->prepare($driver_query);
            $driver_stmt->bindParam(':driver_id', $route['driver_id']);
            $driver_stmt->execute();
            $driver_data = $driver_stmt->fetch(PDO::FETCH_ASSOC);
            if ($driver_data) {
                $driver_info['lat'] = $driver_data['lat'];
                $driver_info['lng'] = $driver_data['lng'];
            }
        }

        // Calculate pickup times based on actual distances
        $pickup_times = calculatePickupTimesFromRoute($driver_info, $route['passengers'], $route['departure_time']);

        $stop_number = 1;
        foreach ($route['passengers'] as $index => $passenger) {
            $pickup_time = isset($pickup_times[$index]) ? $pickup_times[$index] : $route['departure_time'];

            $content .= "\n  Stop #" . $stop_number . ":\n";
            $content .= "  • Name: " . $passenger['name'] . "\n";
            $content .= "  • Address: " . $passenger['address'] . "\n";
            if (isset($passenger['phone'])) {
                $content .= "  • Phone: " . $passenger['phone'] . "\n";
            }
            $content .= "  • Pickup Time: " . $pickup_time . "\n";
            $stop_number++;

            // Track passenger with calculated pickup time
            $all_participants[$passenger['id']] = [
                'name' => $passenger['name'],
                'role' => 'passenger',
                'driver' => $route['driver_name'],
                'pickup_time' => $pickup_time
            ];
        }
        $content .= "\n  Final Stop:\n";
        $content .= "  • " . $event['event_address'] . "\n";
    } else {
        $content .= "DRIVING DIRECTLY TO:\n";
        $content .= "• " . $event['event_address'] . "\n";
        $content .= "(No passengers to pick up)\n";
    }

    $content .= "\n" . str_repeat("=", 70) . "\n\n";
}

// Generate passenger itineraries
$content .= "\n\nPASSENGER ITINERARIES\n";
$content .= str_repeat("=", 70) . "\n\n";

$passenger_count = 0;
foreach ($all_participants as $participant) {
    if ($participant['role'] === 'passenger') {
        $passenger_count++;
        $content .= "Passenger: " . $participant['name'] . "\n";
        $content .= str_repeat("-", 50) . "\n";
        $content .= "Your driver: " . $participant['driver'] . "\n";
        $content .= "Pickup time: " . $participant['pickup_time'] . "\n";
        $content .= "\n";
    }
}

if ($passenger_count === 0) {
    $content .= "No passengers assigned - all participants are driving.\n\n";
}

// Add summary
$content .= str_repeat("=", 70) . "\n";
$content .= "SUMMARY\n";
$content .= str_repeat("=", 70) . "\n";
$content .= "Total Drivers: " . count($routes_data) . "\n";
$content .= "Total Passengers: " . $passenger_count . "\n";
$content .= "Total Participants: " . count($all_participants) . "\n\n";

$content .= "IMPORTANT REMINDERS:\n";
$content .= "• Drivers: Please arrive at pickup locations on time\n";
$content .= "• Passengers: Please be ready 5 minutes before pickup\n";
$content .= "• Everyone: Bring any items needed for the event\n";
$content .= "• Exchange phone numbers with your carpool group\n";
$content .= "• Notify your driver if plans change\n\n";

$content .= "Thank you for carpooling!\n";
$content .= "Together we're reducing emissions and building community.\n";

// Set headers for file download
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="carpool_itinerary_' . date('Y-m-d') . '.txt"');
header('Content-Length: ' . strlen($content));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output the content
echo $content;
exit();
?>