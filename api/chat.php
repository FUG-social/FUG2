<?php
checkAuth();

if ($action === 'send_message') {
    $db->query("INSERT INTO ".TBL_MSGS." (sender_id, receiver_id, body) VALUES (?, ?, ?)", 
        [$_SESSION['user_id'], $input['receiver_id'], trim($input['body'])]
    );
    echo json_encode(['status' => 'success']);
}

if ($action === 'get_messages') {
    $me = $_SESSION['user_id'];
    $other = $input['other_user_id'];
    $messages = $db->query(
        "SELECT * FROM ".TBL_MSGS." WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at DESC LIMIT 50", 
        [$me, $other, $other, $me]
    );
    echo json_encode(['status' => 'success', 'data' => is_array($messages) ? array_reverse($messages) : []]);
}

if ($action === 'get_unread_count') {
    $res = $db->query("SELECT sender_id, COUNT(*) as count FROM ".TBL_MSGS." WHERE receiver_id = ? GROUP BY sender_id", [$_SESSION['user_id']]);
    $counts = [];
    if (is_array($res)) {
        foreach ($res as $row) $counts[$row['sender_id']] = (int)$row['count'];
    }
    echo json_encode(['status' => 'success', 'data' => $counts]);
}
