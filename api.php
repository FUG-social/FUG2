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

// --- SMART OBFUSCATION DECODER START ---
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

// If standard JSON fails, attempt to decrypt the custom secure payload
if (!$input && !empty($rawInput) && isset($_SESSION['api_key'])) {
    $decoded64 = base64_decode($rawInput);
    if ($decoded64 !== false) {
        $key = $_SESSION['api_key'];
        $decryptedStr = '';
        $keyLen = strlen($key);
        $dataLen = strlen($decoded64);
        
        // Reverse XOR Cipher
        for ($i = 0; $i < $dataLen; $i++) {
            $decryptedStr .= chr(ord($decoded64[$i]) ^ ord($key[$i % $keyLen]));
        }
        
        $decodedJson = json_decode($decryptedStr, true);
        if ($decodedJson !== null) {
            $input = $decodedJson;
        }
    }
}

// Fallback to standard POST for initial unauthenticated calls
$input = $input ?? $_POST;
$action = $input['action'] ?? '';
// --- SMART OBFUSCATION DECODER END ---

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