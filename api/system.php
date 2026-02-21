<?php
if ($action === 'login_api') {
    $users = $db->query("SELECT * FROM ".TBL_USERS." WHERE email = ? LIMIT 1", [$input['email'] ?? '']);
    if (!empty($users)) {
        $_SESSION['user_id'] = $users[0]['id'];
        $_SESSION['user_name'] = $users[0]['name'];
        $_SESSION['user_interests'] = $users[0]['interests'];
        echo json_encode(['status' => 'success', 'user' => $users[0]]);
    } else {
        echo json_encode(['status' => 'error']);
    }
}

if ($action === 'get_logs') {
    echo json_encode(['status' => 'success', 'data' => $logger->getLogs($input['type'] ?? 'all', 50)]);
}
