// Party Carpool Application - FINAL FIX
let map;
let markers = [];
let routeMarkers = []; // Track route markers separately
let eventMarker = null; // Track event marker separately
let eventData = {};
let currentEventId = 1;

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    console.log('App starting...');
    initMap();
    loadEventData().then(() => {
        loadUsers();
        loadCarpools();
    });
    setupEventListeners();
    setInterval(refreshData, 30000); // Refresh every 30 seconds

    // Check if we should show the register section (from admin or direct link)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('register') === 'true') {
        setTimeout(() => {
            showSection('register');
        }, 100);
    }
});

// Initialize Leaflet Map
function initMap() {
    // Initialize empty map centered on Madison
    map = L.map('map').setView([43.0731, -89.4012], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    console.log('Map initialized');
}

// Load event data
async function loadEventData() {
    try {
        const response = await fetch(`/api/events.php?id=${currentEventId}`);
        const data = await response.json();
        eventData = data;

        console.log('Event data loaded:', data);

        // Update event header - show message if event details not complete
        if (data.event_name && data.event_address) {
            document.getElementById('eventName').textContent = data.event_name;
            document.getElementById('eventDateTime').textContent =
                new Date(data.event_date + ' ' + data.event_time).toLocaleString();
            document.getElementById('eventLocation').textContent = data.event_address;
        } else {
            document.getElementById('eventName').textContent = 'Event Details Not Set';
            document.getElementById('eventDateTime').textContent = 'Admin needs to configure event';
            document.getElementById('eventLocation').textContent = 'Please contact admin';
        }

        // Update participation stats with correct data
        document.getElementById('totalRegistered').textContent = data.total_participants || 0;
        document.getElementById('availableSeats').textContent = data.total_vehicle_capacity || 0;
        document.getElementById('driverCount').textContent = data.total_drivers || 0;
        document.getElementById('optimizationStatus').textContent = data.optimization_status === 'completed' ? 'Completed' : 'Pending';

        // Handle event marker
        if (data.event_lat && data.event_lng) {
            // Remove old event marker if it exists
            if (eventMarker && map.hasLayer(eventMarker)) {
                map.removeLayer(eventMarker);
                eventMarker = null;
            }

            // Create and add new event marker
            const dateTimeStr = new Date(data.event_date + ' ' + data.event_time).toLocaleString();
            eventMarker = createEventMarker(
                data.event_lat,
                data.event_lng,
                data.event_name,
                data.event_address,
                dateTimeStr
            );

            if (eventMarker) {
                eventMarker.addTo(map);
                SharedMapDisplay.addParticipantMarker('event', eventMarker); // Register with shared module
                console.log('Event marker added to map');

                // Center map on event location on first load
                if (!window.mapCentered) {
                    map.setView([parseFloat(data.event_lat), parseFloat(data.event_lng)], 14);
                    window.mapCentered = true;
                }
            }
        } else {
            // No location set - show message on map
            if (!document.getElementById('noLocationMessage')) {
                const messageDiv = document.createElement('div');
                messageDiv.id = 'noLocationMessage';
                messageDiv.style.cssText = 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1000; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); text-align: center;';
                messageDiv.innerHTML = `
                    <i class="fas fa-map-marked-alt" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                    <h5>Party Location Not Set</h5>
                    <p class="text-muted mb-0">The admin has not yet set the party destination location.</p>
                `;
                document.getElementById('map').appendChild(messageDiv);
            }
        }

    } catch (error) {
        console.error('Error loading event data:', error);
    }
}

// Load all users
async function loadUsers() {
    try {
        const response = await fetch(`/api/users.php?event_id=${currentEventId}`);
        const users = await response.json();

        // Clear existing USER markers only (not event marker!)
        markers.forEach(marker => map.removeLayer(marker));
        markers = [];

        // Remove "no location" message if it exists
        const noLocationMsg = document.getElementById('noLocationMessage');
        if (noLocationMsg) {
            noLocationMsg.remove();
        }

        // Separate willing drivers and those needing rides for map display
        const drivers = users.filter(u => u.willing_to_drive);
        const riders = users.filter(u => !u.willing_to_drive);

        let totalSeats = 0;

        // Add driver markers to map with cute styling
        drivers.forEach(driver => {
            if (driver.lat && driver.lng) {
                const driverMarker = L.marker([parseFloat(driver.lat), parseFloat(driver.lng)], {
                    icon: L.divIcon({
                        html: '<i class="fas fa-car"></i>',
                        iconSize: [36, 36],
                        className: 'custom-marker driver-marker',
                        iconAnchor: [18, 18],
                        popupAnchor: [0, -18]
                    })
                }).addTo(map);

                driverMarker.bindPopup(`
                    <div class="popup-header" style="color: #28a745;">
                        <i class="fas fa-car"></i> ${driver.name}
                    </div>
                    <div class="badge bg-success mb-2">Willing to Drive</div>
                    <div class="popup-info"><i class="fas fa-envelope"></i> ${driver.email}</div>
                    <div class="popup-info"><i class="fas fa-phone"></i> ${driver.phone || 'N/A'}</div>
                    <div class="popup-info"><i class="fas fa-chair"></i> <strong>${driver.vehicle_capacity || 4} seats available</strong></div>
                    ${driver.vehicle_make ? `<div class="popup-info"><i class="fas fa-car-side"></i> ${driver.vehicle_make} ${driver.vehicle_model || ''}</div>` : ''}
                    <div class="popup-info"><i class="fas fa-home"></i> ${driver.address || 'Madison, WI'}</div>
                `);

                markers.push(driverMarker);
                participantMarkers[driver.id] = driverMarker; // Store reference by ID
                SharedMapDisplay.addParticipantMarker(driver.id, driverMarker); // Register with shared module
            }

            totalSeats += parseInt(driver.vehicle_capacity) || 0;
        });

        document.getElementById('availableSeats').textContent = totalSeats;

        // Add rider markers to map with cute styling
        riders.forEach(rider => {
            if (rider.lat && rider.lng) {
                const riderMarker = L.marker([parseFloat(rider.lat), parseFloat(rider.lng)], {
                    icon: L.divIcon({
                        html: '<i class="fas fa-user-friends"></i>',
                        iconSize: [36, 36],
                        className: 'custom-marker rider-marker',
                        iconAnchor: [18, 18],
                        popupAnchor: [0, -18]
                    })
                }).addTo(map);

                riderMarker.bindPopup(`
                    <div class="popup-header" style="color: #17a2b8;">
                        <i class="fas fa-user-friends"></i> ${rider.name}
                    </div>
                    <div class="badge bg-info mb-2">Needs a Ride</div>
                    <div class="popup-info"><i class="fas fa-envelope"></i> ${rider.email}</div>
                    <div class="popup-info"><i class="fas fa-phone"></i> ${rider.phone || 'N/A'}</div>
                    <div class="popup-info"><i class="fas fa-home"></i> ${rider.address || 'Madison, WI'}</div>
                    ${rider.special_notes ? `<div class="popup-info"><i class="fas fa-comment"></i> ${rider.special_notes}</div>` : ''}
                `);

                markers.push(riderMarker);
                participantMarkers[rider.id] = riderMarker; // Store reference by ID
                SharedMapDisplay.addParticipantMarker(rider.id, riderMarker); // Register with shared module
            }
        });

        // Update stats chart
        updateStatsChart(drivers.length, riders.length);

        // Re-add event marker if needed (in case it got removed somehow)
        if (eventData.event_lat && eventData.event_lng) {
            if (!eventMarker || !map.hasLayer(eventMarker)) {
                const dateTimeStr = new Date(eventData.event_date + ' ' + eventData.event_time).toLocaleString();
                eventMarker = createEventMarker(
                    eventData.event_lat,
                    eventData.event_lng,
                    eventData.event_name,
                    eventData.event_address,
                    dateTimeStr
                );
                if (eventMarker) {
                    eventMarker.addTo(map);
                    SharedMapDisplay.addParticipantMarker('event', eventMarker); // Register with shared module
                }
            }
        }

        // Fit map bounds to show everything
        if (markers.length > 0 || eventMarker) {
            const allMarkersToShow = [...markers];
            if (eventMarker) {
                allMarkersToShow.push(eventMarker);
            }

            if (allMarkersToShow.length > 0) {
                const group = new L.featureGroup(allMarkersToShow);
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }

    } catch (error) {
        console.error('Error loading users:', error);
    }
}

// Load optimization results and display carpool assignments
async function loadCarpools() {
    try {
        const response = await fetch(`/api/optimization-results.php?event_id=${currentEventId}`);
        const data = await response.json();

        if (data.optimization_exists && data.routes && data.routes.length > 0) {
            // Update optimization status
            document.getElementById('optimizationStatus').textContent = 'Completed';

            // Display carpool assignments in a simple format
            displayCarpoolAssignments(data.routes);

            // Add route markers to the map
            displayRoutesOnMap(data.routes);

            // Calculate and display vehicle miles saved
            displayMilesSaved(data.routes);
        } else {
            // No optimization yet
            document.getElementById('optimizationStatus').textContent = 'Pending';
            displayNoOptimizationMessage();
        }
    } catch (error) {
        console.error('Error loading optimization results:', error);
    }
}

// Display carpool assignments
function displayCarpoolAssignments(routes) {
    const container = document.querySelector('.container.my-5');

    // Find or create assignments section
    let assignmentsSection = document.getElementById('carpoolAssignments');
    if (!assignmentsSection) {
        assignmentsSection = document.createElement('div');
        assignmentsSection.id = 'carpoolAssignments';
        assignmentsSection.className = 'card shadow mb-4';
        assignmentsSection.innerHTML = `
            <div class="card-body">
                <h5 class="card-title mb-4">
                    <i class="fas fa-route text-primary"></i> Carpool Assignments
                </h5>
                <div id="assignmentsList"></div>
            </div>
        `;

        // Insert after the map section
        const mapCard = document.querySelector('#map').closest('.card');
        if (mapCard) {
            mapCard.parentElement.insertBefore(assignmentsSection, mapCard.nextSibling);
        }
    }

    // Build assignments HTML
    let html = '<div class="row">';
    routes.forEach((route, index) => {
        html += `
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-car"></i> ${route.driver_name}
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            <strong>Vehicle:</strong> ${route.vehicle}<br>
                            <strong>Departure:</strong> ${route.departure_time}<br>
                            <strong>Route Distance:</strong> ${route.total_distance} miles
                        </p>
                        ${route.passengers && route.passengers.length > 0 ? `
                            <h6 class="mt-3">Passengers:</h6>
                            <ul class="list-unstyled">
                                ${route.passengers.map(p => `
                                    <li class="mb-1">
                                        <i class="fas fa-user text-info"></i> ${p.name}
                                        ${p.pickup_time ? `<span class="text-muted ms-2">(Pickup: ${p.pickup_time})</span>` : ''}
                                    </li>
                                `).join('')}
                            </ul>
                        ` : `
                            <div class="mt-3 text-success">
                                <i class="fas fa-route"></i> <strong>Driving directly to destination</strong>
                            </div>
                        `}
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';

    document.getElementById('assignmentsList').innerHTML = html;
}

// Display message when no optimization exists
function displayNoOptimizationMessage() {
    const container = document.querySelector('.container.my-5');

    let messageSection = document.getElementById('carpoolAssignments');
    if (!messageSection) {
        messageSection = document.createElement('div');
        messageSection.id = 'carpoolAssignments';
        messageSection.className = 'card shadow mb-4';
        messageSection.innerHTML = `
            <div class="card-body text-center">
                <i class="fas fa-clock text-warning" style="font-size: 48px;"></i>
                <h5 class="mt-3">Optimization Pending</h5>
                <p class="text-muted">The admin has not yet run the carpool optimization. Please check back later.</p>
            </div>
        `;

        const mapCard = document.querySelector('#map').closest('.card');
        if (mapCard) {
            mapCard.parentElement.insertBefore(messageSection, mapCard.nextSibling);
        }
    }
}

// Store route lines globally for interaction
let routeLines = [];
let selectedRouteIndex = null;
let participantMarkers = {}; // Store references to all participant markers by ID

// Display routes on the map with interactive selector using shared module
function displayRoutesOnMap(routes) {
    // Use the shared display function
    SharedMapDisplay.displayRoutesOnMap(routes, map);

    // Sync our local variables with the shared module
    routeLines = window.routeLines;
    selectedRouteIndex = window.selectedRouteIndex;
    participantMarkers = window.participantMarkers;
}

// Wrapper function to call shared module's createRouteSelector
function createRouteSelector() {
    SharedMapDisplay.createRouteSelector(map);
    return; // Early return to skip duplicate code

    // Legacy code below kept for reference but not executed
    /*
    // Remove existing selector if present
    const existingSelector = document.querySelector('.route-selector-control');
    if (existingSelector) {
        existingSelector.remove();
    }

    // Create selector control
    const selectorControl = L.control({position: 'topright'});

    selectorControl.onAdd = function() {
        const container = L.DomUtil.create('div', 'route-selector-control');

        // Build selector HTML
        let html = `
            <div class="route-selector-panel">
                <div class="route-selector-header">
                    <h6>Routes</h6>
                </div>
                <div class="route-selector-list">
        `;

        routeLines.forEach((route, index) => {
            const routeNum = index + 1;

            html += `
                <div class="route-selector-item" data-route-index="${index}">
                    <span class="route-number" style="background-color: ${route.color}; color: white; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">${routeNum}</span>
                    <span class="driver-name">${route.driverName}</span>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;

        container.innerHTML = html;

        // Prevent map interactions when clicking the selector
        L.DomEvent.disableClickPropagation(container);
        L.DomEvent.disableScrollPropagation(container);

        // Remove all focus outlines and blue highlights
        container.style.outline = 'none';
        container.style.border = 'none';
        container.style.boxShadow = 'none';

        // Remove focus outline from all child elements
        const removeOutlines = (element) => {
            element.style.outline = 'none';
            element.style.border = 'none';
            element.style.boxShadow = 'none';
            element.style.webkitTapHighlightColor = 'transparent';
            element.setAttribute('tabindex', '-1');
        };

        // Apply to container and all descendants
        removeOutlines(container);
        container.querySelectorAll('*').forEach(removeOutlines);

        // Add click handlers to route items
        container.querySelectorAll('.route-selector-item').forEach(item => {
            item.addEventListener('click', function() {
                const routeIndex = parseInt(this.dataset.routeIndex);
                toggleRoute(routeIndex);
            });

            // Prevent focus on click
            item.addEventListener('mousedown', function(e) {
                e.preventDefault();
            });
        });

        return container;
    };

    selectorControl.addTo(map);
    routeMarkers.push(selectorControl);
    */
}

// Wrapper function to call shared module's toggleRoute
function toggleRoute(index) {
    SharedMapDisplay.toggleRoute(index);
    return; // Early return to skip duplicate code

    // Legacy code below kept for reference but not executed
    /*
    // If clicking the currently selected route, show all routes
    if (selectedRouteIndex === index) {
        showAllRoutes();
        showAllParticipants();
        selectedRouteIndex = null;

        // Remove active class from all items
        document.querySelectorAll('.route-selector-item').forEach(item => {
            item.classList.remove('active');
        });
    } else {
        // Hide all routes except the selected one
        routeLines.forEach((route, i) => {
            if (i === index) {
                route.mainLine.setStyle({ opacity: 0.9 });
                route.shadowLine.setStyle({ opacity: 0.8 });
            } else {
                route.mainLine.setStyle({ opacity: 0 });
                route.shadowLine.setStyle({ opacity: 0 });
            }
        });

        // Hide all participant markers first
        Object.values(participantMarkers).forEach(marker => {
            if (marker && map.hasLayer(marker)) {
                marker.setOpacity(0);
            }
        });

        // Show only participants involved in this route
        const selectedRoute = routeLines[index];
        selectedRoute.participantIds.forEach(id => {
            if (participantMarkers[id]) {
                participantMarkers[id].setOpacity(1);
            }
        });

        selectedRouteIndex = index;

        // Update active state in selector
        document.querySelectorAll('.route-selector-item').forEach((item, i) => {
            if (i === index) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        // Fit map to selected route
        const bounds = routeLines[index].mainLine.getBounds();
        map.fitBounds(bounds, { padding: [50, 50] });
    }
    */
}

// Wrapper function to call shared module's showAllParticipants
function showAllParticipants() {
    SharedMapDisplay.showAllParticipants();
}

// Wrapper function to call shared module's showAllRoutes
function showAllRoutes() {
    SharedMapDisplay.showAllRoutes();
}

// Setup event listeners
function setupEventListeners() {
    // Willing to drive change
    const willingSelect = document.getElementById('willingToDrive');
    if (willingSelect) {
        willingSelect.addEventListener('change', function() {
            const driverFields = document.getElementById('driverFields');
            if (this.value === 'true') {
                driverFields.style.display = 'block';
            } else {
                driverFields.style.display = 'none';
            }
        });
    }

    // Registration form
    const regForm = document.getElementById('registrationForm');
    if (regForm) {
        regForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const willing = document.getElementById('willingToDrive').value === 'true';

            // Validate vehicle capacity if willing to drive
            if (willing) {
                const seats = document.getElementById('availableSeatsInput').value;
                if (!seats || seats < 1 || seats > 12) {
                    showAlert('Please enter the number of available seats for passengers (1-12)', 'warning');
                    document.getElementById('availableSeatsInput').focus();
                    return;
                }
            }

            // Geocode the address automatically
            const address = document.getElementById('address').value;
            let lat = null, lng = null;

            try {
                const geoResponse = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`);
                const geoData = await geoResponse.json();
                if (geoData && geoData.length > 0) {
                    lat = parseFloat(geoData[0].lat);
                    lng = parseFloat(geoData[0].lon);
                }
            } catch (error) {
                console.error('Geocoding error:', error);
            }

            const formData = {
                name: document.getElementById('name').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value,
                willing_to_drive: willing,
                address: address,
                lat: lat,
                lng: lng,
                event_id: currentEventId,
                special_notes: document.getElementById('driverNotes')?.value || ''
            };

            if (willing) {
                formData.vehicle_capacity = parseInt(document.getElementById('availableSeatsInput').value);
                formData.vehicle_make = document.getElementById('carMake').value;
                formData.vehicle_model = document.getElementById('carModel').value;
                formData.vehicle_color = document.getElementById('carColor').value;
            }

            try {
                const response = await fetch('/api/users.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (response.ok) {
                    showAlert('Registration successful!', 'success');
                    document.getElementById('registrationForm').reset();
                    loadUsers();
                    showSection('home');
                } else {
                    showAlert(result.message || 'Registration failed', 'danger');
                }
            } catch (error) {
                showAlert('Error during registration', 'danger');
                console.error('Registration error:', error);
            }
        });
    }
}

// Show different sections
function showSection(section) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(sec => {
        sec.style.display = 'none';
    });

    // Show selected section
    const targetSection = document.getElementById(section + 'Section');
    if (targetSection) {
        targetSection.style.display = 'block';
    }

    // Update active nav link
    document.querySelectorAll('.navbar-nav .nav-link').forEach(link => {
        link.classList.remove('active');
        // Mark the link as active if it points to this section
        if (link.getAttribute('onclick') && link.getAttribute('onclick').includes(`'${section}'`)) {
            link.classList.add('active');
        }
    });

    // Invalidate map size if showing home
    if (section === 'home') {
        setTimeout(() => {
            map.invalidateSize();
            // Re-ensure event marker is visible after map resize
            if (eventData.event_lat && eventData.event_lng && eventMarker) {
                if (!map.hasLayer(eventMarker)) {
                    eventMarker.addTo(map);
                }
            }
        }, 100);
    }
}

// Make showSection available globally for onclick handlers in HTML
window.showSection = showSection;

// Calculate and display vehicle miles saved
function displayMilesSaved(routes) {
    const container = document.querySelector('.container.my-5') || document.querySelector('.container');

    // Find or create miles saved section
    let milesSection = document.getElementById('milesSaved');
    if (!milesSection) {
        milesSection = document.createElement('div');
        milesSection.id = 'milesSaved';
        milesSection.className = 'card shadow mb-4';

        // Find the carpool assignments section to insert after it
        const carpoolSection = document.getElementById('carpoolAssignments');
        if (carpoolSection && carpoolSection.parentElement) {
            carpoolSection.parentElement.insertBefore(milesSection, carpoolSection.nextSibling);
        } else if (container) {
            container.appendChild(milesSection);
        }
    }

    // Prepare data structure for all participants
    const participantMiles = new Map();
    let totalMilesSaved = 0;

    // Process each route
    routes.forEach(route => {
        const driverId = route.driver_id;
        const driverName = route.driver_name;
        const driverDirectDistance = parseFloat(route.direct_distance) || 0;
        const driverActualDistance = parseFloat(route.total_distance) || 0;

        // Driver's miles difference (negative if driving more)
        const driverMilesDiff = driverDirectDistance - driverActualDistance;
        participantMiles.set(driverId || driverName, {
            name: driverName,
            directMiles: driverDirectDistance,
            actualMiles: driverActualDistance,
            savedMiles: driverMilesDiff,
            role: 'driver'
        });

        // Process passengers
        if (route.passengers && route.passengers.length > 0) {
            route.passengers.forEach(passenger => {
                const passengerId = passenger.id || passenger.name;
                const passengerDirectDistance = parseFloat(passenger.direct_distance) || driverDirectDistance; // Assume same as driver if not provided

                // Passenger saves all their miles (they don't drive at all)
                participantMiles.set(passengerId, {
                    name: passenger.name,
                    directMiles: passengerDirectDistance,
                    actualMiles: 0, // Passengers don't drive
                    savedMiles: passengerDirectDistance,
                    role: 'passenger'
                });
            });
        }
    });

    // Calculate total miles saved
    participantMiles.forEach(participant => {
        totalMilesSaved += participant.savedMiles;
    });

    // Sort participants by miles saved (descending)
    const sortedParticipants = Array.from(participantMiles.values()).sort((a, b) => b.savedMiles - a.savedMiles);

    // Build the HTML
    let html = `
        <div class="card-body">
            <h5 class="card-title mb-4">
                <i class="fas fa-gas-pump text-success"></i> Vehicle Miles Saved
            </h5>

            <div class="alert ${totalMilesSaved >= 0 ? 'alert-success' : 'alert-warning'} mb-4">
                <h4 class="alert-heading">
                    <i class="fas fa-chart-line"></i> Total Miles Saved:
                    <strong>${totalMilesSaved.toFixed(1)} miles</strong>
                </h4>
                <p class="mb-0">
                    ${totalMilesSaved >= 0 ?
                        `By carpooling, participants collectively saved ${totalMilesSaved.toFixed(1)} vehicle miles!` :
                        `The carpool arrangement resulted in ${Math.abs(totalMilesSaved).toFixed(1)} additional miles overall.`
                    }
                </p>
            </div>

            <h6 class="mb-3">Individual Breakdown:</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Direct Distance</th>
                            <th>Actual Distance</th>
                            <th>Miles Saved</th>
                        </tr>
                    </thead>
                    <tbody>
    `;

    sortedParticipants.forEach(participant => {
        const savedClass = participant.savedMiles >= 0 ? 'text-success' : 'text-danger';
        const roleIcon = participant.role === 'driver' ?
            '<i class="fas fa-car text-primary"></i>' :
            '<i class="fas fa-user text-info"></i>';

        html += `
            <tr>
                <td>${participant.name}</td>
                <td>${roleIcon} ${participant.role === 'driver' ? 'Driver' : 'Passenger'}</td>
                <td>${participant.directMiles.toFixed(1)} mi</td>
                <td>${participant.actualMiles.toFixed(1)} mi</td>
                <td class="${savedClass}">
                    <strong>${participant.savedMiles >= 0 ? '+' : ''}${participant.savedMiles.toFixed(1)} mi</strong>
                </td>
            </tr>
        `;
    });

    html += `
                    </tbody>
                </table>
            </div>

            <div class="mt-3 text-muted small">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> Passengers who cannot drive are counted as if they would have driven themselves.
                Negative values indicate drivers who travel extra distance for pickups.
                ${totalMilesSaved < 0 ? `
                <br><br>
                <i class="fas fa-lightbulb text-warning"></i>
                <strong>Why are total miles increased?</strong> This is normal and expected! When drivers pick up multiple passengers,
                they must detour from their direct route, adding miles. However, the environmental benefit of reducing vehicles by
                ${Math.round((1 - (routes.length / sortedParticipants.length)) * 100)}% far outweighs the small mileage increase.
                Fewer vehicles means less emissions from cold starts, reduced parking needs, and decreased traffic congestion.
                The ${Math.abs(totalMilesSaved).toFixed(1)} extra miles are a small price for eliminating
                ${sortedParticipants.length - routes.length} vehicles from the road!
                ` : ''}
            </div>
        </div>
    `;

    milesSection.innerHTML = html;
}

// Update stats chart - removed as chart is no longer displayed
function updateStatsChart(drivers, riders) {
    // Chart has been removed from the UI
    // This function is kept to avoid errors from existing calls
    // Stats are now shown as text only in the Participation box
    return;
}

// Show alert messages
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
}

// Refresh data
function refreshData() {
    loadEventData().then(() => {
        loadUsers();
        loadCarpools();
    });
}

// Log for debugging
console.log('App.js loaded successfully');