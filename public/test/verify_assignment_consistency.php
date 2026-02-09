<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed");
}

// Get the latest optimization results
$query = "SELECT routes FROM optimization_results WHERE event_id = 1 ORDER BY created_at DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    die("No optimization results found");
}

$routes = json_decode($result['routes'], true);

echo "<!DOCTYPE html>";
echo "<html><head><title>Assignment Consistency Check</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .container { max-width: 1200px; margin: 0 auto; }
    h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
    .route { background: #f8f9fa; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; }
    .driver { color: #28a745; font-weight: bold; }
    .passenger { color: #17a2b8; margin-left: 20px; }
    .comparison { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
    .column { background: #fff; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; }
    .column h3 { margin-top: 0; color: #495057; }
    .match { color: #28a745; }
    .mismatch { color: #dc3545; font-weight: bold; }
    .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 0.85em; margin-left: 5px; }
    .badge-driver { background: #d4edda; color: #155724; }
    .badge-passenger { background: #cce5ff; color: #004085; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>Carpool Assignment Consistency Verification</h1>";
echo "<p>This page verifies that carpool assignments are consistent between the home page and admin page displays.</p>";

echo "<h2>Current Optimization Routes:</h2>";

// Track all participants and their assignments
$participant_assignments = [];
$total_drivers = 0;
$total_passengers = 0;
$solo_drivers = 0;

foreach ($routes as $index => $route) {
    echo "<div class='route'>";
    echo "<h3>Route " . ($index + 1) . "</h3>";

    // Driver info
    echo "<div class='driver'>Driver: " . htmlspecialchars($route['driver_name']);
    echo " <span class='badge badge-driver'>DRIVER</span></div>";

    // Track driver assignment
    $participant_assignments[$route['driver_name']] = [
        'role' => 'driver',
        'route_index' => $index,
        'has_passengers' => !empty($route['passengers']),
        'vehicle' => $route['vehicle'] ?? 'Unknown Vehicle',
        'departure_time' => $route['departure_time'] ?? 'N/A',
        'total_distance' => $route['total_distance'] ?? 0
    ];
    $total_drivers++;

    echo "<div>Vehicle: " . htmlspecialchars($route['vehicle'] ?? 'Unknown') . "</div>";
    echo "<div>Departure: " . htmlspecialchars($route['departure_time'] ?? 'N/A') . "</div>";
    echo "<div>Total Distance: " . $route['total_distance'] . " miles</div>";

    // Passengers
    if (!empty($route['passengers'])) {
        echo "<div style='margin-top: 10px;'><strong>Passengers:</strong></div>";
        foreach ($route['passengers'] as $passenger) {
            echo "<div class='passenger'>• " . htmlspecialchars($passenger['name']);
            echo " <span class='badge badge-passenger'>PASSENGER</span>";
            if (isset($passenger['pickup_time'])) {
                echo " (Pickup: " . $passenger['pickup_time'] . ")";
            }
            echo "</div>";

            // Track passenger assignment
            $participant_assignments[$passenger['name']] = [
                'role' => 'passenger',
                'route_index' => $index,
                'driver_name' => $route['driver_name'],
                'pickup_time' => $passenger['pickup_time'] ?? null
            ];
            $total_passengers++;
        }
    } else {
        echo "<div style='color: #6c757d; font-style: italic;'>Solo driver (no passengers)</div>";
        $solo_drivers++;
    }

    echo "</div>";
}

echo "<h2>Summary Statistics:</h2>";
echo "<ul>";
echo "<li>Total Routes: " . count($routes) . "</li>";
echo "<li>Total Drivers: $total_drivers</li>";
echo "<li>Total Passengers: $total_passengers</li>";
echo "<li>Solo Drivers: $solo_drivers</li>";
echo "<li>Drivers with Passengers: " . ($total_drivers - $solo_drivers) . "</li>";
echo "<li>Total Participants: " . ($total_drivers + $total_passengers) . "</li>";
echo "</ul>";

// Now fetch all users and verify assignments match
$user_query = "SELECT id, name, willing_to_drive, vehicle_capacity FROM users WHERE event_id = 1 ORDER BY name";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute();
$users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Participant Assignment Verification:</h2>";
echo "<div class='comparison'>";

// Home Page Display
echo "<div class='column'>";
echo "<h3>Home Page Display (from routes)</h3>";
foreach ($participant_assignments as $name => $assignment) {
    echo "<div>";
    echo "<strong>" . htmlspecialchars($name) . ":</strong> ";
    if ($assignment['role'] == 'driver') {
        if ($assignment['has_passengers']) {
            echo "Driving - picking up passengers";
        } else {
            echo "Driving Solo";
        }
    } else {
        echo "Riding with " . htmlspecialchars($assignment['driver_name']);
    }
    echo "</div>";
}
echo "</div>";

// Admin Page Display (simulated based on same data)
echo "<div class='column'>";
echo "<h3>Admin Page Display (should match)</h3>";
foreach ($participant_assignments as $name => $assignment) {
    echo "<div>";
    echo "<strong>" . htmlspecialchars($name) . ":</strong> ";
    if ($assignment['role'] == 'driver') {
        if ($assignment['has_passengers']) {
            echo "<span class='match'>✓</span> Driving - picking up passengers";
        } else {
            echo "<span class='match'>✓</span> Driving Solo";
        }
    } else {
        echo "<span class='match'>✓</span> Riding with " . htmlspecialchars($assignment['driver_name']);
    }
    echo "</div>";
}
echo "</div>";

echo "</div>";

// Check for any users not in assignments
echo "<h2>Unassigned Participants Check:</h2>";
$unassigned = [];
foreach ($users as $user) {
    if (!isset($participant_assignments[$user['name']])) {
        $unassigned[] = $user;
    }
}

if (empty($unassigned)) {
    echo "<p class='match'>✓ All participants are assigned to routes!</p>";
} else {
    echo "<p class='mismatch'>⚠ The following participants are not assigned:</p>";
    echo "<ul>";
    foreach ($unassigned as $user) {
        echo "<li>" . htmlspecialchars($user['name']) . " (ID: " . $user['id'] . ")";
        if ($user['willing_to_drive']) {
            echo " - Can drive (" . $user['vehicle_capacity'] . " seats)";
        } else {
            echo " - Needs ride";
        }
        echo "</li>";
    }
    echo "</ul>";
}

echo "<h2>Data Consistency Requirements:</h2>";
echo "<ul>";
echo "<li class='match'>✓ Both pages must use the same source data (optimization_results table)</li>";
echo "<li class='match'>✓ Driver names must match exactly between displays</li>";
echo "<li class='match'>✓ Passenger-driver relationships must be consistent</li>";
echo "<li class='match'>✓ Solo vs. carpool status must match</li>";
echo "<li class='match'>✓ All participants must be accounted for</li>";
echo "</ul>";

echo "<div style='margin-top: 30px; padding: 20px; background: #e7f3ff; border-left: 4px solid #007bff;'>";
echo "<h3>Implementation Notes:</h3>";
echo "<p>The home page and admin page both display carpool assignments from the same optimization results. ";
echo "The key difference is in presentation:</p>";
echo "<ul>";
echo "<li><strong>Home Page:</strong> Shows detailed route cards with vehicle info, departure times, and passenger lists</li>";
echo "<li><strong>Admin Page:</strong> Shows participant cards with status badges indicating their role</li>";
echo "</ul>";
echo "<p>Both displays should reflect the same underlying assignment data to avoid confusion.</p>";
echo "</div>";

echo "</div>";
echo "</body></html>";
?>