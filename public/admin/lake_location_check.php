<?php
/**
 * Simplified lake location check for optimization
 * Returns 0 if all participants are at valid locations
 * Returns 1 if any participants are in water bodies
 */

// Include database connection
include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    exit(1);
}

// Madison area lakes and water bodies to check
$water_bodies = [
    'Lake Mendota' => [
        ['lat' => 43.10, 'lng' => -89.48],
        ['lat' => 43.15, 'lng' => -89.36]
    ],
    'Lake Monona' => [
        ['lat' => 43.05, 'lng' => -89.368],
        ['lat' => 43.09, 'lng' => -89.355]
    ],
    'Lake Waubesa' => [
        ['lat' => 42.99, 'lng' => -89.34],
        ['lat' => 43.02, 'lng' => -89.31]
    ]
];

// Check all participants
$query = "SELECT id, name, lat, lng FROM users WHERE event_id = 1 AND lat IS NOT NULL AND lng IS NOT NULL";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$errors = 0;

foreach ($users as $user) {
    $lat = floatval($user['lat']);
    $lng = floatval($user['lng']);

    // Check if in any water body
    foreach ($water_bodies as $water_name => $bounds) {
        if ($lat > $bounds[0]['lat'] && $lat < $bounds[1]['lat'] &&
            $lng > $bounds[0]['lng'] && $lng < $bounds[1]['lng']) {
            echo "ERROR: {$user['name']} appears to be in $water_name at ($lat, $lng)\n";
            $errors++;
        }
    }
}

if ($errors == 0) {
    echo "All participants are at valid locations.\n";
    exit(0);
} else {
    echo "$errors participant(s) in water bodies.\n";
    exit(1);
}
?>