<?php
session_start();
require_once 'db.php';
require_once 'logger.php';
require_once 'RealtimeStore.php';

header('Content-Type: application/json');

define('TBL_USERS', 'users_v3');
define('TBL_MSGS', 'messages_v3');

$logger = Logger::getInstance();
$realtimeStore = RealtimeStore::getInstance();
$db = new TursoDB();
$db->autoSetup();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? '';

if (!$action) {
    echo json_encode(['status' => 'error', 'message' => 'No action']);
    exit;
}

// Authentication Check
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
        exit;
    }
}

// Logical Routing
$chatActions = ['send_message', 'get_messages', 'get_unread_count'];
$userActions = ['update_location', 'update_profile', 'get_users'];
$sysActions  = ['login_api', 'get_logs', 'clear_logs'];

if (in_array($action, $chatActions)) {
    require_once 'api/chat.php';
} elseif (in_array($action, $userActions)) {
    require_once 'api/user.php';
} elseif (in_array($action, $sysActions)) {
    require_once 'api/system.php';
} else {
    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
}
