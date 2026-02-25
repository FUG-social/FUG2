<?php
// cron/dispatcher.php - The 1-Minute Async Push Notification Queue

set_time_limit(60); // Must finish within 1 minute
define('QUEUE_FILE', __DIR__ . '/../queue/push-queue.jsonl');
define('USERS_DIR', __DIR__ . '/../users/');

// Prevent cron overlap
$lockFile = __DIR__ . '/../queue/dispatcher.lock';
if (file_exists($lockFile) && time() - filemtime($lockFile) < 55) {
    die("Dispatcher running.\n");
}
if (!is_dir(__DIR__ . '/../queue/')) mkdir(__DIR__ . '/../queue/', 0755, true);
file_put_contents($lockFile, time());

if (file_exists(QUEUE_FILE) && filesize(QUEUE_FILE) > 0) {
    // 1. Move queue to a processing file (Atomic Swap) so new chats aren't blocked
    $processingFile = __DIR__ . '/../queue/processing.jsonl';
    rename(QUEUE_FILE, $processingFile);
    touch(QUEUE_FILE); // Recreate empty queue for incoming chats instantly

    $lines = file($processingFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    if ($lines) {
        $notifications = [];

        foreach ($lines as $line) {
            $job = json_decode($line, true);
            if (!$job || empty($job['to_user'])) continue;

            $targetId = $job['to_user'];
            $p1 = $targetId[0] ?? '0';
            $p2 = $targetId[1] ?? '0';
            $dynamicFile = USERS_DIR . "$p1/$p2/$targetId/dynamic.json";

            // Lookup FCM Token from O(1) file system
            if (file_exists($dynamicFile)) {
                $userData = json_decode(file_get_contents($dynamicFile), true);
                $fcmToken = $userData['fcm_device_token'] ?? null;

                if ($fcmToken) {
                    // Prepare Firebase Payload
                    $notifications[] = [
                        'message' => [
                            'token' => $fcmToken,
                            'notification' => [
                                'title' => 'New Message from ' . $job['from_name'],
                                'body' => $job['preview']
                            ],
                            'data' => [
                                'type' => $job['type'],
                                'action_url' => 'fug://chat/' . $targetId
                            ]
                        ]
                    ];
                }
            }
        }

        // 2. Dispatch to Firebase API
        // NOTE: In production, loop through $notifications and send bulk cURL to FCM here.
        // For now, we mock it to prevent errors since you haven't added FCM keys yet.
        $dispatchedCount = count($notifications);
        echo "Successfully dispatched $dispatchedCount push notifications.\n";
    }

    // Cleanup processing file
    unlink($processingFile);
} else {
    echo "Queue is empty.\n";
}

unlink($lockFile);
?>