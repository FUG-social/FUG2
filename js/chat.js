// js/chat.js - Phase 5: IndexedDB Chat Engine

const LocalDB = {
    db: null,
    init: async () => {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open('FugChatDB', 1);
            req.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains('messages')) {
                    const store = db.createObjectStore('messages', { keyPath: 'id' });
                    store.createIndex('room_id', 'room_id', { unique: false });
                }
            };
            req.onsuccess = (e) => { LocalDB.db = e.target.result; resolve(); };
            req.onerror = (e) => reject(e);
        });
    },
    getRoomId: (u1, u2) => {
        return [u1.toString(), u2.toString()].sort().join('_');
    },
    getMessages: async (roomId) => {
        return new Promise((resolve) => {
            const tx = LocalDB.db.transaction('messages', 'readonly');
            const store = tx.objectStore('messages');
            const index = store.index('room_id');
            const req = index.getAll(roomId);
            req.onsuccess = () => resolve(req.result || []);
        });
    },
    saveBulk: async (messages) => {
        return new Promise((resolve) => {
            const tx = LocalDB.db.transaction('messages', 'readwrite');
            const store = tx.objectStore('messages');
            messages.forEach(m => store.put(m)); // put overwrites if ID exists (prevents duplicates)
            tx.oncomplete = () => resolve();
        });
    },
    saveSingle: async (msg) => {
        return new Promise((resolve) => {
            const tx = LocalDB.db.transaction('messages', 'readwrite');
            tx.objectStore('messages').put(msg);
            tx.oncomplete = () => resolve();
        });
    }
};

const Chat = {
    unreadInterval: null, 
    
    renderUserList: () => {
        const list = document.getElementById('chat-list');
        if (!list) return;
        list.innerHTML = State.users.map(u => `
            <div style="border-bottom:1px solid #eee; padding:10px; cursor:pointer;" onclick="Chat.open('${u.id}', '${u.name}')">
                <b>${u.name}</b> <small>(${u.shared_interests?.length || 0} matches)</small>
                <span id="unread-${u.id}" style="color:red; font-weight:bold; display:none;">(New)</span>
            </div>
        `).join('') || "No users found.";
    },
    
    open: async (otherId, name) => {
        State.activeChatId = otherId.toString();
        const roomId = LocalDB.getRoomId(State.userId, State.activeChatId);

        document.getElementById('chat-room-name').innerText = name;
        document.getElementById('chat-list').classList.add('hidden');
        document.getElementById('chat-room').classList.remove('hidden');
        
        document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
        document.getElementById('view-chats').classList.add('active');
        document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
        
        // 1. PURE CSR: Render immediately from IndexedDB. Zero latency.
        const cachedMessages = await LocalDB.getMessages(roomId);
        Chat.renderMessagesHTML(cachedMessages);
        
        // 2. "AT ONCE" API INVOLVEMENT: Sync missing DB messages in the background
        const res = await State.api('get_messages', { other_user_id: State.activeChatId });
        if (res.status === 'success' && res.data.length > 0) {
            // Attach room_id to incoming data for IndexedDB indexing
            const formattedData = res.data.map(m => ({...m, room_id: roomId}));
            await LocalDB.saveBulk(formattedData);
            
            // Re-render if DB had new messages
            const updatedMessages = await LocalDB.getMessages(roomId);
            Chat.renderMessagesHTML(updatedMessages);
        }
    },
    
    close: () => {
        State.activeChatId = null;
        document.getElementById('chat-room').classList.add('hidden');
        document.getElementById('chat-list').classList.remove('hidden');
    },
    
    renderMessagesHTML: (messages) => {
        const area = document.getElementById('chat-messages');
        if (!messages || messages.length === 0) {
            area.innerHTML = '<div style="text-align:center; color:#aaa; margin-top:20px;">No messages yet.</div>';
            return;
        }

        // Sort just in case IDB returned them out of order
        messages.sort((a, b) => parseInt(a.id) - parseInt(b.id));

        const html = messages.map(m => `
            <div class="msg-bubble ${m.sender_id === State.userId ? 'msg-sent' : 'msg-received'}">
                ${m.body}
            </div>
        `).join('');
        
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
        const roomId = LocalDB.getRoomId(State.userId, receiverId);
        
        // Create optimistic local message
        const tempId = 'local_' + Date.now();
        const localMsg = {
            id: tempId,
            room_id: roomId,
            sender_id: State.userId,
            receiver_id: receiverId,
            body: body,
            created_at: new Date().toISOString()
        };

        // 1. PURE CSR: Save to IDB and render instantly
        await LocalDB.saveSingle(localMsg);
        const currentMessages = await LocalDB.getMessages(roomId);
        Chat.renderMessagesHTML(currentMessages);
        
        // 2. API INVOLVEMENT: Send to PHP
        const res = await State.api('send_message', { receiver_id: receiverId, body });
        
        // 3. Update local IDB with the real database ID so it doesn't duplicate on next sync
        if (res.status === 'success' && res.data) {
            localMsg.id = res.data.id; 
            await LocalDB.saveSingle(localMsg);
            
            // Delete the temporary local record
            const tx = LocalDB.db.transaction('messages', 'readwrite');
            tx.objectStore('messages').delete(tempId);
        }
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