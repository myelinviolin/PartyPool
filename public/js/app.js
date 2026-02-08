// Party Carpool Application - FINAL FIX
let map;
let markers = [];
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

// Load carpool matches (simplified for stats only)
async function loadCarpools() {
    // This function is kept minimal since we removed the matches display
    // It can be extended later if needed for other purposes
    try {
        const response = await fetch(`/api/carpools.php?event_id=${currentEventId}`);
        const carpools = await response.json();
        // Could update optimization status here if needed
    } catch (error) {
        console.error('Error loading carpools:', error);
    }
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
    });
    if (event.target) {
        event.target.classList.add('active');
    }

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