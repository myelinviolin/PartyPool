<?php
include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get latest optimization
$query = "SELECT routes FROM optimization_results WHERE event_id = 1 ORDER BY created_at DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$routes = json_decode($result['routes'], true);

// Get user locations
$query = "SELECT id, name, lat, lng FROM users WHERE event_id = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
$user_coords = [];
foreach ($users as $user) {
    $user_coords[$user['id']] = [
        'name' => $user['name'],
        'lat' => $user['lat'],
        'lng' => $user['lng']
    ];
}

echo "=== ROUTE LINE VS ICON PLACEMENT CHECK ===\n\n";

$mismatches = 0;

foreach ($routes as $route) {
    echo "Route for Driver: " . $route['driver_name'] . "\n";

    // Check driver coordinates
    $driver_id = $route['driver_id'];
    $route_start = $route['coordinates'][0];
    $db_coords = $user_coords[$driver_id];

    $lat_diff = abs($route_start[0] - $db_coords['lat']);
    $lng_diff = abs($route_start[1] - $db_coords['lng']);

    if ($lat_diff > 0.0001 || $lng_diff > 0.0001) {
        echo "  ⚠️ MISMATCH for driver " . $db_coords['name'] . ":\n";
        echo "    Route start: [" . $route_start[0] . ", " . $route_start[1] . "]\n";
        echo "    DB coords:   [" . $db_coords['lat'] . ", " . $db_coords['lng'] . "]\n";
        $mismatches++;
    } else {
        echo "  ✓ Driver coordinates match\n";
    }

    // Check passenger pickup points
    if (!empty($route['passengers'])) {
        foreach ($route['passengers'] as $i => $pax) {
            $pax_id = $pax['id'];
            $route_coord = $route['coordinates'][$i + 1]; // +1 because driver is first
            $db_coords = $user_coords[$pax_id];

            $lat_diff = abs($route_coord[0] - $db_coords['lat']);
            $lng_diff = abs($route_coord[1] - $db_coords['lng']);

            if ($lat_diff > 0.0001 || $lng_diff > 0.0001) {
                echo "  ⚠️ MISMATCH for passenger " . $db_coords['name'] . ":\n";
                echo "    Route point: [" . $route_coord[0] . ", " . $route_coord[1] . "]\n";
                echo "    DB coords:   [" . $db_coords['lat'] . ", " . $db_coords['lng'] . "]\n";
                $mismatches++;
            } else {
                echo "  ✓ Passenger " . $pax['name'] . " coordinates match\n";
            }
        }
    } else {
        echo "  (Solo driver - no passengers)\n";
    }
    echo "\n";
}

echo "=== SUMMARY ===\n";
if ($mismatches == 0) {
    echo "✅ All route lines perfectly align with icon placements!\n";
} else {
    echo "⚠️ Found $mismatches coordinate mismatches that need fixing.\n";
}
?>