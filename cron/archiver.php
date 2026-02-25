<?php
// cron/archiver.php - The Midnight Chat Cold-Storage Archiver

set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/../db.php';
define('CHATS_DIR', __DIR__ . '/../chats/');

$lockFile = CHATS_DIR . 'archiver.lock';
if (file_exists($lockFile) && time() - filemtime($lockFile) < 3600) {
    die("Archiver already ran recently.\n");
}
if (!is_dir(CHATS_DIR)) mkdir(CHATS_DIR, 0755, true);
file_put_contents($lockFile, time());

$db = new TursoDB();

// 1. Fetch exactly yesterday's messages
$yesterday = date('Y-m-d', strtotime('-1 day'));
$sql = "SELECT * FROM messages_v4 WHERE date(created_at) = ?";
$messages = $db->query($sql, [$yesterday]);

if (!empty($messages)) {
    // Group messages by room_id
    $grouped = [];
    foreach ($messages as $msg) {
        $roomId = $msg['room_id'];
        $grouped[$roomId][] = $msg;
    }

    // Write to Cold Storage JSON files
    foreach ($grouped as $roomId => $msgs) {
        $p1 = $roomId[0] ?? '0';
        $p2 = $roomId[1] ?? '0';
        $roomDir = CHATS_DIR . "$p1/$p2/$roomId/";
        
        if (!is_dir($roomDir)) mkdir($roomDir, 0755, true);
        
        $archiveFile = $roomDir . "chat-{$yesterday}.json";
        file_put_contents($archiveFile, json_encode($msgs, JSON_PRETTY_PRINT));
    }
    
    echo "Archived " . count($messages) . " messages into JSON cold storage.\n";
} else {
    echo "No messages to archive for $yesterday.\n";
}

// 2. Delete messages older than 30 days from TursoDB to free space
$db->query("DELETE FROM messages_v4 WHERE date(created_at) < date('now', '-30 days')");
echo "Cleaned TursoDB: Deleted messages older than 30 days.\n";

unlink($lockFile);
?>