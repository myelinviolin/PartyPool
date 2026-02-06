<?php
session_start();
$_SESSION['admin_id'] = 1;

include_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get all users
$query = "SELECT * FROM users WHERE event_id = 1 ORDER BY willing_to_drive DESC, name";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get event
$event_query = "SELECT * FROM events WHERE id = 1";
$event_stmt = $db->prepare($event_query);
$event_stmt->execute();
$event = $event_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Map Locations</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #verifyMap { height: 600px; border-radius: 8px; }
        .location-list { max-height: 600px; overflow-y: auto; }
        .location-item { cursor: pointer; transition: all 0.3s; }
        .location-item:hover { background: #f0f0f0; transform: translateX(5px); }
        .coordinates { font-family: monospace; font-size: 0.85em; color: #666; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <h2 class="text-center mb-4">Verify User Locations on Map</h2>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Map Verification:</strong> All markers should appear on land within Madison, WI. No markers should be in lakes!
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-map-marked-alt"></i> Madison Map - All Participants</h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="verifyMap"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Participants (<?php echo count($users); ?>)</h5>
                    </div>
                    <div class="card-body location-list">
                        <?php foreach ($users as $user): ?>
                            <div class="location-item p-2 mb-2 border rounded" onclick="focusMarker(<?php echo $user['lat']; ?>, <?php echo $user['lng']; ?>)">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>
                                            <?php if ($user['willing_to_drive']): ?>
                                                <i class="fas fa-car text-success"></i>
                                            <?php else: ?>
                                                <i class="fas fa-user-friends text-info"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($user['name']); ?>
                                        </strong>
                                        <?php if ($user['willing_to_drive']): ?>
                                            <span class="badge bg-success">Driver</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Rider</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <small class="d-block text-muted">
                                    <?php echo htmlspecialchars($user['address']); ?>
                                </small>
                                <small class="coordinates">
                                    Lat: <?php echo number_format($user['lat'], 4); ?>,
                                    Lng: <?php echo number_format($user['lng'], 4); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-check-circle"></i> Verification Checklist</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="check1">
                            <label class="form-check-label" for="check1">
                                All markers visible on map
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="check2">
                            <label class="form-check-label" for="check2">
                                No markers in Lake Mendota
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="check3">
                            <label class="form-check-label" for="check3">
                                No markers in Lake Monona
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="check4">
                            <label class="form-check-label" for="check4">
                                All addresses are real locations
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="check5">
                            <label class="form-check-label" for="check5">
                                Event marker (gold star) visible
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3 text-center">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-tachometer-alt"></i> Back to Dashboard
            </a>
            <button class="btn btn-success" onclick="confirmLocations()">
                <i class="fas fa-check"></i> Locations Verified
            </button>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map;
        let markers = [];

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map centered on Madison
            map = L.map('verifyMap').setView([43.0731, -89.4012], 12);

            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Add event marker (gold star)
            <?php if ($event && $event['event_lat'] && $event['event_lng']): ?>
            const eventMarker = L.marker([<?php echo $event['event_lat']; ?>, <?php echo $event['event_lng']; ?>], {
                icon: L.divIcon({
                    html: '<div style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #FFD700, #FFA500); border-radius: 50%; border: 3px solid #FF8C00; box-shadow: 0 3px 10px rgba(255, 215, 0, 0.3);"><i class="fas fa-star" style="color: white; font-size: 24px;"></i></div>',
                    iconSize: [48, 48],
                    iconAnchor: [24, 24],
                    popupAnchor: [0, -24]
                })
            }).addTo(map).bindPopup(`
                <strong><?php echo htmlspecialchars($event['event_name']); ?></strong><br>
                <?php echo htmlspecialchars($event['event_address']); ?>
            `);
            <?php endif; ?>

            // Add user markers
            <?php foreach ($users as $user): ?>
                <?php if ($user['lat'] && $user['lng']): ?>
                const marker<?php echo $user['id']; ?> = L.marker([<?php echo $user['lat']; ?>, <?php echo $user['lng']; ?>], {
                    icon: L.divIcon({
                        html: '<div style="width: 36px; height: 36px; background: white; border-radius: 50%; box-shadow: 0 3px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; border: 3px solid <?php echo $user['willing_to_drive'] ? '#28a745' : '#17a2b8'; ?>;"><i class="fas fa-<?php echo $user['willing_to_drive'] ? 'car' : 'user-friends'; ?>" style="color: <?php echo $user['willing_to_drive'] ? '#28a745' : '#17a2b8'; ?>; font-size: 18px;"></i></div>',
                        iconSize: [36, 36],
                        iconAnchor: [18, 18],
                        popupAnchor: [0, -18]
                    })
                }).addTo(map).bindPopup(`
                    <strong><?php echo htmlspecialchars($user['name']); ?></strong><br>
                    <?php echo htmlspecialchars($user['address']); ?><br>
                    <small>Lat: <?php echo $user['lat']; ?>, Lng: <?php echo $user['lng']; ?></small>
                `);
                markers.push(marker<?php echo $user['id']; ?>);
                <?php endif; ?>
            <?php endforeach; ?>

            // Fit map to show all markers
            if (markers.length > 0) {
                const group = new L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.1));
            }
        });

        function focusMarker(lat, lng) {
            map.setView([lat, lng], 15);
            // Find and open the popup for this location
            markers.forEach(marker => {
                if (Math.abs(marker.getLatLng().lat - lat) < 0.0001 &&
                    Math.abs(marker.getLatLng().lng - lng) < 0.0001) {
                    marker.openPopup();
                }
            });
        }

        function confirmLocations() {
            const allChecked = document.querySelectorAll('.form-check-input:checked').length === 5;
            if (allChecked) {
                alert('✅ All locations verified! No markers in lakes.');
            } else {
                alert('Please verify all items in the checklist first.');
            }
        }
    </script>
</body>
</html>