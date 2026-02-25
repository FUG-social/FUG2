// js/main.js

document.addEventListener('DOMContentLoaded', async () => {
    if (!State.userId) return; // Ignore if on login page

    // INITIALIZE INDEXED_DB FIRST
    try {
        await LocalDB.init();
        console.log("IndexedDB Initialized successfully");
    } catch (e) {
        console.error("Failed to initialize IndexedDB", e);
    }

    // Tab Navigation Logic
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            // Change Views
            document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
            const targetId = e.target.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');
            
            // Execute view-specific logic
            if (targetId === 'view-map') MapApp.init();
            if (targetId === 'view-logs') Logs.load();
        });
    });

    // Startup Execution
    Profile.initLocation();
    Chat.startUnreadPolling();
});