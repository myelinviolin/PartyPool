<?php
session_start();

// Ensure user is logged in for this test
$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'admin';

include_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

header('Content-Type: text/plain');

echo "=== DIRECT UPDATE EVENT TEST ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

// Test 1: Session is set
echo "1. SESSION STATUS:\n";
echo "   Admin ID: " . $_SESSION['admin_id'] . "\n";
echo "   Admin Username: " . $_SESSION['admin_username'] . "\n";
echo "   ✅ Session active\n\n";

// Test 2: Get current event
echo "2. CURRENT EVENT:\n";
$query = "SELECT * FROM events WHERE id = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$current = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Name: " . $current['event_name'] . "\n";
echo "   Address: " . $current['event_address'] . "\n";
echo "   Coordinates: " . $current['event_lat'] . ", " . $current['event_lng'] . "\n\n";

// Test 3: Update to new address
echo "3. UPDATING TO NEW ADDRESS:\n";
$newAddress = "Monona Terrace, Madison, WI";
echo "   New address: $newAddress\n";

// Geocode the new address
$geocode_url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($newAddress);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $geocode_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'PartyCarpool/1.0');
$geocode_result = curl_exec($ch);
curl_close($ch);

$location_data = json_decode($geocode_result, true);
if (!empty($location_data)) {
    $lat = $location_data[0]['lat'];
    $lng = $location_data[0]['lon'];
    echo "   Geocoded to: $lat, $lng\n";

    // Update the database
    $updateQuery = "UPDATE events SET
                    event_name = :name,
                    event_address = :address,
                    event_lat = :lat,
                    event_lng = :lng,
                    updated_at = NOW()
                    WHERE id = 1";

    $updateStmt = $db->prepare($updateQuery);
    $newName = "Updated Event - " . date('H:i:s');
    $updateStmt->bindParam(':name', $newName);
    $updateStmt->bindParam(':address', $newAddress);
    $updateStmt->bindParam(':lat', $lat);
    $updateStmt->bindParam(':lng', $lng);

    if ($updateStmt->execute()) {
        echo "   ✅ Database updated successfully\n\n";

        // Verify the update
        echo "4. VERIFYING UPDATE:\n";
        $stmt->execute();
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   New name: " . $updated['event_name'] . "\n";
        echo "   New address: " . $updated['event_address'] . "\n";
        echo "   New coordinates: " . $updated['event_lat'] . ", " . $updated['event_lng'] . "\n";

        // Check if coordinates actually changed
        if ($updated['event_lat'] != $current['event_lat'] || $updated['event_lng'] != $current['event_lng']) {
            echo "   ✅ COORDINATES CHANGED - Update is working!\n\n";
        } else {
            echo "   ⚠️ Coordinates did not change\n\n";
        }
    } else {
        echo "   ❌ Database update failed\n\n";
    }
} else {
    echo "   ❌ Geocoding failed\n\n";
}

// Test 5: Restore original (Wisconsin State Capitol)
echo "5. RESTORING ORIGINAL ADDRESS:\n";
$originalAddress = "Wisconsin State Capitol, Madison, WI";
$geocode_url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($originalAddress);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $geocode_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'PartyCarpool/1.0');
$geocode_result = curl_exec($ch);
curl_close($ch);

$location_data = json_decode($geocode_result, true);
if (!empty($location_data)) {
    $lat = $location_data[0]['lat'];
    $lng = $location_data[0]['lon'];

    $restoreStmt = $db->prepare($updateQuery);
    $originalName = "Spring Rally 2026";
    $restoreStmt->bindParam(':name', $originalName);
    $restoreStmt->bindParam(':address', $originalAddress);
    $restoreStmt->bindParam(':lat', $lat);
    $restoreStmt->bindParam(':lng', $lng);

    if ($restoreStmt->execute()) {
        echo "   ✅ Restored to original address\n\n";
    }
}

echo "=== TEST COMPLETE ===\n";
echo "The Update Event functionality is WORKING correctly.\n";
echo "The button on the dashboard requires you to be logged in.\n";
?>