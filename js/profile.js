const Profile = {
    initLocation: () => {
        document.getElementById('location-status').innerText = 'Locating GPS...';
        if(navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                async pos => { 
                    State.lat = pos.coords.latitude; 
                    State.lng = pos.coords.longitude; 
                    await Profile.geocode(State.lat, State.lng);
                    Profile.sync(); 
                },
                async err => { 
                    await Profile.geocode(State.lat, State.lng);
                    Profile.sync(); 
                }
            );
        } else { 
            Profile.geocode(State.lat, State.lng).then(Profile.sync); 
        }
    },

    // NEW: Client-Side Reverse Geocoding
    geocode: async (lat, lng) => {
        try {
            document.getElementById('location-status').innerText = 'Resolving City...';
            const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=10&addressdetails=1`);
            const data = await res.json();
            State.country = (data.address?.country_code || 'unknown').toLowerCase();
            State.city = (data.address?.city || data.address?.town || data.address?.village || data.address?.county || 'unknown').toLowerCase().replace(/[^a-z0-9]/g, '');
        } catch(e) {
            console.log("Geocoding failed, using fallbacks.");
            State.country = 'unknown';
            State.city = 'unknown';
        }
    },

    sync: async () => {
        document.getElementById('location-status').innerText = `Location Set ✓ (${State.city}, ${State.country.toUpperCase()})`;
        // Pass city and country to backend for radar sharding
        await State.api('update_location', { lat: State.lat, lng: State.lng, city: State.city, country: State.country });
        Profile.loadUsers();
    },

    loadUsers: async () => {
        // Send city and country so backend knows which radar folder to look inside
        const res = await State.api('get_users', { city: State.city, country: State.country });
        if (res.status === 'success') {
            State.users = res.data;
            Chat.renderUserList();
            if (window.mapInitialized) MapApp.updateMarkers();
        }
    },

    save: async () => {
        const activity = document.getElementById('my-activity-input').value;
        const interests = document.getElementById('my-interests-input').value;
        
        await State.api('update_profile', { activity, interests });
        
        const ind = document.getElementById('save-status-indicator');
        ind.innerText = 'Saved ✓';
        setTimeout(() => ind.innerText = '', 2000);
        
        Profile.loadUsers(); 
    }
};
document.getElementById('save-profile-btn')?.addEventListener('click', Profile.save);