/**
 * Shared Map Display Module
 * Used by both home page and admin dashboard for consistent map display
 */

// High contrast colors for better visibility - expanded to 20 unique colors
const ROUTE_COLORS = [
    '#FF0000', // Bright Red
    '#0066FF', // Bright Blue
    '#00CC00', // Bright Green
    '#FF6600', // Orange
    '#9900FF', // Purple
    '#FF0099', // Magenta
    '#00CCCC', // Cyan
    '#FFCC00', // Gold
    '#663300', // Brown
    '#FF66CC', // Pink
    '#0099CC', // Teal
    '#99CC00', // Lime
    '#FF3333', // Light Red
    '#3366FF', // Light Blue
    '#33CC33', // Light Green
    '#FF9933', // Light Orange
    '#CC33FF', // Light Purple
    '#FF33CC', // Light Magenta
    '#33CCCC', // Light Cyan
    '#FFFF33'  // Light Yellow
];

// Global variables for route interaction
window.routeLines = [];
window.selectedRouteIndex = null;
window.participantMarkers = {};
window.routeMarkers = window.routeMarkers || [];

/**
 * Display routes on the map with interactive legend
 * @param {Array} routes - Array of route objects
 * @param {Object} map - Leaflet map instance
 */
function displayRoutesOnMap(routes, map) {
    // Clear previous route markers only
    if (window.routeMarkers) {
        window.routeMarkers.forEach(marker => {
            if (map.hasLayer(marker)) {
                map.removeLayer(marker);
            }
        });
    }
    window.routeMarkers = [];

    // Clear previous route lines
    window.routeLines = [];
    window.selectedRouteIndex = null;

    // Create route objects
    routes.forEach((route, index) => {
        if (route.coordinates && route.coordinates.length > 1) {
            const color = ROUTE_COLORS[index % ROUTE_COLORS.length];
            const hasPax = route.passengers && route.passengers.length > 0;

            // Draw white shadow line for visibility
            const shadowLine = L.polyline(route.coordinates, {
                color: '#FFFFFF',
                weight: 7,
                opacity: 0.8
            }).addTo(map);
            window.routeMarkers.push(shadowLine);

            // Create main colored route line
            const routeLine = L.polyline(route.coordinates, {
                color: color,
                weight: 5,
                opacity: 0.9,
                smoothFactor: 1,
                dashArray: hasPax ? null : '10, 10' // Dashed for solo drivers
            }).addTo(map);
            window.routeMarkers.push(routeLine);

            // Add popup with route info
            const driverName = route.driver_name.replace(/Driver \d+ - /, '');
            const paxCount = route.passengers ? route.passengers.length : 0;
            const popupText = paxCount > 0 ?
                `<b>${driverName}</b><br>${paxCount} passenger(s)` :
                `<b>${driverName}</b><br>Solo driver`;
            routeLine.bindPopup(popupText);

            // Store route line data for interaction
            const participantIds = [route.driver_id];
            if (route.passengers) {
                route.passengers.forEach(pax => {
                    participantIds.push(pax.id);
                });
            }

            window.routeLines.push({
                mainLine: routeLine,
                shadowLine: shadowLine,
                color: color,
                driverName: driverName,
                paxCount: paxCount,
                hasPax: hasPax,
                index: index,
                participantIds: participantIds
            });
        }
    });

    // Create interactive route selector
    createRouteSelector(map);
}

/**
 * Create the route selector panel (legend) on the right side
 */
function createRouteSelector(map) {
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

        window.routeLines.forEach((route, index) => {
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
    window.routeMarkers.push(selectorControl);
}

/**
 * Toggle route visibility when clicked in legend
 */
function toggleRoute(index) {
    // If clicking the currently selected route, show all routes
    if (window.selectedRouteIndex === index) {
        showAllRoutes();
        showAllParticipants();
        window.selectedRouteIndex = null;

        // Remove active class from all items
        document.querySelectorAll('.route-selector-item').forEach(item => {
            item.classList.remove('active');
        });
    } else {
        // Hide all routes except the selected one
        window.routeLines.forEach((route, i) => {
            if (i === index) {
                route.mainLine.setStyle({ opacity: 0.9 });
                route.shadowLine.setStyle({ opacity: 0.8 });
            } else {
                route.mainLine.setStyle({ opacity: 0 });
                route.shadowLine.setStyle({ opacity: 0 });
            }
        });

        // Hide all participant markers first
        Object.values(window.participantMarkers).forEach(marker => {
            if (marker && marker.setOpacity) {
                marker.setOpacity(0);
            }
        });

        // Show only participants involved in this route
        const selectedRoute = window.routeLines[index];
        selectedRoute.participantIds.forEach(id => {
            if (window.participantMarkers[id]) {
                window.participantMarkers[id].setOpacity(1);
            }
        });

        // Also show the event marker
        if (window.participantMarkers['event']) {
            window.participantMarkers['event'].setOpacity(1);
        }

        window.selectedRouteIndex = index;

        // Add active class to selected item
        document.querySelectorAll('.route-selector-item').forEach((item, i) => {
            if (i === index) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    }
}

/**
 * Show all routes
 */
function showAllRoutes() {
    window.routeLines.forEach(route => {
        route.mainLine.setStyle({ opacity: 0.9 });
        route.shadowLine.setStyle({ opacity: 0.8 });
    });
}

/**
 * Show all participant markers
 */
function showAllParticipants() {
    Object.values(window.participantMarkers).forEach(marker => {
        if (marker && marker.setOpacity) {
            marker.setOpacity(1);
        }
    });
}

/**
 * Store a participant marker for later reference
 */
function addParticipantMarker(id, marker) {
    window.participantMarkers[id] = marker;
}

/**
 * Clear all participant markers
 */
function clearParticipantMarkers() {
    window.participantMarkers = {};
}

// Export functions for use in other files
window.SharedMapDisplay = {
    displayRoutesOnMap,
    createRouteSelector,
    toggleRoute,
    showAllRoutes,
    showAllParticipants,
    addParticipantMarker,
    clearParticipantMarkers,
    ROUTE_COLORS
};