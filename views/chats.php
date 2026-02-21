<main id="view-chats" class="view">
    <h2>Conversations</h2>
    
    <!-- List View -->
    <div id="chat-list">Loading connections...</div>
    
    <!-- Room View -->
    <div id="chat-room" class="hidden">
        <button id="close-chat-btn">← Back to List</button>
        <h3 id="chat-room-name" style="margin: 10px 0;"></h3>
        
        <div id="chat-messages" style="height:300px; overflow-y:scroll; border:1px solid #ccc; padding:10px;"></div>
        
        <div style="display:flex; margin-top:5px;">
            <input type="text" id="chat-input" placeholder="Type..." style="flex:1;">
            <button id="send-msg-btn">Send</button>
        </div>
    </div>
</main>
