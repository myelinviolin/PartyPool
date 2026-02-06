<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo '<div class="alert alert-warning">Please <a href="login.php">login</a> first to test this feature.</div>';
    // For testing purposes, we'll continue anyway
}

include_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get current event data
$query = "SELECT * FROM events WHERE id = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$currentEvent = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Event Address Update</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
    <div class="container mt-4">
        <h2>Test Event Address Update - REAL Test</h2>

        <div class="alert alert-info">
            <strong>This test will:</strong>
            <ol class="mb-0">
                <li>Show the current saved address and coordinates</li>
                <li>Let you update to a DIFFERENT address</li>
                <li>Verify the coordinates actually change</li>
                <li>Show the marker at the new location</li>
            </ol>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Current Event Location</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($currentEvent['event_address']); ?></p>
                        <p><strong>Latitude:</strong> <?php echo $currentEvent['event_lat']; ?></p>
                        <p><strong>Longitude:</strong> <?php echo $currentEvent['event_lng']; ?></p>
                        <p><strong>Last Updated:</strong> <?php echo $currentEvent['updated_at']; ?></p>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">Test Update to NEW Address</h5>
                    </div>
                    <div class="card-body">
                        <form id="updateForm">
                            <div class="mb-3">
                                <label class="form-label">Test with Different Addresses:</label>
                                <div class="list-group mb-3">
                                    <button type="button" class="list-group-item list-group-item-action" onclick="setAddress('Wisconsin State Capitol, Madison, WI')">
                                        Wisconsin State Capitol (Different from State Street)
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action" onclick="setAddress('1 University Ave, Madison, WI 53703')">
                                        University Avenue (Different location)
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action" onclick="setAddress('702 West Johnson Street, Madison, WI')">
                                        West Johnson Street (Campus area)
                                    </button>
                                    <button type="button" class="list-group-item list-group-item-action" onclick="setAddress('615 State Street, Madison, WI 53703')">
                                        Original State Street (Reset to original)
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Or Enter Custom Address:</label>
                                <input type="text" id="newAddress" class="form-control"
                                       placeholder="Enter any Madison address"
                                       value="Wisconsin State Capitol, Madison, WI">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Event Name:</label>
                                <input type="text" id="eventName" class="form-control"
                                       value="<?php echo htmlspecialchars($currentEvent['event_name']); ?>">
                            </div>

                            <button type="button" onclick="testUpdate()" class="btn btn-warning w-100">
                                <i class="fas fa-sync"></i> Test Update Event
                            </button>
                        </form>

                        <div id="updateResult" class="mt-3"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Map View - Before & After</h5>
                    </div>
                    <div class="card-body">
                        <div id="map" style="height: 400px;"></div>
                        <div id="mapInfo" class="mt-2 small text-muted">
                            <i class="fas fa-info-circle"></i> Blue marker: Current location | Red marker: New location after update
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Test Results</h5>
                    </div>
                    <div class="card-body" id="testResults">
                        <p class="text-muted">Update event to see test results...</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-secondary mt-3">
            <strong>How to verify it's working:</strong>
            <ol class="mb-0">
                <li>Choose a different address from the list above</li>
                <li>Click "Test Update Event"</li>
                <li>Check that the coordinates change (not the same as before)</li>
                <li>Verify the map marker moves to the new location</li>
                <li>Refresh the page - the new address should persist</li>
            </ol>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map
        const map = L.map('map').setView([43.0747, -89.3985], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Add current location marker (blue)
        let currentMarker = L.marker([<?php echo $currentEvent['event_lat']; ?>, <?php echo $currentEvent['event_lng']; ?>], {
            icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            })
        }).addTo(map);
        currentMarker.bindPopup('Current: <?php echo htmlspecialchars($currentEvent['event_address']); ?>').openPopup();

        let newMarker = null;

        function setAddress(address) {
            document.getElementById('newAddress').value = address;
        }

        async function testUpdate() {
            const resultDiv = document.getElementById('updateResult');
            const testResultsDiv = document.getElementById('testResults');
            const newAddress = document.getElementById('newAddress').value;
            const eventName = document.getElementById('eventName').value;

            if (!newAddress) {
                alert('Please enter an address');
                return;
            }

            resultDiv.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Updating event...';
            testResultsDiv.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Running tests...';

            try {
                // Get coordinates before update
                const beforeCoords = {
                    lat: <?php echo $currentEvent['event_lat']; ?>,
                    lng: <?php echo $currentEvent['event_lng']; ?>
                };

                // Call the update endpoint
                const response = await fetch('update_event.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: 1,
                        event_name: eventName,
                        event_address: newAddress,
                        event_date: '<?php echo $currentEvent['event_date']; ?>',
                        event_time: '<?php echo $currentEvent['event_time']; ?>'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Get updated event data
                    const eventResponse = await fetch('/api/events.php?id=1');
                    const eventData = await eventResponse.json();

                    const afterCoords = {
                        lat: parseFloat(eventData.event_lat),
                        lng: parseFloat(eventData.event_lng)
                    };

                    // Check if coordinates actually changed
                    const coordsChanged = (Math.abs(beforeCoords.lat - afterCoords.lat) > 0.0001) ||
                                        (Math.abs(beforeCoords.lng - afterCoords.lng) > 0.0001);

                    const addressChanged = newAddress !== '<?php echo addslashes($currentEvent['event_address']); ?>';

                    // Update map with new marker
                    if (newMarker) {
                        map.removeLayer(newMarker);
                    }

                    if (coordsChanged) {
                        newMarker = L.marker([afterCoords.lat, afterCoords.lng], {
                            icon: L.icon({
                                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                                iconSize: [25, 41],
                                iconAnchor: [12, 41],
                                popupAnchor: [1, -34],
                                shadowSize: [41, 41]
                            })
                        }).addTo(map);
                        newMarker.bindPopup('New: ' + newAddress).openPopup();

                        // Fit map to show both markers
                        const group = new L.featureGroup([currentMarker, newMarker]);
                        map.fitBounds(group.getBounds().pad(0.1));
                    }

                    // Display results
                    resultDiv.innerHTML = `
                        <div class="alert alert-${result.success ? 'success' : 'danger'}">
                            <strong>${result.success ? '✅ Update Response:' : '❌ Update Failed:'}</strong><br>
                            ${result.message || 'Event updated successfully'}<br>
                            Geocoded: ${result.lat}, ${result.lng}
                        </div>
                    `;

                    // Test results
                    let testHtml = '<h6>Test Results:</h6><ul class="list-unstyled">';

                    // Test 1: API Response
                    testHtml += `<li>${result.success ? '✅' : '❌'} API returned success</li>`;

                    // Test 2: Address Changed
                    testHtml += `<li>${addressChanged ? '✅' : '⚠️'} Address is different from original
                                 ${addressChanged ? '' : '(same address)'}</li>`;

                    // Test 3: Coordinates Changed
                    testHtml += `<li>${coordsChanged ? '✅' : '❌'} Coordinates changed
                                 ${coordsChanged ? '(moved ' + Math.round(haversineDistance(beforeCoords, afterCoords)) + ' meters)' : '(NO CHANGE - UPDATE FAILED!)'}</li>`;

                    // Test 4: Geocoding worked
                    testHtml += `<li>${afterCoords.lat && afterCoords.lng ? '✅' : '❌'} Geocoding returned valid coordinates</li>`;

                    // Test 5: Database updated
                    testHtml += `<li>${eventData.event_address === newAddress ? '✅' : '❌'} Database saved new address</li>`;

                    testHtml += '</ul>';

                    if (coordsChanged) {
                        testHtml += `
                            <div class="alert alert-success mt-3">
                                <strong>✅ SUCCESS!</strong><br>
                                Address updated from:<br>
                                <small>${'<?php echo htmlspecialchars($currentEvent['event_address']); ?>'}</small><br>
                                To:<br>
                                <small>${newAddress}</small><br>
                                Coordinates: ${afterCoords.lat}, ${afterCoords.lng}
                            </div>
                        `;
                    } else {
                        testHtml += `
                            <div class="alert alert-danger mt-3">
                                <strong>❌ FAILURE!</strong><br>
                                The coordinates did not change. The update is not working properly.<br>
                                Before: ${beforeCoords.lat}, ${beforeCoords.lng}<br>
                                After: ${afterCoords.lat}, ${afterCoords.lng}
                            </div>
                        `;
                    }

                    testResultsDiv.innerHTML = testHtml;

                    // Auto-refresh after 3 seconds if successful
                    if (coordsChanged) {
                        setTimeout(() => {
                            if (confirm('Update successful! Refresh page to see persisted changes?')) {
                                location.reload();
                            }
                        }, 3000);
                    }

                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${result.message || 'Update failed'}</div>`;
                    testResultsDiv.innerHTML = '<div class="alert alert-danger">❌ Update failed - no tests to run</div>';
                }

            } catch (error) {
                resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
                testResultsDiv.innerHTML = '<div class="alert alert-danger">❌ Error during testing</div>';
            }
        }

        // Haversine distance calculation
        function haversineDistance(coords1, coords2) {
            const R = 6371000; // Earth's radius in meters
            const φ1 = coords1.lat * Math.PI / 180;
            const φ2 = coords2.lat * Math.PI / 180;
            const Δφ = (coords2.lat - coords1.lat) * Math.PI / 180;
            const Δλ = (coords2.lng - coords1.lng) * Math.PI / 180;

            const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                    Math.cos(φ1) * Math.cos(φ2) *
                    Math.sin(Δλ/2) * Math.sin(Δλ/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

            return R * c; // Distance in meters
        }
    </script>
</body>
</html>