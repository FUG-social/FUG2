window.mapInitialized = false;
let mapInstance = null;
let mapMarkers = [];

const MapApp = {
    init: () => {
        if (window.mapInitialized) { mapInstance.invalidateSize(); return; }
        mapInstance = L.map('radar-map', { zoomControl: false }).setView([State.lat, State.lng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapInstance);
        window.mapInitialized = true;
        MapApp.updateMarkers();
    },

    updateMarkers: () => {
        if (!window.mapInitialized) return;
        mapMarkers.forEach(m => mapInstance.removeLayer(m));
        mapMarkers = [];
        
        // Self
        mapMarkers.push(L.marker([State.lat, State.lng]).addTo(mapInstance).bindPopup('<b>You</b>'));
        
        // Match Users
        State.users.forEach(u => {
            const m = L.marker([u.lat, u.lng]).addTo(mapInstance);
            const matches = u.shared_interests ? u.shared_interests.length : 0;
            // FIX: Added quotes around '${u.id}'
            m.bindPopup(`
                <b>${u.name}</b><br>
                ${matches} shared interests<br>
                <hr style="margin:5px 0">
                <button onclick="Chat.open('${u.id}', '${u.name}')">Message Now</button>
            `);
            mapMarkers.push(m);
        });
    }
};