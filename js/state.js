const State = {
    userId: document.querySelector('meta[name="current-user-id"]')?.content,
    lat: 29.3956, 
    lng: 71.6836,
    city: 'unknown',
    country: 'unknown',
    users: [],
    activeChatId: null,

    api: async (action, data = {}) => {
        data.action = action;
        
        const rawJsonString = JSON.stringify(data);
        const encodedPayload = btoa(unescape(encodeURIComponent(rawJsonString)));
        
        const secureData = {
            _encoded_payload: encodedPayload
        };

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(secureData) 
            });
            return await res.json();
        } catch (e) { 
            console.error('API Error:', e); 
            return { status: 'error' }; 
        }
    }
};