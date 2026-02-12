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
// Ensure no whitespace or output before DOCTYPE
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
    <link rel="stylesheet" href="/css/style.css">
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
        .participant-card .btn-danger,
        .participant-card .btn-primary {
            opacity: 0.7;
            transition: opacity 0.2s;
            padding: 0.25rem 0.5rem;
        }
        .participant-card:hover .btn-danger,
        .participant-card:hover .btn-primary {
            opacity: 1;
        }
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
        .route-badge {
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            margin: 0 2px;
            display: inline-block;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-user-shield"></i> Admin Dashboard
            </a>
            <div class="ms-auto">
                <a href="/" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-home"></i> Home Page
                </a>
                <a href="/?register=true" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-user-plus"></i> Register
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
                                        value="<?php echo isset($event['event_name']) ? htmlspecialchars($event['event_name']) : ''; ?>"
                                        oninput="checkFormChanged()"
                                        onchange="checkFormChanged()">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Destination Address</label>
                                    <input type="text" class="form-control" id="eventAddress"
                                        value="<?php echo isset($event['event_address']) ? htmlspecialchars($event['event_address']) : ''; ?>"
                                        oninput="checkFormChanged()"
                                        onchange="checkFormChanged()">
                                <small class="text-muted d-block mt-1" id="addressStatus">
                                    <?php if (isset($event['event_lat']) && isset($event['event_lng']) && $event['event_lat'] && $event['event_lng']): ?>
                                        <i class="fas fa-check-circle text-success"></i> Location verified
                                    <?php else: ?>
                                        <i class="fas fa-exclamation-triangle text-warning"></i> Location will be verified on save
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Event Date</label>
                                    <input type="date" class="form-control" id="eventDate"
                                        value="<?php echo isset($event['event_date']) ? $event['event_date'] : ''; ?>"
                                        oninput="checkFormChanged()"
                                        onchange="checkFormChanged()">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Event Time</label>
                                    <input type="time" class="form-control" id="eventTime"
                                        value="<?php echo isset($event['event_time']) ? $event['event_time'] : ''; ?>"
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
                                    <div class="card-body p-2 position-relative">
                                        <div class="position-absolute top-0 end-0 m-1">
                                            <button class="btn btn-sm btn-primary me-1"
                                                    onclick="editParticipant(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                                    title="Edit participant">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger"
                                                    onclick="removeParticipant(<?php echo $user['id']; ?>, '<?php echo addslashes(htmlspecialchars($user['name'])); ?>')"
                                                    title="Remove participant">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
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
    <script src="/js/map-utils.js"></script>
    <script src="/js/shared-map-display.js"></script>

    <!-- DEBUG: Output raw HTML source around this script block -->
    <?php
    if (isset($_GET['debug_html'])) {
        $lines = file(__FILE__);
        $start = max(0, 335 - 20); // 20 lines before script
        $end = min(count($lines), 335 + 20); // 20 lines after script
        echo '<pre style="background:#fff;color:#000;max-height:400px;overflow:auto;border:2px solid red;padding:8px;">';
        for ($i = $start; $i < $end; $i++) {
            echo htmlspecialchars(sprintf('%04d: %s', $i+1, $lines[$i]));
        }
        echo '</pre>';
    }
    ?>
    <script>
        // Ensure all admin dashboard functions are globally available
        // If you see 'checkFormChanged is not defined', check for earlier JS errors or script loading order.
        // Global error handler for debugging
        window.onerror = function(msg, url, line, col, error) {
            console.error('Global error:', msg, 'at line', line);
            return false;
        };

        // Safety: define checkFormChanged as a no-op if not already defined (should be overwritten below)
        if (typeof window.checkFormChanged !== 'function') {
            window.checkFormChanged = function(){};
        }

        // IMPORTANT: Define these in global scope immediately
        var map;
        var markers = [];
        var routeMarkers = []; // Track route markers separately
        var originalEventData = {};
        var currentOptimizationResults = null; // Store optimization results for itinerary

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

            // Add event location marker using shared function
            <?php if ($event['event_lat'] && $event['event_lng']): ?>
            const eventDateTime = '<?php echo date('M j, Y \\a\\t g:i A', strtotime($event['event_date'] . ' ' . $event['event_time'])); ?>';
            const eventMarker = createEventMarker(
                <?php echo $event['event_lat']; ?>,
                <?php echo $event['event_lng']; ?>,
                '<?php echo addslashes($event['event_name']); ?>',
                '<?php echo addslashes($event['event_address']); ?>',
                eventDateTime
            );
            if (eventMarker) {
                eventMarker.addTo(map);
                allMarkers.push(eventMarker);
                SharedMapDisplay.addParticipantMarker('event', eventMarker);
            }
            <?php endif; ?>

            // Add user markers using shared functions
            SharedMapDisplay.clearParticipantMarkers(); // Clear/initialize participant markers
            <?php foreach ($users as $user): ?>
                <?php if ($user['lat'] && $user['lng']): ?>
                <?php if ($user['willing_to_drive']): ?>
                    const marker_<?php echo $user['id']; ?> = createDriverMarker(
                        <?php echo $user['lat']; ?>,
                        <?php echo $user['lng']; ?>,
                        '<?php echo addslashes($user['name']); ?>',
                        '<?php echo addslashes($user['address']); ?>',
                        <?php echo $user['vehicle_make'] ? "'" . addslashes($user['vehicle_make'] . ' ' . ($user['vehicle_model'] ?: '')) . "'" : 'null'; ?>,
                        <?php echo $user['vehicle_capacity'] ?: 4; ?>
                    );
                <?php else: ?>
                    const marker_<?php echo $user['id']; ?> = createRiderMarker(
                        <?php echo $user['lat']; ?>,
                        <?php echo $user['lng']; ?>,
                        '<?php echo addslashes($user['name']); ?>',
                        '<?php echo addslashes($user['address']); ?>'
                    );
                <?php endif; ?>
                if (marker_<?php echo $user['id']; ?>) {
                    marker_<?php echo $user['id']; ?>.addTo(map);
                    allMarkers.push(marker_<?php echo $user['id']; ?>);
                    SharedMapDisplay.addParticipantMarker(<?php echo $user['id']; ?>, marker_<?php echo $user['id']; ?>);
                }
                <?php endif; ?>
            <?php endforeach; ?>

            // Fit map to show all markers if any exist
            if (allMarkers.length > 0) {
                const group = new L.featureGroup(allMarkers);
                map.fitBounds(group.getBounds().pad(0.1));
            }

            // Load saved optimization results if they exist (after map is ready)
            loadSavedOptimization();
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
                    // Update the address field with the formatted address
                    if (result.formatted_address) {
                        document.getElementById('eventAddress').value = result.formatted_address;
                        // Update the original data so the form doesn't appear changed
                        originalEventData.event_address = result.formatted_address;
                    }

                    // Update the address status
                    const addressStatus = document.getElementById('addressStatus');
                    if (addressStatus && result.lat && result.lng) {
                        addressStatus.innerHTML = '<i class="fas fa-check-circle text-success"></i> Location verified';
                    }

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
                                ${result.formatted_address ? `<br><small>Location: ${result.formatted_address}</small>` : ''}
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
            console.log('========== UPDATING PARTICIPANT CARDS ==========');
            console.log('Optimization result:', optimizationResult);
            console.log('Routes count:', optimizationResult.routes ? optimizationResult.routes.length : 0);

            // Force clear all cards first with no assumptions
            document.querySelectorAll('.participant-card').forEach(card => {
                card.classList.remove('assigned-driver', 'assigned-rider', 'can-drive', 'need-ride');
                const statusDiv = card.querySelector('.assignment-status');
                if (statusDiv) {
                    statusDiv.innerHTML = '';
                }
            });

            // Create a map to track all assignments
            const assignmentMap = new Map();

            // Build the assignment map from card data
            document.querySelectorAll('.participant-card').forEach(card => {
                const userName = card.dataset.userName;
                if (userName) {
                    assignmentMap.set(userName, { card: card, role: 'unassigned' });
                }
            });

            // Don't default anyone to rider status - we'll set roles based on actual assignments
            // This prevents drivers from showing as riders if they're not found

            // Helper function to find matching card
            function findParticipantCard(name) {
                // Create a temporary element to decode HTML entities
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = name;
                const decodedName = tempDiv.textContent || tempDiv.innerText || '';

                let foundCard = null;
                let foundCardName = null;

                // Try to find the card using different matching strategies
                for (const [cardName, data] of assignmentMap) {
                    // Exact match
                    if (cardName === name || cardName === decodedName) {
                        foundCard = data.card;
                        foundCardName = cardName;
                        break;
                    }

                    // Partial match - name is part of card name or vice versa
                    if (cardName.includes(name) || name.includes(cardName) ||
                        cardName.includes(decodedName) || decodedName.includes(cardName)) {
                        foundCard = data.card;
                        foundCardName = cardName;
                        break;
                    }

                    // Handle "Driver X - Name" format
                    const driverMatch = cardName.match(/Driver \d+ - (.+)/);
                    if (driverMatch && (driverMatch[1] === name || driverMatch[1] === decodedName)) {
                        foundCard = data.card;
                        foundCardName = cardName;
                        break;
                    }
                }

                return { card: foundCard, name: foundCardName };
            }

            // Step 2: Mark assigned drivers as blue and add appropriate badges
            if (optimizationResult.routes) {
                console.log(`Processing ${optimizationResult.routes.length} routes...`);

                // First pass: Mark all assigned drivers with appropriate badges
                optimizationResult.routes.forEach((route, index) => {
                    console.log(`\n--- Route ${index + 1} ---`);
                    console.log(`Driver: "${route.driver_name}"`);
                    console.log(`Passengers: ${route.passengers ? route.passengers.length : 0}`);

                    // Find driver card
                    const driverMatch = findParticipantCard(route.driver_name);

                    if (driverMatch.card) {
                        console.log(`✓ Matched driver "${route.driver_name}" with card "${driverMatch.name}"`);

                        // Update assignment tracking
                        assignmentMap.get(driverMatch.name).role = 'driver';

                        // Remove all previous assignment classes and add driver class
                        driverMatch.card.classList.remove('assigned-rider', 'can-drive', 'need-ride');
                        driverMatch.card.classList.add('assigned-driver');

                        const statusDiv = driverMatch.card.querySelector('.assignment-status');
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
                        console.error(`✗ Could not find card for driver: "${route.driver_name}"`);
                    }
                });

                // Second pass: Add "Riding with" badges for passengers
                optimizationResult.routes.forEach((route, routeIndex) => {
                    if (route.passengers && route.passengers.length > 0) {
                        console.log(`\nProcessing passengers for route ${routeIndex + 1}:`);

                        route.passengers.forEach((passenger, passengerIndex) => {
                            console.log(`  Passenger ${passengerIndex + 1}: "${passenger.name}"`);

                            // Find passenger card
                            const passengerMatch = findParticipantCard(passenger.name);

                            if (passengerMatch.card) {
                                console.log(`  ✓ Matched passenger "${passenger.name}" with card "${passengerMatch.name}"`);

                                // Update assignment tracking
                                assignmentMap.get(passengerMatch.name).role = 'passenger';
                                assignmentMap.get(passengerMatch.name).driver = route.driver_name;

                                // Ensure passenger card is orange
                                passengerMatch.card.classList.remove('assigned-driver', 'can-drive', 'need-ride');
                                passengerMatch.card.classList.add('assigned-rider');

                                const statusDiv = passengerMatch.card.querySelector('.assignment-status');
                                if (statusDiv) {
                                    statusDiv.innerHTML = `<span class="badge bg-secondary">
                                        <i class="fas fa-user-friends"></i> Riding with ${route.driver_name}
                                    </span>`;
                                }
                            } else {
                                console.error(`  ✗ Could not find card for passenger: "${passenger.name}"`);
                            }
                        });
                    }
                });
            }

            // Step 3: Handle unassigned participants
            // Anyone not assigned as driver or passenger should keep their original status
            for (const [name, data] of assignmentMap) {
                if (data.role === 'unassigned') {
                    const card = data.card;
                    // Remove any assignment classes
                    card.classList.remove('assigned-driver', 'assigned-rider');

                    // Restore original status based on willing_to_drive
                    const canDrive = card.dataset.canDrive === 'true';
                    if (canDrive) {
                        card.classList.add('can-drive');
                    } else {
                        card.classList.add('need-ride');
                    }

                    // Clear any status badge
                    const statusDiv = card.querySelector('.assignment-status');
                    if (statusDiv) {
                        statusDiv.innerHTML = '';
                    }
                }
            }

            // Final verification and summary
            console.log('\n========== ASSIGNMENT SUMMARY ==========');

            let driverCount = 0;
            let passengerCount = 0;
            let unassignedCount = 0;
            const errors = [];

            // Check each participant's assignment
            for (const [name, data] of assignmentMap) {
                const card = data.card;
                const hasDriverClass = card.classList.contains('assigned-driver');
                const hasRiderClass = card.classList.contains('assigned-rider');
                const statusBadge = card.querySelector('.assignment-status')?.innerText || '';
                const cardColor = hasDriverClass ? 'BLUE' : (hasRiderClass ? 'ORANGE' : 'UNKNOWN');

                if (data.role === 'driver') {
                    driverCount++;
                    console.log(`✓ Driver: ${name} - ${cardColor} card - "${statusBadge.trim()}"`);

                    // Verify correct color
                    if (!hasDriverClass) {
                        errors.push(`${name} is marked as driver but doesn't have blue card!`);
                    }
                } else if (data.role === 'passenger') {
                    passengerCount++;
                    console.log(`✓ Passenger: ${name} - ${cardColor} card - "${statusBadge.trim()}"`);

                    // Verify correct color
                    if (!hasRiderClass) {
                        errors.push(`${name} is marked as passenger but doesn't have orange card!`);
                    }
                } else {
                    unassignedCount++;
                    console.log(`○ Unassigned: ${name} - ${cardColor} card`);
                }

                // Check for badge/color consistency
                if (statusBadge.includes('Riding with') && !hasRiderClass) {
                    errors.push(`${name} has "Riding with" badge but is not orange!`);
                }
                if ((statusBadge.includes('Driving')) && !hasDriverClass) {
                    errors.push(`${name} has driving badge but is not blue!`);
                }
            }

            console.log('\n--- TOTALS ---');
            console.log(`Drivers: ${driverCount}`);
            console.log(`Passengers: ${passengerCount}`);
            console.log(`Unassigned: ${unassignedCount}`);
            console.log(`Total: ${assignmentMap.size}`);

            // Compare with optimization result
            const expectedDrivers = optimizationResult.routes ? optimizationResult.routes.length : 0;
            const expectedPassengers = optimizationResult.routes ?
                optimizationResult.routes.reduce((total, route) => total + (route.passengers ? route.passengers.length : 0), 0) : 0;

            console.log('\n--- VERIFICATION ---');
            console.log(`Expected drivers: ${expectedDrivers}, Actual: ${driverCount} ${expectedDrivers === driverCount ? '✓' : '✗'}`);
            console.log(`Expected passengers: ${expectedPassengers}, Actual: ${passengerCount} ${expectedPassengers === passengerCount ? '✓' : '✗'}`);

            if (errors.length > 0) {
                console.error('\n--- ERRORS FOUND ---');
                errors.forEach(error => console.error(`✗ ${error}`));
            } else {
                console.log('\n✓ All assignments correctly reflected in participant cards!');
            }

            console.log('========================================');
        }

        async function loadSavedOptimization() {
            try {
                // Add timestamp to prevent caching
                const timestamp = new Date().getTime();
                const response = await fetch('/api/optimization-results.php?event_id=<?php echo $event_id; ?>&_=' + timestamp, {
                    cache: 'no-cache'
                });
                const data = await response.json();

                console.log('Loaded saved optimization:', {
                    exists: data.optimization_exists,
                    vehicles_needed: data.vehicles_needed,
                    target_vehicles: data.target_vehicles,
                    minimum_vehicles_needed: data.minimum_vehicles_needed,
                    routes_count: data.routes ? data.routes.length : 0,
                    created_at: data.created_at
                });

                // Store the minimum vehicles needed globally
                window.minimumVehiclesNeeded = data.minimum_vehicles_needed || 1;

                if (data.optimization_exists && data.routes && data.routes.length > 0) {
                    // Store the target vehicles for rerun
                    window.savedTargetVehicles = data.target_vehicles || data.vehicles_needed;

                    // Display the saved optimization results
                    displayEnhancedResults({
                        success: true,
                        total_participants: data.total_participants,
                        vehicles_needed: data.vehicles_needed,
                        vehicles_saved: data.vehicles_saved,
                        routes: data.routes,
                        target_vehicles: data.target_vehicles
                    });

                    // Show the results card
                    document.getElementById('resultsCard').style.display = 'block';

                    // Update status
                    const statusDiv = document.getElementById('optimizationStatus');
                    if (statusDiv) {
                        const lastRun = new Date(data.optimization_run_at).toLocaleString();
                        statusDiv.innerHTML = `<div class="text-success"><i class="fas fa-check-circle"></i> Last optimized: ${lastRun}</div>`;
                    }

                    // Update participant cards with saved data
                    updateParticipantCards({
                        success: true,
                        total_participants: data.total_participants,
                        vehicles_needed: data.vehicles_needed,
                        vehicles_saved: data.vehicles_saved,
                        routes: data.routes
                    });

                    // Draw routes on map
                    if (data.routes && map) {
                        drawRoutes({
                            routes: data.routes,
                            vehicles_needed: data.vehicles_needed,
                            vehicles_saved: data.vehicles_saved
                        });
                    }
                }
            } catch (error) {
                console.error('Error loading saved optimization:', error);
            }
        }

        async function runOptimization(targetVehicles = null) {
            const statusDiv = document.getElementById('optimizationStatus');
            statusDiv.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Running optimization...';

            try {
                const requestBody = {
                    event_id: <?php echo $event_id; ?>,
                    target_vehicles: targetVehicles ? parseInt(targetVehicles) : null,
                    max_drive_time: 50
                };

                console.log('Sending optimization request:', requestBody);

                const response = await fetch('optimize_enhanced.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin', // Include cookies/session
                    body: JSON.stringify(requestBody)
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
                console.log('Optimization result:', {
                    success: result.success,
                    vehicles_needed: result.vehicles_needed,
                    target_vehicles: result.target_vehicles,
                    actual_vehicles: result.actual_vehicles,
                    routes_count: result.routes ? result.routes.length : 0
                });

                if (result.success) {
                    // Store the optimization results globally
                    currentOptimizationResults = result;

                    // Store target vehicles if it was specified
                    if (targetVehicles) {
                        window.savedTargetVehicles = targetVehicles;
                    }

                    let statusMessage = `<div class="alert alert-success mb-0 mt-2">
                        <i class="fas fa-check"></i> Optimization complete!
                        Using ${result.vehicles_needed} vehicles`;

                    if (result.overhead_optimized) {
                        statusMessage += ` <span class="badge bg-info ms-2">
                            <i class="fas fa-bolt"></i> Overhead optimized: max ${result.max_overhead || 0} min
                        </span>`;
                    }

                    statusMessage += `</div>`;
                    statusDiv.innerHTML = statusMessage;

                    // Show temporary message while saving
                    document.getElementById('resultsCard').style.display = 'block';
                    document.getElementById('optimizationResults').innerHTML = '<div class="text-center"><div class="spinner-border"></div> Saving optimization results...</div>';

                    // Clear participant cards immediately to ensure clean state
                    document.querySelectorAll('.participant-card').forEach(card => {
                        card.classList.remove('assigned-driver', 'assigned-rider', 'can-drive', 'need-ride');
                        const statusDiv = card.querySelector('.assignment-status');
                        if (statusDiv) {
                            statusDiv.innerHTML = '<span class="badge bg-warning"><i class="fas fa-spinner fa-spin"></i> Updating...</span>';
                        }
                    });

                    // Wait for database save, then load the saved results as the single source of truth
                    console.log('Optimization successful, waiting for database save...');
                    setTimeout(() => {
                        console.log('Loading saved optimization from database...');
                        loadSavedOptimization();
                    }, 2000); // Increased delay to ensure database is updated
                } else {
                    statusDiv.innerHTML = '<div class="alert alert-danger mb-0 mt-2">Error: ' + result.message + '</div>';
                }
            } catch (error) {
                statusDiv.innerHTML = '<div class="alert alert-danger mb-0 mt-2">Error: ' + error.message + '</div>';
            }
        }

        function rerunWithVehicles() {
            const newTarget = document.getElementById('newTargetVehicles').value;
            const minVehicles = window.minimumVehiclesNeeded || 1;

            // Validate against minimum
            if (newTarget < minVehicles) {
                alert(`Cannot use fewer than ${minVehicles} vehicles. This is the minimum needed to transport all participants based on vehicle capacity.`);
                document.getElementById('newTargetVehicles').value = minVehicles;
                return;
            }

            if (newTarget && newTarget > 0) {
                console.log('Rerunning optimization with', newTarget, 'vehicles');

                // Hide the results card completely first
                document.getElementById('resultsCard').style.display = 'none';

                // Clear participant cards to show updating status
                document.querySelectorAll('.participant-card').forEach(card => {
                    card.classList.remove('assigned-driver', 'assigned-rider', 'can-drive', 'need-ride');
                    const statusDiv = card.querySelector('.assignment-status');
                    if (statusDiv) {
                        statusDiv.innerHTML = '<span class="badge bg-warning"><i class="fas fa-spinner fa-spin"></i> Re-optimizing...</span>';
                    }
                });

                // Clear previous map markers
                markers.forEach(m => map.removeLayer(m));
                markers = [];

                // Clear any existing route lines
                if (window.routeLines) {
                    window.routeLines.forEach(line => map.removeLayer(line));
                    window.routeLines = [];
                }

                // Show status message
                const statusDiv = document.getElementById('optimizationStatus');
                statusDiv.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Rerunning optimization with ' + newTarget + ' vehicles...';

                // Run with new target - ensure it's passed as integer
                runOptimization(parseInt(newTarget));
            }
        }

        function displayEnhancedResults(assignments) {
            const resultsDiv = document.getElementById('optimizationResults');

            // Calculate total miles saved
            let totalMilesSaved = 0;
            assignments.routes.forEach(route => {
                // Driver's miles difference (negative if driving more)
                const driverDirectDistance = parseFloat(route.direct_distance) || 0;
                const driverActualDistance = parseFloat(route.total_distance) || 0;
                const driverMilesDiff = driverDirectDistance - driverActualDistance;
                totalMilesSaved += driverMilesDiff;

                // Passengers save all their miles
                if (route.passengers && route.passengers.length > 0) {
                    route.passengers.forEach(passenger => {
                        // Use passenger's direct distance if available, otherwise use driver's as estimate
                        const passengerDirectDistance = parseFloat(passenger.direct_distance) || driverDirectDistance;
                        totalMilesSaved += passengerDirectDistance;
                    });
                }
            });

            const milesSavedText = totalMilesSaved >= 0 ?
                `<strong>${totalMilesSaved.toFixed(1)} vehicle miles saved</strong> through carpooling` :
                `Added ${Math.abs(totalMilesSaved).toFixed(1)} total miles for pickup detours`;
            const gasPumpClass = totalMilesSaved >= 0 ? 'text-success' : 'text-warning';

            let html = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Optimized ${assignments.total_participants} participants into ${assignments.vehicles_needed} vehicles
                    - Saved ${assignments.vehicles_saved} ${assignments.vehicles_saved === 1 ? 'vehicle' : 'vehicles'}<br>
                    <i class="fas fa-gas-pump ${gasPumpClass}"></i> ${milesSavedText}
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
                                ${hasPassengers ? `
                                    <div>
                                        <strong>Route (Pickup Order):</strong>
                                        <ol class="mb-0 mt-1" style="padding-left: 1.5rem;">
                                            ${route.passengers.map(p => `
                                                <li style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                    <span class="route-badge">${p.name}</span>
                                                </li>
                                            `).join('')}
                                        </ol>
                                    </div>
                                ` : `
                                    <div>
                                        <strong>Route:</strong>
                                        <span class="text-muted">Drive directly to destination</span>
                                    </div>
                                `}
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
                                ${hasPassengers ? `
                                    <small class="d-block">
                                        <i class="fas fa-user-friends"></i> ${route.passengers.length} / ${route.capacity} passengers
                                    </small>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });

            // Use target_vehicles if available, otherwise use saved value or vehicles_needed
            const targetValue = assignments.target_vehicles || window.savedTargetVehicles || assignments.vehicles_needed;
            const minVehicles = window.minimumVehiclesNeeded || 1;
            html += `
                <div class="card mt-3 bg-light">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="fas fa-sliders-h"></i> Adjust Optimization:</h6>
                        <div class="row align-items-end">
                            <div class="col-md-6">
                                <label class="form-label">Try Different Vehicle Count:</label>
                                <input type="number" id="newTargetVehicles" class="form-control"
                                       min="${minVehicles}" max="<?php echo count($drivers); ?>"
                                       value="${targetValue}">
                                <small class="text-muted">
                                    Minimum: ${minVehicles} vehicles (required for capacity)
                                </small>
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

        // Store route lines globally for interaction
        let routeLines = [];
        let selectedRouteIndex = null;
        let participantMarkers = {}; // Store references to all participant markers by ID

        function drawRoutes(assignments) {
            // Use the shared display function for consistent map display with home page
            SharedMapDisplay.displayRoutesOnMap(assignments.routes, map);

            // Sync our local variables with the shared module
            routeLines = window.routeLines;
            selectedRouteIndex = window.selectedRouteIndex;
            participantMarkers = window.participantMarkers;
        }

        // Wrapper function to call shared module for route selector
        function createRouteSelector() {
            SharedMapDisplay.createRouteSelector(map);
        }

        // Toggle route visibility - wrapper to call shared module
        function toggleRoute(index) {
            SharedMapDisplay.toggleRoute(index);
            // Sync our local variables with the shared module
            selectedRouteIndex = window.selectedRouteIndex;
        }

        // Show all participant markers - wrapper to call shared module
        function showAllParticipants() {
            SharedMapDisplay.showAllParticipants();
        }

        // Show all routes - wrapper to call shared module
        function showAllRoutes() {
            SharedMapDisplay.showAllRoutes();
        }

        // Route colors are now handled by SharedMapDisplay module

        // Remove participant function
        async function removeParticipant(userId, userName) {
            // Show confirmation dialog
            if (!confirm(`Are you sure you want to remove ${userName} from the event?\n\nThis action cannot be undone.`)) {
                return;
            }

            try {
                const response = await fetch('remove_participant.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        user_id: userId,
                        event_id: <?php echo $event_id; ?>
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Show success message
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3 shadow-lg';
                    successAlert.style.zIndex = '9999';
                    successAlert.style.minWidth = '400px';
                    successAlert.innerHTML = `
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle fa-2x me-3"></i>
                            <div>
                                <strong>Participant Removed</strong><br>
                                <small>${userName} has been removed from the event.</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(successAlert);

                    // Auto-dismiss after 3 seconds
                    setTimeout(() => {
                        successAlert.classList.remove('show');
                        setTimeout(() => successAlert.remove(), 150);
                    }, 3000);

                    // Remove the participant card from the UI
                    const participantCard = document.querySelector(`[data-user-id="${userId}"]`);
                    if (participantCard && participantCard.parentElement) {
                        participantCard.parentElement.style.transition = 'opacity 0.3s';
                        participantCard.parentElement.style.opacity = '0';
                        setTimeout(() => {
                            participantCard.parentElement.remove();
                        }, 300);
                    }

                    // Remove participant marker from map if exists
                    if (participantMarkers[userId]) {
                        map.removeLayer(participantMarkers[userId]);
                        delete participantMarkers[userId];
                    }

                    // Clear optimization results since they're now outdated
                    const resultsCard = document.getElementById('resultsCard');
                    if (resultsCard) {
                        resultsCard.style.display = 'none';
                    }

                    // Update optimization status
                    const statusDiv = document.getElementById('optimizationStatus');
                    if (statusDiv) {
                        statusDiv.innerHTML = '<div class="text-warning"><i class="fas fa-exclamation-triangle"></i> Optimization needs to be re-run</div>';
                    }
                } else {
                    // Show error message
                    alert('Error removing participant: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error removing participant:', error);
                alert('Error removing participant: ' + error.message);
            }
        }

        async function saveAssignments() {
            try {
                // First, save the assignments
                const saveResponse = await fetch('save_assignments.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        event_id: <?php echo $event_id; ?>,
                        routes: currentOptimizationResults ? currentOptimizationResults.routes : null
                    })
                });

                const saveResult = await saveResponse.json();
                if (saveResult.success) {
                    // Download the itinerary file
                    const downloadUrl = `generate_itinerary.php?event_id=<?php echo $event_id; ?>`;
                    const link = document.createElement('a');
                    link.href = downloadUrl;
                    link.download = `carpool_itinerary_${new Date().toISOString().split('T')[0]}.txt`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    // Show success message after a short delay to allow download to start
                    setTimeout(() => {
                        alert('Assignments saved successfully! The itinerary has been downloaded.');
                        location.reload();
                    }, 500);
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

        // Edit participant functionality
        function editParticipant(userData) {
            // Populate the modal with user data
            document.getElementById('editUserId').value = userData.id;
            document.getElementById('editName').value = userData.name;
            document.getElementById('editEmail').value = userData.email;
            document.getElementById('editPhone').value = userData.phone || '';
            document.getElementById('editAddress').value = userData.address;
            document.getElementById('editWillingToDrive').value = userData.willing_to_drive ? 'true' : 'false';

            // Show/hide driver fields based on willing_to_drive
            const driverFields = document.getElementById('editDriverFields');
            if (userData.willing_to_drive) {
                driverFields.style.display = 'block';
                document.getElementById('editVehicleCapacity').value = userData.vehicle_capacity || '';
                document.getElementById('editVehicleMake').value = userData.vehicle_make || '';
                document.getElementById('editVehicleModel').value = userData.vehicle_model || '';
                document.getElementById('editVehicleColor').value = userData.vehicle_color || '';
            } else {
                driverFields.style.display = 'none';
            }

            document.getElementById('editSpecialNotes').value = userData.special_notes || '';

            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('editParticipantModal'));
            modal.show();
        }

        // Handle willing to drive change in edit modal
        document.getElementById('editWillingToDrive').addEventListener('change', function() {
            const driverFields = document.getElementById('editDriverFields');
            if (this.value === 'true') {
                driverFields.style.display = 'block';
            } else {
                driverFields.style.display = 'none';
            }
        });

        // Save edited participant
        async function saveParticipant() {
            const formData = {
                id: document.getElementById('editUserId').value,
                name: document.getElementById('editName').value,
                email: document.getElementById('editEmail').value,
                phone: document.getElementById('editPhone').value,
                address: document.getElementById('editAddress').value,
                willing_to_drive: document.getElementById('editWillingToDrive').value === 'true',
                special_notes: document.getElementById('editSpecialNotes').value
            };

            if (formData.willing_to_drive) {
                formData.vehicle_capacity = document.getElementById('editVehicleCapacity').value;
                formData.vehicle_make = document.getElementById('editVehicleMake').value;
                formData.vehicle_model = document.getElementById('editVehicleModel').value;
                formData.vehicle_color = document.getElementById('editVehicleColor').value;
            }

            try {
                const response = await fetch('edit_participant.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();
                if (result.success) {
                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('editParticipantModal')).hide();

                    // Show success message
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success alert-dismissible fade show position-fixed top-50 start-50 translate-middle success-notification';
                    successAlert.style.zIndex = '9999';
                    successAlert.innerHTML = `
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle fa-2x me-3"></i>
                            <div>
                                <strong>Participant Updated</strong><br>
                                <small>${formData.name} has been updated successfully.</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(successAlert);

                    // Reload page after a short delay
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    alert('Error: ' + (result.message || 'Failed to update participant'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error updating participant');
            }
        }
    </script>

    <!-- Edit Participant Modal -->
    <div class="modal fade" id="editParticipantModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Participant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editUserId">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editName" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editName" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editEmail" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="editEmail" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editPhone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="editPhone">
                        </div>
                        <div class="col-md-6">
                            <label for="editWillingToDrive" class="form-label">Willing to Drive? <span class="text-danger">*</span></label>
                            <select class="form-select" id="editWillingToDrive" required>
                                <option value="true">Yes, I can drive</option>
                                <option value="false">No, I need a ride</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="editAddress" class="form-label">Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editAddress" required>
                    </div>

                    <!-- Driver-specific fields -->
                    <div id="editDriverFields" style="display: none;">
                        <div class="border rounded p-3 bg-light mb-3">
                            <h6 class="text-primary">Vehicle Information</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="editVehicleCapacity" class="form-label">Passenger Seats</label>
                                    <input type="number" class="form-control" id="editVehicleCapacity" min="1" max="12">
                                </div>
                                <div class="col-md-3">
                                    <label for="editVehicleMake" class="form-label">Make</label>
                                    <input type="text" class="form-control" id="editVehicleMake">
                                </div>
                                <div class="col-md-3">
                                    <label for="editVehicleModel" class="form-label">Model</label>
                                    <input type="text" class="form-control" id="editVehicleModel">
                                </div>
                                <div class="col-md-3">
                                    <label for="editVehicleColor" class="form-label">Color</label>
                                    <input type="text" class="form-control" id="editVehicleColor">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="editSpecialNotes" class="form-label">Special Notes</label>
                        <textarea class="form-control" id="editSpecialNotes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveParticipant()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>