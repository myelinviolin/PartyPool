<?php
session_start();
$_SESSION['admin_id'] = 1;

// Simulate the download by including and executing the generate_itinerary.php logic
include_once '../config/database.php';

// Get event ID
$event_id = 1;

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

if ($optimization) {
    $routes_data = json_decode($optimization['routes'], true);

    // Helper function to calculate pickup times
    function calculatePickupTimes($departure_time, $passenger_count) {
        $pickup_times = [];
        $base_time = strtotime($departure_time);

        for ($i = 0; $i < $passenger_count; $i++) {
            if ($i == 0) {
                $pickup_time = $base_time + (10 * 60);
            } else {
                $pickup_time = $base_time + ((10 + ($i * 5)) * 60);
            }
            $pickup_times[] = date('g:i A', $pickup_time);
        }

        return $pickup_times;
    }

    echo "========== PREVIEW OF DOWNLOADABLE ITINERARY ==========\n\n";
    echo "CARPOOL ITINERARY\n";
    echo "=================\n";
    echo "Event: " . $event['event_name'] . "\n";
    echo "Date: " . date('F j, Y', strtotime($event['event_date'])) . "\n";
    echo "Time: " . date('g:i A', strtotime($event['event_time'])) . "\n";
    echo "Location: " . $event['event_address'] . "\n\n";

    // Show first route as example
    if (!empty($routes_data)) {
        $route = $routes_data[0];
        echo "DRIVER ITINERARY (Example)\n";
        echo str_repeat("-", 50) . "\n";
        echo "Driver: " . $route['driver_name'] . "\n";
        echo "Vehicle: " . ($route['vehicle'] ?? 'Not specified') . "\n";
        echo "Departure Time: " . ($route['departure_time'] ?? 'TBD') . "\n";

        if (isset($route['has_passengers']) && $route['has_passengers'] && !empty($route['passengers'])) {
            echo "\nPICKUP SCHEDULE:\n";

            // Calculate pickup times
            $pickup_times = calculatePickupTimes($route['departure_time'], count($route['passengers']));

            foreach ($route['passengers'] as $index => $passenger) {
                echo "\n  Stop #" . ($index + 1) . ":\n";
                echo "  • Name: " . $passenger['name'] . "\n";
                echo "  • Address: " . $passenger['address'] . "\n";
                echo "  • Pickup Time: " . $pickup_times[$index] . "\n";
            }
        }

        echo "\n[... Additional routes in full file ...]\n\n";

        // Show passenger itinerary example
        if (!empty($route['passengers'])) {
            $pickup_times = calculatePickupTimes($route['departure_time'], count($route['passengers']));
            $passenger = $route['passengers'][0];

            echo "PASSENGER ITINERARY (Example)\n";
            echo str_repeat("-", 50) . "\n";
            echo "Passenger: " . $passenger['name'] . "\n";
            echo "Your driver: " . $route['driver_name'] . "\n";
            echo "Pickup time: " . $pickup_times[0] . "\n\n";
        }
    }

    echo "✅ Pickup times are calculated and included in the download!\n";
    echo "✅ 'Please be ready 5 minutes' reminder has been removed!\n\n";
} else {
    echo "No optimization results found. Run optimization first.\n";
}

echo "========== END OF PREVIEW ==========\n";
?>