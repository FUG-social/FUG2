const Chat = {
    pollInterval: null,
    unreadInterval: null,
    
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
    
    open: (id, name) => {
        State.activeChatId = id;
        document.getElementById('chat-room-name').innerText = name;
        document.getElementById('chat-list').classList.add('hidden');
        document.getElementById('chat-room').classList.remove('hidden');
        
        // Force navigate to Chat tab (useful if opened via Map)
        document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
        document.getElementById('view-chats').classList.add('active');
        document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
        
        if (Chat.pollInterval) clearInterval(Chat.pollInterval);
        Chat.pollInterval = setInterval(Chat.loadMessages, 1500);
        Chat.loadMessages();
    },
    
    close: () => {
        State.activeChatId = null;
        document.getElementById('chat-room').classList.add('hidden');
        document.getElementById('chat-list').classList.remove('hidden');
        if (Chat.pollInterval) clearInterval(Chat.pollInterval);
    },
    
    loadMessages: async () => {
        if (!State.activeChatId) return;
        const res = await State.api('get_messages', { other_user_id: State.activeChatId });
        if (res.status === 'success') {
            const area = document.getElementById('chat-messages');
            const html = res.data.map(m => `
                <div class="msg-bubble ${m.sender_id == State.userId ? 'msg-sent' : 'msg-received'}">
                    ${m.body}
                </div>
            `).join('');
            
            // Prevent blinking/unnecessary rendering
            if (area.innerHTML.replace(/\s+/g, '') !== html.replace(/\s+/g, '')) {
                const atBottom = area.scrollHeight - area.scrollTop <= area.clientHeight + 50;
                area.innerHTML = html;
                if (atBottom || area.innerHTML === '') area.scrollTop = area.scrollHeight;
            }
        }
    },
    
    send: async () => {
        const input = document.getElementById('chat-input');
        const body = input.value.trim();
        if (!body || !State.activeChatId) return;
        
        input.value = '';
        await State.api('send_message', { receiver_id: State.activeChatId, body });
        Chat.loadMessages(); // immediate load
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
