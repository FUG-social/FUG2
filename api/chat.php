<?php
// api/chat.php - Phase 5: V2 Chat Engine (Room IDs & Alphanumeric Support)

checkAuth();
$userId = (string)$_SESSION['user_id']; // e.g., 'af1012'

// Helper: Generate Alphabetical Room ID
function getRoomId($user1, $user2) {
    $users = [$user1, $user2];
    sort($users); // Sorts alphabetically: 'af1012', 'bc9999'
    return implode('_', $users); // Results in 'af1012_bc9999'
}

// ---------------------------------------------------------
// ACTION: SEND MESSAGE
// ---------------------------------------------------------
if ($action === 'send_message') {
    $receiverId = (string)($input['receiver_id'] ?? '');
    $body = trim($input['body'] ?? '');
    
    if (!$receiverId || !$body) {
        echo json_encode(['status' => 'error', 'message' => 'Missing data']);
        exit;
    }
    
    $roomId = getRoomId($userId, $receiverId);
    
    // Insert into Turso DB (messages_v4)
    global $db;
    $db->query(
        "INSERT INTO messages_v4 (room_id, sender_id, receiver_id, body) VALUES (?, ?, ?, ?)", 
        [$roomId, $userId, $receiverId, $body]
    );

    // Get the ID of the inserted message (SQLite gets highest ID in room as workaround)
    $lastMsg = $db->query("SELECT id, created_at FROM messages_v4 WHERE room_id = ? ORDER BY id DESC LIMIT 1", [$roomId]);

    // O(1) Push Notification Queue (Prep for Phase 6)
    $queueDir = __DIR__ . '/../queue/';
    if (!is_dir($queueDir)) mkdir($queueDir, 0755, true);
    
    $pushData = [
        'to_user' => $receiverId,
        'from_name' => $_SESSION['user_name'] ?? 'Someone',
        'type' => 'chat',
        'preview' => substr($body, 0, 50),
        'time' => time()
    ];
    file_put_contents($queueDir . 'push-queue.jsonl', json_encode($pushData) . "\n", FILE_APPEND | LOCK_EX);
    
    echo json_encode(['status' => 'success', 'data' => $lastMsg[0] ?? []]);
    exit;
}

// ---------------------------------------------------------
// ACTION: GET MESSAGES (Sync)
// ---------------------------------------------------------
if ($action === 'get_messages') {
    $otherUserId = (string)($input['other_user_id'] ?? '');
    if (!$otherUserId) {
        echo json_encode(['status' => 'error', 'message' => 'Missing user id']);
        exit;
    }
    
    $roomId = getRoomId($userId, $otherUserId);
    
    global $db;
    $messages = $db->query(
        "SELECT id, sender_id, receiver_id, body, created_at FROM messages_v4 WHERE room_id = ? ORDER BY id ASC", 
        [$roomId]
    );
    
    echo json_encode(['status' => 'success', 'data' => $messages]);
    exit;
}

// ---------------------------------------------------------
// ACTION: GET UNREAD COUNT
// ---------------------------------------------------------
if ($action === 'get_unread_count') {
    global $db;
    // Count messages sent TO me, grouped by sender
    $unread = $db->query(
        "SELECT sender_id, COUNT(*) as count FROM messages_v4 WHERE receiver_id = ? GROUP BY sender_id", 
        [$userId]
    );
    
    $counts = [];
    foreach ($unread as $row) {
        $counts[$row['sender_id']] = (int)$row['count'];
    }
    
    echo json_encode(['status' => 'success', 'data' => $counts]);
    exit;
}