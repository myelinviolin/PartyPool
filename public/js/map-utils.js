/**
 * Shared Map Utilities
 * These functions are used by both the home page and admin dashboard
 * Any changes here will automatically apply to both pages
 */

// Create the distinctive gold star event marker
function createEventMarker(lat, lng, eventName, eventAddress, eventDateTime) {
    // Ensure coordinates are numbers
    lat = parseFloat(lat);
    lng = parseFloat(lng);

    console.log('Creating event marker at:', lat, lng);

    // Validate coordinates are reasonable for Madison area
    if (isNaN(lat) || isNaN(lng) || lat < 42 || lat > 44 || lng < -91 || lng > -88) {
        console.error('Invalid coordinates:', lat, lng);
        return null;
    }

    // Create event marker icon with inline styles to avoid CSS conflicts
    const starIcon = L.divIcon({
        html: '<div style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #FFD700, #FFA500); border-radius: 50%; border: 3px solid #FF8C00; box-shadow: 0 3px 10px rgba(255, 215, 0, 0.3);"><i class="fas fa-star" style="color: white; font-size: 24px;"></i></div>',
        iconSize: [48, 48],
        iconAnchor: [24, 24],
        popupAnchor: [0, -24],
        className: ''  // No CSS classes to avoid conflicts
    });

    const marker = L.marker([lat, lng], { icon: starIcon });

    // Format date/time if provided as string
    if (eventDateTime && typeof eventDateTime === 'string') {
        if (eventDateTime.includes('T') || eventDateTime.includes(' ')) {
            eventDateTime = new Date(eventDateTime).toLocaleString();
        }
    }

    marker.bindPopup(`
        <div style="text-align: center;">
            <strong style="color: #FF8C00; font-size: 16px;">🎉 Party Location!</strong><br>
            <div style="margin-top: 8px;">
                <strong>${eventName}</strong><br>
                ${eventAddress}<br>
                ${eventDateTime || ''}
            </div>
        </div>
    `);

    return marker;
}

// Create driver marker (green car icon)
function createDriverMarker(lat, lng, name, address, vehicleInfo, capacity) {
    lat = parseFloat(lat);
    lng = parseFloat(lng);

    if (isNaN(lat) || isNaN(lng)) {
        console.error('Invalid driver coordinates:', lat, lng);
        return null;
    }

    const driverIcon = L.divIcon({
        html: '<div style="width: 36px; height: 36px; background: white; border-radius: 50%; box-shadow: 0 3px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; border: 3px solid #28a745;"><i class="fas fa-car" style="color: #28a745; font-size: 18px;"></i></div>',
        iconSize: [36, 36],
        iconAnchor: [18, 18],
        popupAnchor: [0, -18],
        className: ''
    });

    const marker = L.marker([lat, lng], { icon: driverIcon });

    let popupContent = `<strong>${name}</strong><br>${address}`;
    if (vehicleInfo) {
        popupContent += `<br>Vehicle: ${vehicleInfo}`;
    }
    if (capacity) {
        popupContent += `<br>Capacity: ${capacity} seats`;
    }

    marker.bindPopup(popupContent);
    return marker;
}

// Create rider marker (blue user icon)
function createRiderMarker(lat, lng, name, address) {
    lat = parseFloat(lat);
    lng = parseFloat(lng);

    if (isNaN(lat) || isNaN(lng)) {
        console.error('Invalid rider coordinates:', lat, lng);
        return null;
    }

    const riderIcon = L.divIcon({
        html: '<div style="width: 36px; height: 36px; background: white; border-radius: 50%; box-shadow: 0 3px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; border: 3px solid #17a2b8;"><i class="fas fa-user-friends" style="color: #17a2b8; font-size: 18px;"></i></div>',
        iconSize: [36, 36],
        iconAnchor: [18, 18],
        popupAnchor: [0, -18],
        className: ''
    });

    const marker = L.marker([lat, lng], { icon: riderIcon });
    marker.bindPopup(`<strong>${name}</strong><br>${address}`);
    return marker;
}

// Fit map to show all markers with padding
function fitMapToMarkers(map, markers, padding = 0.1) {
    if (!markers || markers.length === 0) return;

    const validMarkers = markers.filter(m => m && m.getLatLng);
    if (validMarkers.length === 0) return;

    const group = new L.featureGroup(validMarkers);
    map.fitBounds(group.getBounds().pad(padding));
}

// Export for use if needed (though these are global functions)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        createEventMarker,
        createDriverMarker,
        createRiderMarker,
        fitMapToMarkers
    };
}