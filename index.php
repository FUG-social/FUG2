<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';
$db = new TursoDB();
$db->autoSetup();

// Authentication Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $email = $_POST['email'] ?? '';
        $users = $db->query("SELECT * FROM users_v3 WHERE email = ? LIMIT 1", [$email]);
        if (!empty($users)) {
            $_SESSION['user_id'] = $users[0]['id'];
            $_SESSION['user_name'] = $users[0]['name'];
            $_SESSION['user_activity'] = $users[0]['activity'] ?? '';
            $_SESSION['user_interests'] = $users[0]['interests'] ?? '';
            session_regenerate_id(true);
        }
        header("Location: index.php"); exit;
    } elseif ($_POST['action'] === 'logout') {
        session_destroy();
        header("Location: index.php"); exit;
    }
}
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FUG - Minimal</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
        <meta name="current-user-id" content="<?= $_SESSION['user_id'] ?>">
        
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

        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="js/state.js"></script>
        <script src="js/profile.js"></script>
        <script src="js/map.js"></script>
        <script src="js/chat.js"></script>
        <script src="js/logs.js"></script>
        <script src="js/main.js"></script>
    <?php endif; ?>
</body>
</html>
