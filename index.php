<?php
ob_start(); 
session_start();
require_once 'db.php';
$db = new TursoDB();
$db->autoSetup();

// ID Generator for V2 Architecture
function generateInternalId() {
    return substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 6);
}

// Authentication Handling (Mocking Auth0 for Phase 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $email = trim($_POST['email'] ?? '');
        if ($email) {
            $users = $db->query("SELECT * FROM user_identity_v1 WHERE email = ? LIMIT 1", [$email]);
            
            if (empty($users)) {
                // New User: Generate 6-char ID and Mock Auth0 Sub
                $internalId = generateInternalId();
                $auth0Sub = 'mock|' . bin2hex(random_bytes(8)); 
                $db->query("INSERT INTO user_identity_v1 (auth0_sub, email, internal_id) VALUES (?, ?, ?)", 
                    [$auth0Sub, $email, $internalId]
                );
                $_SESSION['user_id'] = $internalId;
            } else {
                // Existing User
                $_SESSION['user_id'] = $users[0]['internal_id'];
            }
            
            $_SESSION['user_name'] = explode('@', $email)[0]; // Fallback name for now

            // FIX: Read profile from dynamic.json to restore UI state on login
            $p1 = $_SESSION['user_id'][0] ?? '0';
            $p2 = $_SESSION['user_id'][1] ?? '0';
            $dynamicFile = __DIR__ . "/users/$p1/$p2/{$_SESSION['user_id']}/dynamic.json";
            
            if (file_exists($dynamicFile)) {
                $dyn = json_decode(file_get_contents($dynamicFile), true);
                $_SESSION['user_activity'] = $dyn['activity'] ?? '';
                if (isset($dyn['interests']) && is_array($dyn['interests'])) {
                    $flat = array_merge($dyn['interests']['core'] ?? [], $dyn['interests']['craft'] ?? [], $dyn['interests']['orbit'] ?? []);
                    $_SESSION['user_interests'] = implode(', ', $flat);
                }
            }

            session_regenerate_id(true);
        }
        header("Location: index.php"); 
        exit;
    } elseif ($_POST['action'] === 'logout') {
        session_destroy();
        header("Location: index.php"); 
        exit;
    }
}

$isLoggedIn = isset($_SESSION['user_id']);

if ($isLoggedIn && empty($_SESSION['api_key'])) {
    $_SESSION['api_key'] = bin2hex(random_bytes(16)); 
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FUG - V2 Build</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        body { font-family: sans-serif; margin: 15px; }
        .hidden { display: none !important; }
        .view { display: none; }
        .view.active { display: block; }
        nav { margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #ccc; }
        button { padding: 5px 10px; cursor: pointer; }
        input { padding: 5px; margin: 5px 0; }
        hr { border: 0.5px solid #eee; margin: 15px 0; }
        #radar-map { height: 400px; width: 100%; border: 1px solid #ccc; }
        .msg-bubble { padding: 8px; margin: 5px 0; border-radius: 4px; }
        .msg-sent { background: #e3f2fd; text-align: right; }
        .msg-received { background: #f5f5f5; }
    </style>
</head>
<body>
    <?php if (!$isLoggedIn): include 'views/login.php'; else: ?>
        <!-- Note: current-user-id is now a 6-char string like 'af1012' -->
        <meta name="current-user-id" content="<?= $_SESSION['user_id'] ?>">
        <meta name="api-key" content="<?= $_SESSION['api_key'] ?>">
        
        <nav>
            <button class="nav-btn" data-target="view-home">Home</button>
            <button class="nav-btn" data-target="view-map">Map</button>
            <button class="nav-btn" data-target="view-chats">Chats (<span id="chat-badge" style="color:red;font-weight:bold;">0</span>)</button>
            <button class="nav-btn" data-target="view-logs">Logs</button>
            <form method="POST" style="display:inline;"><input type="hidden" name="action" value="logout"><button>Logout</button></form>
        </nav>

        <?php 
            include 'views/home.php'; 
            include 'views/map.php'; 
            include 'views/chats.php'; 
            include 'views/logs.php'; 
        ?>

        <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
        <script src="js/state.js"></script>
        <script src="js/profile.js"></script>
        <script src="js/map.js"></script>
        <script src="js/chat.js"></script>
        <script src="js/logs.js"></script>
        <script src="js/main.js"></script>
    <?php endif; ?>
</body>
</html>