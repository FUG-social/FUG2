const State = {
    userId: document.querySelector('meta[name="current-user-id"]')?.content,
    apiKey: document.querySelector('meta[name="api-key"]')?.content,
    lat: 29.3956, 
    lng: 71.6836,
    users: [],
    activeChatId: null,

    api: async (action, data = {}) => {
        data.action = action;
        let payload, headers;

        // SECURE PAYLOAD ENCODING (Only works if logged in & key exists)
        if (State.apiKey) {
            const str = JSON.stringify(data);
            let encoded = '';
            // Forward XOR Cipher
            for(let i = 0; i < str.length; i++) {
                encoded += String.fromCharCode(str.charCodeAt(i) ^ State.apiKey.charCodeAt(i % State.apiKey.length));
            }
            payload = btoa(encoded); // Base64 safe transport
            headers = { 'Content-Type': 'text/plain' }; // Mask as plain text to avoid auto-parsers
        } else {
            // Standard JSON fallback
            payload = JSON.stringify(data);
            headers = { 'Content-Type': 'application/json' };
        }

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: headers,
                body: payload
            });
            return await res.json();
        } catch (e) { 
            console.error('API Error:', e); 
            return { status: 'error' }; 
        }
    }
};