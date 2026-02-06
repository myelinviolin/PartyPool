<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
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

// Get current assignments
$assignments_query = "SELECT ca.*, u.name as driver_name, u.vehicle_make, u.vehicle_model
                     FROM carpool_assignments ca
                     JOIN users u ON ca.driver_user_id = u.id
                     WHERE ca.event_id = :event_id AND ca.is_active = TRUE";
$assignments_stmt = $db->prepare($assignments_query);
$assignments_stmt->bindParam(':event_id', $event_id);
$assignments_stmt->execute();
$assignments = $assignments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Party Carpool</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes slideDown {
            from {
                transform: translate(-50%, -100%);
                opacity: 0;
            }
            to {
                transform: translate(-50%, 0);
                opacity: 1;
            }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .success-notification {
            animation: slideDown 0.3s ease-out;
        }

        .update-success {
            animation: pulse 0.5s ease;
            border-color: #28a745 !important;
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.3);
        }

        /* Map popup styles to match homepage */
        .leaflet-popup-content {
            width: 250px;
        }

        .popup-header {
            font-weight: bold;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .popup-info {
            margin: 0.25rem 0;
            font-size: 0.9rem;
        }

        .popup-info i {
            width: 20px;
            color: #6c757d;
            margin-right: 5px;
        }

        /* Custom marker hover effect */
        .custom-marker:hover {
            transform: scale(1.2);
            z-index: 1000 !important;
        }
    </style>
    <style>
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .participant-card {
            transition: transform 0.2s;
            cursor: pointer;
            min-height: 100px; /* Ensure consistent height */
            height: 100%;
        }
        .participant-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .participant-card .card-body {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        /* Before optimization colors */
        .can-drive { background: #d4edda; } /* Green for drivers */
        .need-ride { background: #ffe4d1; } /* Orange for riders */

        /* After optimization colors */
        .assigned-driver { background: #cce5ff; } /* Blue for assigned drivers */
        .assigned-rider { background: #ffe4d1; } /* Orange for riders */
        #optimizationMap { height: 400px; border-radius: 8px; }
        .optimization-result {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 2px solid transparent;
        }
        .route-badge { background: #667eea; color: white; padding: 2px 8px; border-radius: 12px; margin: 0 2px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-user-shield"></i> Admin Dashboard
            </a>
            <div class="ms-auto">
                <a href="/index.html" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-home"></i> Home Page
                </a>
                <span class="navbar-text text-white me-3">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-lg-3">
                <!-- Event Management -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-calendar-alt"></i> Event Details</h5>
                    </div>
                    <div class="card-body">
                        <form id="eventForm">
                            <input type="hidden" id="eventId" value="<?php echo $event_id; ?>">
                            <div class="mb-3">
                                <label class="form-label">Event Name</label>
                                <input type="text" class="form-control" id="eventName"
                                       value="<?php echo htmlspecialchars($event['event_name']); ?>"
                                       oninput="checkFormChanged()"
                                       onchange="checkFormChanged()">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Destination Address</label>
                                <input type="text" class="form-control" id="eventAddress"
                                       value="<?php echo htmlspecialchars($event['event_address']); ?>"
                                       oninput="checkFormChanged()"
                                       onchange="checkFormChanged()">
                                <small class="text-muted d-block mt-1">
                                    <?php if ($event['event_lat'] && $event['event_lng']): ?>
                                        <i class="fas fa-check-circle text-success"></i> Location coordinates: <?php echo number_format($event['event_lat'], 6); ?>, <?php echo number_format($event['event_lng'], 6); ?>
                                    <?php else: ?>
                                        <i class="fas fa-exclamation-triangle text-warning"></i> Location will be geocoded when you save
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Event Date</label>
                                <input type="date" class="form-control" id="eventDate"
                                       value="<?php echo $event['event_date']; ?>"
                                       oninput="checkFormChanged()"
                                       onchange="checkFormChanged()">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Event Time</label>
                                <input type="time" class="form-control" id="eventTime"
                                       value="<?php echo $event['event_time']; ?>"
                                       oninput="checkFormChanged()"
                                       onchange="checkFormChanged()">
                            </div>
                            <button type="button" class="btn btn-secondary w-100" onclick="updateEvent(this)" disabled>
                                <i class="fas fa-save"></i> Update Event
                            </button>
                        </form>
                    </div>
                </div>

            </div>

            <div class="col-lg-9">
                <!-- Optimization Controls -->
                <div class="card mb-3">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-magic"></i> Carpool Optimization</h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center mb-3">
                            <div class="col-md-8">
                                <p class="mb-2">The optimization algorithm will:</p>
                                <ul class="mb-0">
                                    <li>Minimize total vehicles needed</li>
                                    <li>Group participants by geographic proximity</li>
                                    <li>Calculate time overhead for each driver</li>
                                    <li>Show direct vs carpool route comparison</li>
                                </ul>
                            </div>
                            <div class="col-md-4 text-center">
                                <button class="btn btn-success btn-lg w-100" onclick="runOptimization()">
                                    <i class="fas fa-play"></i> Run Optimization
                                </button>
                                <div id="optimizationStatus" class="mt-2"></div>
                            </div>
                        </div>

                        <div id="optimizationMap"></div>
                    </div>
                </div>

                <!-- Participants List -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5><i class="fas fa-users"></i> Participants</h5>
                    </div>
                    <div class="card-body">
                        <div class="row" id="participantsList">
                            <?php foreach ($users as $user): ?>
                            <div class="col-md-6 mb-2">
                                <div class="participant-card card <?php echo $user['willing_to_drive'] ? 'can-drive' : 'need-ride'; ?>"
                                     data-user-id="<?php echo $user['id']; ?>"
                                     data-user-name="<?php echo htmlspecialchars($user['name']); ?>"
                                     data-can-drive="<?php echo $user['willing_to_drive'] ? 'true' : 'false'; ?>">
                                    <div class="card-body p-2">
                                        <div class="mb-1">
                                            <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                            <?php if ($user['willing_to_drive']): ?>
                                                <span class="badge bg-success ms-1">
                                                    <i class="fas fa-car"></i> Can Drive
                                                </span>
                                                <?php if ($user['vehicle_capacity']): ?>
                                                    <span class="badge bg-info"><?php echo $user['vehicle_capacity']; ?> seats</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-warning ms-1">
                                                    <i class="fas fa-user"></i> Needs Ride
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="assignment-status mb-1"></div>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($user['address'] ?? 'No address'); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Optimization Results -->
                <div class="card" id="resultsCard" style="display: none;">
                    <div class="card-header bg-info text-white">
                        <h5><i class="fas fa-route"></i> Optimization Results</h5>
                    </div>
                    <div class="card-body" id="optimizationResults">
                        <!-- Results will be inserted here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global error handler for debugging
        window.onerror = function(msg, url, line, col, error) {
            console.error('Global error:', msg, 'at line', line);
            return false;
        };

        // IMPORTANT: Define these in global scope immediately
        var map;
        var markers = [];
        var originalEventData = {};

        // Verify script is loading
        console.log('Dashboard script loading...');

        // Store original values for change detection - GLOBAL
        window.storeOriginalValues = function() {
            originalEventData = {
                event_name: document.getElementById('eventName')?.value || '',
                event_address: document.getElementById('eventAddress')?.value || '',
                event_date: document.getElementById('eventDate')?.value || '',
                event_time: document.getElementById('eventTime')?.value || ''
            };
            console.log('Stored original values:', originalEventData);
        }

        // Check if form has changed - GLOBAL
        window.checkFormChanged = function() {
            // Check if original values are set
            if (Object.keys(originalEventData).length === 0) {
                console.warn('Original values not yet stored! Storing now...');
                storeOriginalValues();
            }

            const currentData = {
                event_name: document.getElementById('eventName')?.value || '',
                event_address: document.getElementById('eventAddress')?.value || '',
                event_date: document.getElementById('eventDate')?.value || '',
                event_time: document.getElementById('eventTime')?.value || ''
            };

            const hasChanged = JSON.stringify(originalEventData) !== JSON.stringify(currentData);
            console.log('checkFormChanged:', {
                original: originalEventData,
                current: currentData,
                changed: hasChanged
            });

            const updateBtn = document.querySelector('button[onclick*="updateEvent"]');
            if (updateBtn) {
                updateBtn.disabled = !hasChanged;
                if (!hasChanged) {
                    updateBtn.classList.add('btn-secondary');
                    updateBtn.classList.remove('btn-primary');
                } else {
                    updateBtn.classList.remove('btn-secondary');
                    updateBtn.classList.add('btn-primary');
                }
                console.log('Button state - Disabled:', updateBtn.disabled, 'Classes:', updateBtn.className);
            } else {
                console.error('Update button not found!');
            }
        }


        // Initialize map
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded - Initializing...');

            // Store original values when page loads
            storeOriginalValues();

            // Initially check button state
            checkFormChanged();

            // Debug: Check if functions are accessible
            console.log('Functions available:');
            console.log('- checkFormChanged:', typeof window.checkFormChanged);
            console.log('- storeOriginalValues:', typeof window.storeOriginalValues);
            console.log('- updateEvent:', typeof window.updateEvent);
            console.log('Original values stored:', originalEventData);

            // Add manual test trigger
            console.log('To test manually, type in console:');
            console.log("document.getElementById('eventName').value = 'Test Name Changed';");
            console.log("checkFormChanged();");

            // Initialize map - center on event location or default to Madison
            console.log("Initializing optimization map");
            <?php
            $map_lat = $event['event_lat'] ?: 43.0731;
            $map_lng = $event['event_lng'] ?: -89.4012;
            ?>
            map = L.map('optimizationMap').setView([<?php echo $map_lat; ?>, <?php echo $map_lng; ?>], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            let allMarkers = [];

            // Add event location marker with homepage gold star style
            <?php if ($event['event_lat'] && $event['event_lng']): ?>
            const eventMarker = L.marker([<?php echo $event['event_lat']; ?>, <?php echo $event['event_lng']; ?>], {
                icon: L.divIcon({
                    html: '<div style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #FFD700, #FFA500); border-radius: 50%; border: 3px solid #FF8C00; box-shadow: 0 3px 10px rgba(255, 215, 0, 0.3);"><i class="fas fa-star" style="color: white; font-size: 24px;"></i></div>',
                    iconSize: [48, 48],
                    iconAnchor: [24, 24],
                    popupAnchor: [0, -24],
                    className: ''  // No CSS classes to avoid conflicts
                })
            }).addTo(map).bindPopup(`
                <div class="popup-header" style="color: #FF8C00;">
                    <i class="fas fa-star"></i> Event Location
                </div>
                <div class="popup-info"><i class="fas fa-map-marker-alt"></i> <?php echo addslashes($event['event_name']); ?></div>
                <div class="popup-info"><i class="fas fa-location-arrow"></i> <?php echo addslashes($event['event_address']); ?></div>
                <div class="popup-info"><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($event['event_date'])); ?></div>
                <div class="popup-info"><i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($event['event_time'])); ?></div>
            `);
            allMarkers.push(eventMarker);
            <?php endif; ?>

            // Add user markers with homepage styling
            <?php foreach ($users as $user): ?>
                <?php if ($user['lat'] && $user['lng']): ?>
                allMarkers.push(
                    L.marker([<?php echo $user['lat']; ?>, <?php echo $user['lng']; ?>], {
                        icon: L.divIcon({
                            html: '<div style="width: 36px; height: 36px; background: white; border-radius: 50%; box-shadow: 0 3px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; border: 3px solid <?php echo $user['willing_to_drive'] ? '#28a745' : '#17a2b8'; ?>;"><i class="fas fa-<?php echo $user['willing_to_drive'] ? 'car' : 'user-friends'; ?>" style="color: <?php echo $user['willing_to_drive'] ? '#28a745' : '#17a2b8'; ?>; font-size: 18px;"></i></div>',
                            iconSize: [36, 36],
                            iconAnchor: [18, 18],
                            popupAnchor: [0, -18],
                            className: 'custom-marker <?php echo $user['willing_to_drive'] ? 'driver-marker' : 'rider-marker'; ?>'
                        })
                    }).addTo(map).bindPopup(`
                        <div class="popup-header" style="color: <?php echo $user['willing_to_drive'] ? '#28a745' : '#17a2b8'; ?>;">
                            <i class="fas fa-<?php echo $user['willing_to_drive'] ? 'car' : 'user-friends'; ?>"></i> <?php echo addslashes($user['name']); ?>
                        </div>
                        <?php if ($user['willing_to_drive']): ?>
                            <div class="badge bg-success mb-2">Willing to Drive</div>
                            <div class="popup-info"><i class="fas fa-chair"></i> <strong><?php echo $user['vehicle_capacity'] ?: 4; ?> seats available</strong></div>
                            <?php if ($user['vehicle_make']): ?>
                                <div class="popup-info"><i class="fas fa-car-side"></i> <?php echo htmlspecialchars($user['vehicle_make'] . ' ' . ($user['vehicle_model'] ?: '')); ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="badge bg-info mb-2">Needs a Ride</div>
                        <?php endif; ?>
                        <div class="popup-info"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                        <?php if ($user['phone']): ?>
                            <div class="popup-info"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?></div>
                        <?php endif; ?>
                        <div class="popup-info"><i class="fas fa-home"></i> <?php echo htmlspecialchars($user['address']); ?></div>
                    `)
                );
                <?php endif; ?>
            <?php endforeach; ?>

            // Fit map to show all markers if any exist
            if (allMarkers.length > 0) {
                const group = new L.featureGroup(allMarkers);
                map.fitBounds(group.getBounds().pad(0.1));
            }
        });

        // UPDATE EVENT FUNCTION - Global scope for onclick handler
        window.updateEvent = async function(btn) {
            console.log('updateEvent called!');

            // Show loading state
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';

            const eventData = {
                id: document.getElementById('eventId').value,
                event_name: document.getElementById('eventName').value,
                event_address: document.getElementById('eventAddress').value,
                event_date: document.getElementById('eventDate').value,
                event_time: document.getElementById('eventTime').value
            };

            console.log('Updating event with data:', eventData);

            try {
                const response = await fetch('update_event.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(eventData)
                });

                const result = await response.json();
                console.log('Update result:', result);

                if (result.success) {
                    // Add visual feedback to the card
                    const eventCard = btn.closest('.card');
                    if (eventCard) {
                        eventCard.classList.add('update-success');
                        setTimeout(() => eventCard.classList.remove('update-success'), 1000);
                    }

                    // Show success message at top of page
                    const pageAlert = document.createElement('div');
                    pageAlert.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3 success-notification shadow-lg';
                    pageAlert.style.zIndex = '9999';
                    pageAlert.style.minWidth = '400px';
                    pageAlert.innerHTML = `
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle fa-2x me-3"></i>
                            <div>
                                <strong>Event Updated Successfully!</strong><br>
                                <small>${eventData.event_name} has been updated.</small>
                                ${result.lat && result.lng ? `<br><small>Location geocoded: ${eventData.event_address}</small>` : ''}
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(pageAlert);

                    // Also show inline success
                    const inlineAlert = document.createElement('div');
                    inlineAlert.className = 'alert alert-success alert-dismissible fade show mt-2';
                    inlineAlert.innerHTML = `
                        <i class="fas fa-check"></i> Changes saved successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    btn.parentElement.appendChild(inlineAlert);

                    // Auto-dismiss floating alert after 3 seconds
                    setTimeout(() => {
                        pageAlert.classList.remove('show');
                        setTimeout(() => pageAlert.remove(), 150);
                    }, 3000);

                    // Store new values as original and reset button state
                    storeOriginalValues();
                    checkFormChanged();

                    // Reload after a short delay
                    setTimeout(() => location.reload(), 2000);
                } else {
                    // Show error message
                    const errorAlert = document.createElement('div');
                    errorAlert.className = 'alert alert-danger alert-dismissible fade show mt-2';
                    errorAlert.innerHTML = `
                        <i class="fas fa-exclamation-triangle"></i> Error updating event: ${result.message || 'Unknown error'}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    btn.parentElement.appendChild(errorAlert);

                    // Re-enable button and restore state
                    btn.innerHTML = originalText;
                    checkFormChanged();
                }
            } catch (error) {
                console.error('Update error:', error);

                // Show error message
                const errorAlert = document.createElement('div');
                errorAlert.className = 'alert alert-danger alert-dismissible fade show mt-2';
                errorAlert.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i> Connection error: ${error.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                btn.parentElement.appendChild(errorAlert);

                // Re-enable button and restore state
                btn.innerHTML = originalText;
                checkFormChanged();
            }
        }

        function updateParticipantCards(optimizationResult) {
            console.log('Updating participant cards with optimization result:', optimizationResult);

            // After optimization, ALL cards should be orange (riders) except assigned drivers (blue)
            // Step 1: Set ALL cards to orange (assigned-rider) and clear statuses
            document.querySelectorAll('.participant-card').forEach(card => {
                card.classList.remove('assigned-driver', 'can-drive', 'need-ride');
                card.classList.add('assigned-rider'); // Everyone is a rider by default

                const statusDiv = card.querySelector('.assignment-status');
                if (statusDiv) {
                    statusDiv.innerHTML = '';
                }
            });

            // Step 2: Mark assigned drivers as blue and add appropriate badges
            if (optimizationResult.routes) {
                // First pass: Mark all assigned drivers with appropriate badges
                optimizationResult.routes.forEach((route, index) => {
                    console.log(`Processing route ${index + 1}, driver: ${route.driver_name}`);

                    // Find driver card - need to handle HTML entities and special characters
                    let driverCard = null;

                    // Create a temporary element to decode HTML entities
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = route.driver_name;
                    const decodedDriverName = tempDiv.textContent || tempDiv.innerText || '';

                    // Try to find the card using different matching strategies
                    document.querySelectorAll('.participant-card').forEach(card => {
                        const cardName = card.dataset.userName || '';

                        // Check various matching strategies
                        if (cardName === route.driver_name ||
                            cardName === decodedDriverName ||
                            cardName.includes(route.driver_name) ||
                            route.driver_name.includes(cardName) ||
                            cardName.includes(decodedDriverName) ||
                            decodedDriverName.includes(cardName)) {
                            driverCard = card;
                            console.log(`Matched driver: "${route.driver_name}" with card: "${cardName}"`);
                        }
                    });

                    if (driverCard) {
                        console.log(`Found card for driver: ${route.driver_name}`);
                        // Change from orange to blue for assigned drivers
                        driverCard.classList.remove('assigned-rider');
                        driverCard.classList.add('assigned-driver');

                        const statusDiv = driverCard.querySelector('.assignment-status');
                        if (statusDiv) {
                            // Check if driver has passengers to determine badge text
                            const hasPassengers = route.passengers && route.passengers.length > 0;
                            if (hasPassengers) {
                                statusDiv.innerHTML = `<span class="badge bg-primary">
                                    <i class="fas fa-car"></i> Driving - picking up passengers
                                </span>`;
                            } else {
                                statusDiv.innerHTML = `<span class="badge bg-primary">
                                    <i class="fas fa-car-side"></i> Driving Solo
                                </span>`;
                            }
                        }
                    } else {
                        console.warn(`Could not find card for driver: ${route.driver_name}`);
                    }
                });

                // Second pass: Add "Riding with" badges for passengers
                optimizationResult.routes.forEach(route => {
                    if (route.passengers && route.passengers.length > 0) {
                        route.passengers.forEach(passenger => {
                            console.log(`Processing passenger: ${passenger.name}, riding with ${route.driver_name}`);

                            // Find passenger card - need to handle HTML entities and special characters
                            let passengerCard = null;

                            // Create a temporary element to decode HTML entities
                            const tempDiv2 = document.createElement('div');
                            tempDiv2.innerHTML = passenger.name;
                            const decodedPassengerName = tempDiv2.textContent || tempDiv2.innerText || '';

                            // Try to find the card using different matching strategies
                            document.querySelectorAll('.participant-card').forEach(card => {
                                const cardName = card.dataset.userName || '';

                                // Check various matching strategies
                                if (cardName === passenger.name ||
                                    cardName === decodedPassengerName ||
                                    cardName.includes(passenger.name) ||
                                    passenger.name.includes(cardName) ||
                                    cardName.includes(decodedPassengerName) ||
                                    decodedPassengerName.includes(cardName)) {
                                    passengerCard = card;
                                    console.log(`Matched passenger: "${passenger.name}" with card: "${cardName}"`);
                                }
                            });

                            if (passengerCard) {
                                console.log(`Found card for passenger: ${passenger.name}`);
                                const statusDiv = passengerCard.querySelector('.assignment-status');
                                if (statusDiv) {
                                    statusDiv.innerHTML = `<span class="badge bg-secondary">
                                        <i class="fas fa-user-friends"></i> Riding with #${route.driver_name}
                                    </span>`;
                                }
                            } else {
                                console.warn(`Could not find card for passenger: ${passenger.name}`);
                            }
                        });
                    }
                });
            }

            // Log final status
            console.log('Card update complete. Checking results:');
            console.log('Available cards:');
            document.querySelectorAll('.participant-card').forEach(card => {
                console.log(`  Card name: "${card.dataset.userName}"`);
            });
            console.log('Assignment results:');
            document.querySelectorAll('.participant-card').forEach(card => {
                const name = card.dataset.userName;
                const hasDriverClass = card.classList.contains('assigned-driver');
                const hasRiderClass = card.classList.contains('assigned-rider');
                const statusBadge = card.querySelector('.assignment-status')?.innerText || 'No badge';
                console.log(`  ${name}: Driver=${hasDriverClass}, Rider=${hasRiderClass}, Badge="${statusBadge.trim()}"`);
            });
        }

        async function runOptimization(targetVehicles = null) {
            const statusDiv = document.getElementById('optimizationStatus');
            statusDiv.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Running optimization...';

            try {
                const response = await fetch('optimize_enhanced.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin', // Include cookies/session
                    body: JSON.stringify({
                        event_id: <?php echo $event_id; ?>,
                        target_vehicles: targetVehicles ? parseInt(targetVehicles) : null,
                        max_drive_time: 50
                    })
                });

                // Check if response is ok before parsing JSON
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const text = await response.text();
                console.log('Raw response:', text);

                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response was:', text);
                    throw new Error('Invalid JSON response from server');
                }
                console.log('Optimization result:', result);

                if (result.success) {
                    let statusMessage = `<div class="alert alert-success mb-0 mt-2">
                        <i class="fas fa-check"></i> Optimization complete!
                        Using ${result.vehicles_needed} vehicles`;

                    if (result.overhead_optimized) {
                        statusMessage += ` <span class="badge bg-info ms-2">
                            <i class="fas fa-bolt"></i> Overhead optimized: max ${result.max_overhead || 0} min
                        </span>`;
                    }

                    statusMessage += `</div>`;

                    if (result.overhead_optimized && result.max_overhead <= 20) {
                        statusMessage += `<div class="alert alert-info mb-0 mt-2">
                            <i class="fas fa-info-circle"></i> All drivers have overhead under 20 minutes!
                        </div>`;
                    }

                    statusDiv.innerHTML = statusMessage;
                    displayEnhancedResults(result);
                    updateParticipantCards(result);  // Update participant cards
                    document.getElementById('resultsCard').style.display = 'block';

                    // Draw routes on map
                    if (result.routes) {
                        drawRoutes(result);
                    }
                } else {
                    statusDiv.innerHTML = '<div class="alert alert-danger mb-0 mt-2">Error: ' + result.message + '</div>';
                }
            } catch (error) {
                statusDiv.innerHTML = '<div class="alert alert-danger mb-0 mt-2">Error: ' + error.message + '</div>';
            }
        }

        function rerunWithVehicles() {
            const newTarget = document.getElementById('newTargetVehicles').value;
            if (newTarget && newTarget > 0) {
                // Clear previous results
                markers.forEach(m => map.removeLayer(m));
                markers = [];

                // Run with new target
                runOptimization(newTarget);
            }
        }

        function displayEnhancedResults(assignments) {
            const resultsDiv = document.getElementById('optimizationResults');
            let html = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Optimized ${assignments.total_participants} participants into ${assignments.vehicles_needed} vehicles
                    - Saved ${assignments.vehicles_saved} ${assignments.vehicles_saved === 1 ? 'vehicle' : 'vehicles'}
                </div>
            `;

            assignments.routes.forEach((route, index) => {
                const isHighOverhead = route.overhead_time && parseInt(route.overhead_time) > 20;
                const hasPassengers = route.passengers && route.passengers.length > 0;

                html += `
                    <div class="optimization-result">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6>
                                    <i class="fas fa-car text-success"></i>
                                    ${route.driver_name}
                                    ${route.vehicle ? `<span class="badge bg-secondary ms-2">${route.vehicle}</span>` : ''}
                                </h6>
                            </div>
                            <div class="text-end">
                                ${hasPassengers && route.direct_time ? `
                                    <span class="badge ${isHighOverhead ? 'bg-warning' : 'bg-info'}">
                                        <i class="fas fa-clock"></i>
                                        +${route.overhead_time} overhead
                                    </span>
                                ` : ''}
                                ${!hasPassengers ? `
                                    <span class="badge bg-success">
                                        <i class="fas fa-arrow-right"></i>
                                        Direct to destination
                                    </span>
                                ` : ''}
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-md-8">
                                ${route.departure_time ? `<div><strong>Departure:</strong> ${route.departure_time}</div>` : ''}
                                <div>
                                    <strong>Route:</strong>
                                    ${hasPassengers ?
                                        route.passengers.map(p => `<span class="route-badge">${p.name}</span>`).join(' → ') :
                                        '<span class="text-muted">Drive directly to destination</span>'
                                    }
                                </div>
                            </div>
                            <div class="col-md-4">
                                ${hasPassengers ? `
                                    <small class="d-block">
                                        <i class="fas fa-route"></i> Direct: ${route.direct_distance || '0'} mi in ${route.direct_time || '0'}
                                    </small>
                                    <small class="d-block">
                                        <i class="fas fa-users"></i> Carpool: ${route.total_distance} mi in ${route.estimated_travel_time}
                                    </small>
                                ` : `
                                    <small class="d-block">
                                        <i class="fas fa-route"></i> Distance: ${route.direct_distance || '0'} mi
                                    </small>
                                    <small class="d-block">
                                        <i class="fas fa-clock"></i> Travel time: ${route.direct_time || '0'}
                                    </small>
                                `}
                                <small class="d-block">
                                    <i class="fas fa-user-friends"></i> ${route.passengers.length} / ${route.capacity} passengers
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });

            html += `
                <div class="card mt-3 bg-light">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="fas fa-sliders-h"></i> Adjust Optimization:</h6>
                        <div class="row align-items-end">
                            <div class="col-md-6">
                                <label class="form-label">Try Different Vehicle Count:</label>
                                <input type="number" id="newTargetVehicles" class="form-control"
                                       min="1" max="<?php echo count($drivers); ?>"
                                       value="${assignments.vehicles_needed}">
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-warning" onclick="rerunWithVehicles()">
                                    <i class="fas fa-redo"></i> Rerun with New Vehicle Count
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 text-center">
                    <button class="btn btn-primary btn-lg" onclick="saveAssignments()">
                        <i class="fas fa-save"></i> Save These Assignments
                    </button>
                </div>
            `;

            resultsDiv.innerHTML = html;
        }

        // Keep old displayResults function as fallback
        function displayResults(assignments) {
            displayEnhancedResults(assignments);
        }

        function drawRoutes(assignments) {
            // Clear existing route lines
            markers.forEach(m => map.removeLayer(m));
            markers = [];

            // Draw routes for each vehicle
            assignments.routes.forEach(route => {
                if (route.coordinates && route.coordinates.length > 1) {
                    const polyline = L.polyline(route.coordinates, {
                        color: getRandomColor(),
                        weight: 3,
                        opacity: 0.7
                    }).addTo(map);
                    markers.push(polyline);
                }
            });
        }

        function getRandomColor() {
            const colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#74B9FF'];
            return colors[Math.floor(Math.random() * colors.length)];
        }

        async function saveAssignments() {
            try {
                const response = await fetch('save_assignments.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ event_id: <?php echo $event_id; ?> })
                });

                const result = await response.json();
                if (result.success) {
                    alert('Assignments saved successfully! Users have been notified.');
                    location.reload();
                }
            } catch (error) {
                alert('Error saving assignments: ' + error.message);
            }
        }

        // Verify functions are loaded
        console.log('Dashboard script loaded. Functions available:');
        console.log('- checkFormChanged:', typeof window.checkFormChanged);
        console.log('- updateEvent:', typeof window.updateEvent);
        console.log('- storeOriginalValues:', typeof window.storeOriginalValues);
    </script>
</body>
</html>