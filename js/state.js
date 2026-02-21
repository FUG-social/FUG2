const State = {
    userId: document.querySelector('meta[name="current-user-id"]')?.content,
    lat: 29.3956, 
    lng: 71.6836,
    users: [],
    activeChatId: null,

    api: async (action, data = {}) => {
        data.action = action;
        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            return await res.json();
        } catch (e) { console.error('API Error:', e); return { status: 'error' }; }
    }
};
