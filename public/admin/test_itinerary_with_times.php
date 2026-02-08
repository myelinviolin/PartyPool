<?php
session_start();
$_SESSION['admin_id'] = 1;

include_once '../config/database.php';
require_once 'optimize_enhanced.php';

echo "========== TESTING ITINERARY WITH PICKUP TIMES ==========\n\n";

$database = new Database();
$db = $database->getConnection();

// Step 1: Run optimization
echo "Step 1: Running optimization...\n";
$optimizer = new EnhancedCarpoolOptimizer($db, 1, null, 50);
$result = $optimizer->optimize();

if (!$result['success']) {
    echo "✗ Optimization failed: " . $result['message'] . "\n";
    exit(1);
}

echo "✓ Optimization successful\n";
echo "  - Routes created: " . count($result['routes']) . "\n\n";

// Step 2: Display sample itinerary with calculated pickup times
echo "Step 2: Sample itinerary with calculated pickup times\n";
echo "======================================================\n\n";

// Get event info
$event_query = "SELECT * FROM events WHERE id = 1";
$event_stmt = $db->prepare($event_query);
$event_stmt->execute();
$event = $event_stmt->fetch(PDO::FETCH_ASSOC);

// Helper function to calculate pickup times
function calculatePickupTimes($departure_time, $passenger_count) {
    $pickup_times = [];
    $base_time = strtotime($departure_time);

    for ($i = 0; $i < $passenger_count; $i++) {
        if ($i == 0) {
            // First passenger - 10 minutes after departure
            $pickup_time = $base_time + (10 * 60);
        } else {
            // Subsequent passengers - 5 minutes after previous pickup
            $pickup_time = $base_time + ((10 + ($i * 5)) * 60);
        }
        $pickup_times[] = date('g:i A', $pickup_time);
    }

    return $pickup_times;
}

// Show first 3 routes as examples
$count = 0;
$all_participants = [];

foreach ($result['routes'] as $route) {
    if ($count >= 3) {
        echo "\n[... Additional routes omitted for brevity ...]\n";
        break;
    }

    echo "DRIVER ITINERARY\n";
    echo str_repeat("-", 50) . "\n";
    echo "Driver: " . $route['driver_name'] . "\n";
    echo "Vehicle: " . ($route['vehicle'] ?? 'Not specified') . "\n";
    echo "Departure Time: " . ($route['departure_time'] ?? 'TBD') . "\n";

    if (isset($route['estimated_travel_time'])) {
        echo "Total Travel Time: " . $route['estimated_travel_time'] . "\n";
    }

    // Track driver
    $all_participants[$route['driver_id']] = [
        'name' => $route['driver_name'],
        'role' => 'driver',
        'departure_time' => $route['departure_time']
    ];

    if (isset($route['has_passengers']) && $route['has_passengers'] && !empty($route['passengers'])) {
        echo "\nPICKUP SCHEDULE:\n";

        // Calculate pickup times
        $pickup_times = calculatePickupTimes($route['departure_time'], count($route['passengers']));

        $stop_number = 1;
        foreach ($route['passengers'] as $index => $passenger) {
            $pickup_time = $pickup_times[$index];

            echo "  Stop #" . $stop_number . ":\n";
            echo "  • Name: " . $passenger['name'] . "\n";
            echo "  • Address: " . ($passenger['address'] ?? 'TBD') . "\n";
            echo "  • Pickup Time: " . $pickup_time . "\n\n";

            // Track passenger
            $all_participants[$passenger['id']] = [
                'name' => $passenger['name'],
                'role' => 'passenger',
                'driver' => $route['driver_name'],
                'pickup_time' => $pickup_time
            ];

            $stop_number++;
        }

        echo "  Final Stop: " . $event['event_name'] . "\n";
        echo "  • Location: " . $event['event_address'] . "\n";
    } else {
        echo "\nDRIVING DIRECTLY TO EVENT (No passengers)\n";
    }

    echo "\n" . str_repeat("=", 70) . "\n\n";
    $count++;
}

// Show passenger itineraries
echo "PASSENGER ITINERARIES\n";
echo str_repeat("=", 70) . "\n\n";

$passenger_count = 0;
foreach ($all_participants as $participant) {
    if ($participant['role'] === 'passenger') {
        $passenger_count++;
        echo "Passenger: " . $participant['name'] . "\n";
        echo str_repeat("-", 50) . "\n";
        echo "Your driver: " . $participant['driver'] . "\n";
        echo "Pickup time: " . $participant['pickup_time'] . "\n\n";

        if ($passenger_count >= 3) {
            echo "[... Additional passengers omitted for brevity ...]\n\n";
            break;
        }
    }
}

echo str_repeat("=", 70) . "\n";
echo "✓ Pickup times calculated successfully!\n\n";
echo "Notes:\n";
echo "  • First passenger picked up 10 minutes after driver departs\n";
echo "  • Subsequent passengers picked up 5 minutes after previous stop\n";
echo "  • Times automatically adjust based on departure time\n\n";
echo "========== END OF TEST ==========\n";
?>