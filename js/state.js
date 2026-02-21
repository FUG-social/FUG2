const State = {
    userId: document.querySelector('meta[name="current-user-id"]')?.content,
    lat: 29.3956, 
    lng: 71.6836,
    users: [],
    activeChatId: null,

    api: async (action, data = {}) => {
        data.action = action;
        
        // WORKAROUND: Encode the payload so it's not exposed as raw JSON in the Network tab.
        const rawJsonString = JSON.stringify(data);
        // Encode securely handling Unicode characters
        const encodedPayload = btoa(unescape(encodeURIComponent(rawJsonString)));
        
        const secureData = {
            _encoded_payload: encodedPayload
        };

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(secureData) // Sending the encoded wrapper
            });
            return await res.json();
        } catch (e) { 
            console.error('API Error:', e); 
            return { status: 'error' }; 
        }
    }
};