const Profile = {
    initLocation: () => {
        if(navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                pos => { State.lat = pos.coords.latitude; State.lng = pos.coords.longitude; Profile.sync(); },
                err => { Profile.sync(); } // Fallback to defaults
            );
        } else { Profile.sync(); }
    },

    sync: async () => {
        document.getElementById('location-status').innerText = 'Location Set ✓';
        await State.api('update_location', { lat: State.lat, lng: State.lng });
        Profile.loadUsers();
    },

    loadUsers: async () => {
        const res = await State.api('get_users');
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
        
        Profile.loadUsers(); // Refresh matches
    }
};
document.getElementById('save-profile-btn')?.addEventListener('click', Profile.save);
