const Chat = {
    unreadInterval: null, 
    
    // --- Local Cache Helpers ---
    getCacheKey: (userId) => `chat_history_${State.userId}_${userId}`,
    getCache: (userId) => JSON.parse(localStorage.getItem(Chat.getCacheKey(userId)) || '[]'),
    setCache: (userId, data) => localStorage.setItem(Chat.getCacheKey(userId), JSON.stringify(data)),
    
    renderUserList: () => {
        const list = document.getElementById('chat-list');
        if (!list) return;
        list.innerHTML = State.users.map(u => `
            <div style="border-bottom:1px solid #eee; padding:10px; cursor:pointer;" onclick="Chat.open(${u.id}, '${u.name}')">
                <b>${u.name}</b> <small>(${u.shared_interests?.length || 0} matches)</small>
                <span id="unread-${u.id}" style="color:red; font-weight:bold; display:none;">(New)</span>
            </div>
        `).join('') || "No users found.";
    },
    
    open: async (id, name) => {
        State.activeChatId = id;
        document.getElementById('chat-room-name').innerText = name;
        document.getElementById('chat-list').classList.add('hidden');
        document.getElementById('chat-room').classList.remove('hidden');
        
        document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
        document.getElementById('view-chats').classList.add('active');
        document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
        
        // 1. PURE CSR: Render immediately from Local Cache. Zero latency.
        const cachedMessages = Chat.getCache(id);
        Chat.renderMessagesHTML(cachedMessages);
        
        // 2. "AT ONCE" API INVOLVEMENT: Sync with database exactly ONCE upon opening. 
        // No background looping for active chat.
        const res = await State.api('get_messages', { other_user_id: id });
        if (res.status === 'success') {
            Chat.setCache(id, res.data);
            Chat.renderMessagesHTML(res.data);
        }
    },
    
    close: () => {
        State.activeChatId = null;
        document.getElementById('chat-room').classList.add('hidden');
        document.getElementById('chat-list').classList.remove('hidden');
    },
    
    // Abstracted Rendering Logic for CSR
    renderMessagesHTML: (messages) => {
        const area = document.getElementById('chat-messages');
        if (!messages || messages.length === 0) {
            area.innerHTML = '<div style="text-align:center; color:#aaa; margin-top:20px;">No messages yet.</div>';
            return;
        }

        const html = messages.map(m => `
            <div class="msg-bubble ${m.sender_id == State.userId ? 'msg-sent' : 'msg-received'}">
                ${m.body}
            </div>
        `).join('');
        
        // Only update DOM if changes occurred (prevents blinking)
        if (area.innerHTML.replace(/\s+/g, '') !== html.replace(/\s+/g, '')) {
            const atBottom = area.scrollHeight - area.scrollTop <= area.clientHeight + 50;
            area.innerHTML = html;
            if (atBottom || area.innerHTML === '') area.scrollTop = area.scrollHeight;
        }
    },
    
    send: async () => {
        const input = document.getElementById('chat-input');
        const body = input.value.trim();
        const receiverId = State.activeChatId;
        if (!body || !receiverId) return;
        
        input.value = '';
        
        // 1. PURE CSR: Push to local cache and render instantly. NO waiting for PHP.
        const localData = Chat.getCache(receiverId);
        localData.push({
            sender_id: State.userId,
            receiver_id: receiverId,
            body: body
        });
        Chat.setCache(receiverId, localData);
        Chat.renderMessagesHTML(localData);
        
        // 2. "AT ONCE" API INVOLVEMENT: Send to database silently in the background once.
        await State.api('send_message', { receiver_id: receiverId, body });
    },
    
    startUnreadPolling: () => {
        Chat.checkUnread();
        Chat.unreadInterval = setInterval(Chat.checkUnread, 5000);
    },
    
    checkUnread: async () => {
        const res = await State.api('get_unread_count');
        if (res.status === 'success') {
            let total = 0;
            Object.entries(res.data).forEach(([uid, count]) => {
                total += count;
                const el = document.getElementById(`unread-${uid}`);
                if (el) el.style.display = count > 0 ? 'inline' : 'none';
            });
            const badge = document.getElementById('chat-badge');
            if (badge) badge.innerText = total > 0 ? total : '0';
        }
    }
};

document.getElementById('close-chat-btn')?.addEventListener('click', Chat.close);
document.getElementById('send-msg-btn')?.addEventListener('click', Chat.send);
document.getElementById('chat-input')?.addEventListener('keypress', (e) => { if (e.key === 'Enter') Chat.send(); });