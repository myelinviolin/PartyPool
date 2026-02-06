<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get current event coordinates
$query = "SELECT * FROM events WHERE id = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

$expectedLat = 43.0747;
$expectedLng = -89.3985;
$actualLat = floatval($event['event_lat']);
$actualLng = floatval($event['event_lng']);

$latDiff = abs($actualLat - $expectedLat);
$lngDiff = abs($actualLng - $expectedLng);

$positionCorrect = ($latDiff < 0.0001 && $lngDiff < 0.0001);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marker Position Verification</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Marker Position Verification Test</h2>

        <div class="alert <?php echo $positionCorrect ? 'alert-success' : 'alert-danger'; ?> mb-3">
            <h4>Database Coordinates Test</h4>
            <p><strong>Expected:</strong> State Street (<?php echo $expectedLat; ?>, <?php echo $expectedLng; ?>)</p>
            <p><strong>Actual:</strong> <?php echo $actualLat; ?>, <?php echo $actualLng; ?></p>
            <p><strong>Difference:</strong> <?php echo number_format($latDiff, 6); ?> lat, <?php echo number_format($lngDiff, 6); ?> lng</p>
            <p><strong>Result:</strong> <?php echo $positionCorrect ? '✅ PASS - Coordinates match State Street' : '❌ FAIL - Coordinates do not match'; ?></p>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Test Map with Event Marker</div>
                    <div class="card-body">
                        <div id="testMap" style="height: 400px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Live App Map (iframe)</div>
                    <div class="card-body">
                        <iframe src="../index.html" style="width: 100%; height: 400px; border: none;"></iframe>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">Visual Verification</div>
            <div class="card-body">
                <p>The test map shows:</p>
                <ul>
                    <li>🟢 Green circle: Exact State Street location</li>
                    <li>⭐ Gold star: Event marker from database</li>
                    <li>📍 Blue marker: Default Leaflet marker for reference</li>
                </ul>
                <p id="visualResult"></p>
            </div>
        </div>

        <a href="dashboard.php" class="btn btn-primary mt-3">Back to Dashboard</a>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize test map
        const testMap = L.map('testMap').setView([43.0747, -89.3985], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(testMap);

        // Add reference circle at exact State Street location
        L.circle([43.0747, -89.3985], {
            color: 'green',
            fillColor: '#0f3',
            fillOpacity: 0.3,
            radius: 30
        }).addTo(testMap).bindPopup('Exact State Street Location');

        // Add default Leaflet marker for reference
        L.marker([43.0747, -89.3985]).addTo(testMap)
            .bindPopup('Reference: Default Leaflet Marker');

        // Add event marker from database using same code as app.js
        const eventLat = <?php echo $event['event_lat']; ?>;
        const eventLng = <?php echo $event['event_lng']; ?>;

        const eventMarker = L.marker([eventLat, eventLng], {
            icon: L.divIcon({
                html: '<i class="fas fa-star"></i>',
                iconSize: [48, 48],
                iconAnchor: [24, 24],
                popupAnchor: [0, -24],
                className: 'custom-marker event-marker'
            })
        }).addTo(testMap);

        eventMarker.bindPopup(`
            <strong>Event Location</strong><br>
            <?php echo $event['event_name']; ?><br>
            <?php echo $event['event_address']; ?><br>
            Coords: ${eventLat}, ${eventLng}
        `);

        // Check visual alignment
        const markerLatLng = eventMarker.getLatLng();
        const expectedLatLng = L.latLng(43.0747, -89.3985);
        const distance = markerLatLng.distanceTo(expectedLatLng);

        document.getElementById('visualResult').innerHTML =
            distance < 5 ?
            '<strong class="text-success">✅ Visual Test PASS: Marker is correctly positioned at State Street (within 5 meters)</strong>' :
            '<strong class="text-danger">❌ Visual Test FAIL: Marker is ' + Math.round(distance) + ' meters away from State Street</strong>';
    </script>
</body>
</html>