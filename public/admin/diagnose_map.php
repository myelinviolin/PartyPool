<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = 1; // Set temporary admin session for testing
    echo "<div class='alert alert-warning'>Note: Setting temporary admin session for testing</div>";
}

include_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get current event
$event_id = $_GET['event_id'] ?? 1;

$query = "SELECT * FROM events WHERE id = :event_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':event_id', $event_id);
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all users for this event
$users_query = "SELECT * FROM users WHERE event_id = :event_id ORDER BY willing_to_drive DESC, name";
$users_stmt = $db->prepare($users_query);
$users_stmt->bindParam(':event_id', $event_id);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Extract drivers from users
$drivers = array_filter($users, function($user) {
    return $user['willing_to_drive'] && $user['vehicle_capacity'] > 0;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Map Diagnostics</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #optimizationMap { height: 400px; border-radius: 8px; margin-bottom: 20px; }
        .diagnostic-info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2>Optimization Map Diagnostics</h2>

        <div class="diagnostic-info">
            <h4>Event Data:</h4>
            <?php if ($event): ?>
                <p>✅ Event loaded: <?php echo htmlspecialchars($event['event_name']); ?></p>
                <p>Location: <?php echo $event['event_lat'] ? "Lat: {$event['event_lat']}, Lng: {$event['event_lng']}" : "No coordinates set"; ?></p>
            <?php else: ?>
                <p>❌ No event found</p>
            <?php endif; ?>
        </div>

        <div class="diagnostic-info">
            <h4>Users Data:</h4>
            <p>Total users: <?php echo count($users); ?></p>
            <p>Drivers: <?php echo count($drivers); ?></p>
            <p>Riders: <?php echo count($users) - count($drivers); ?></p>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <h5><i class="fas fa-magic"></i> Carpool Optimization Map</h5>
            </div>
            <div class="card-body">
                <div id="optimizationMap"></div>
            </div>
        </div>

        <div class="diagnostic-info">
            <h4>JavaScript Console:</h4>
            <div id="jsConsole" style="font-family: monospace; font-size: 0.9em;"></div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Log to our custom console
        function logToConsole(message, type = 'info') {
            const consoleDiv = document.getElementById('jsConsole');
            const color = type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info';
            consoleDiv.innerHTML += `<div class="text-${color}">${new Date().toLocaleTimeString()}: ${message}</div>`;
        }

        document.addEventListener('DOMContentLoaded', function() {
            logToConsole('DOM Content Loaded', 'info');

            try {
                // Initialize map
                logToConsole('Initializing map...', 'info');

                <?php
                $map_lat = $event['event_lat'] ?: 43.0731;
                $map_lng = $event['event_lng'] ?: -89.4012;
                ?>

                const map = L.map('optimizationMap').setView([<?php echo $map_lat; ?>, <?php echo $map_lng; ?>], 12);
                logToConsole('Map object created at [<?php echo $map_lat; ?>, <?php echo $map_lng; ?>]', 'success');

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
                logToConsole('Tile layer added', 'success');

                // Add event marker
                <?php if ($event['event_lat'] && $event['event_lng']): ?>
                const eventMarker = L.marker([<?php echo $event['event_lat']; ?>, <?php echo $event['event_lng']; ?>], {
                    icon: L.divIcon({
                        html: '<div style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #FFD700, #FFA500); border-radius: 50%; border: 3px solid #FF8C00; box-shadow: 0 3px 10px rgba(255, 215, 0, 0.3);"><i class="fas fa-star" style="color: white; font-size: 24px;"></i></div>',
                        iconSize: [48, 48],
                        iconAnchor: [24, 24],
                        popupAnchor: [0, -24]
                    })
                }).addTo(map).bindPopup('<?php echo addslashes($event['event_name']); ?>');
                logToConsole('Event marker added', 'success');
                <?php else: ?>
                logToConsole('No event coordinates available', 'warning');
                <?php endif; ?>

                // Add user markers
                let markerCount = 0;
                <?php foreach ($users as $user): ?>
                    <?php if ($user['lat'] && $user['lng']): ?>
                    L.marker([<?php echo $user['lat']; ?>, <?php echo $user['lng']; ?>], {
                        icon: L.divIcon({
                            html: '<div style="width: 36px; height: 36px; background: white; border-radius: 50%; box-shadow: 0 3px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; border: 3px solid <?php echo $user['willing_to_drive'] ? '#28a745' : '#17a2b8'; ?>;"><i class="fas fa-<?php echo $user['willing_to_drive'] ? 'car' : 'user-friends'; ?>" style="color: <?php echo $user['willing_to_drive'] ? '#28a745' : '#17a2b8'; ?>; font-size: 18px;"></i></div>',
                            iconSize: [36, 36],
                            iconAnchor: [18, 18],
                            popupAnchor: [0, -18]
                        })
                    }).addTo(map).bindPopup('<?php echo addslashes($user['name']); ?>');
                    markerCount++;
                    <?php endif; ?>
                <?php endforeach; ?>
                logToConsole(`Added ${markerCount} user markers`, 'success');

                logToConsole('Map initialization complete!', 'success');

            } catch (error) {
                logToConsole('Error: ' + error.message, 'error');
                console.error('Map error:', error);
            }
        });
    </script>
</body>
</html>