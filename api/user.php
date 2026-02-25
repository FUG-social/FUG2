<?php
// api/user.php - Phase 3: JSONL Radar Engine

checkAuth();
$userId = $_SESSION['user_id']; 

function getUserDir($id) {
    $p1 = $id[0] ?? '0';
    $p2 = $id[1] ?? '0';
    $path = __DIR__ . "/../users/$p1/$p2/$id/";
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    return $path;
}

$userDir = getUserDir($userId);
$staticFile = $userDir . 'static.json';
$dynamicFile = $userDir . 'dynamic.json';

if (!file_exists($staticFile)) {
    $staticData = [
        'id' => $userId,
        'name' => $_SESSION['user_name'] ?? 'User',
        'joined_at' => date('c')
    ];
    file_put_contents($staticFile, json_encode($staticData, JSON_PRETTY_PRINT));
}

// ---------------------------------------------------------
// ACTION: UPDATE PROFILE
// ---------------------------------------------------------
if ($action === 'update_profile') {
    $activity = $input['activity'] ?? '';
    $interestsStr = $input['interests'] ?? '';
    
    $interestsArray = array_map('trim', explode(',', $interestsStr));
    $interestsArray = array_filter($interestsArray);
    
    $tieredInterests = [
        'core' => array_slice($interestsArray, 0, 2),
        'craft' => array_slice($interestsArray, 2, 2),
        'orbit' => array_slice($interestsArray, 4)
    ];

    $dynamicData = file_exists($dynamicFile) ? json_decode(file_get_contents($dynamicFile), true) : [];
    
    $dynamicData['status'] = 'online';
    $dynamicData['activity'] = $activity;
    $dynamicData['interests'] = $tieredInterests;
    $dynamicData['last_seen_timestamp'] = time();

    file_put_contents($dynamicFile, json_encode($dynamicData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $_SESSION['user_activity'] = $activity;
    $_SESSION['user_interests'] = $interestsStr;

    echo json_encode(['status' => 'success', 'message' => 'Profile saved']);
    exit;
}

// ---------------------------------------------------------
// ACTION: UPDATE LOCATION (Micro-Sharded Appender)
// ---------------------------------------------------------
if ($action === 'update_location') {
    $lat = isset($input['lat']) ? (float)$input['lat'] : DEFAULT_LAT;
    $lng = isset($input['lng']) ? (float)$input['lng'] : DEFAULT_LNG;
    $country = preg_replace('/[^a-z0-9]/', '', strtolower($input['country'] ?? 'unknown'));
    $city = preg_replace('/[^a-z0-9]/', '', strtolower($input['city'] ?? 'unknown'));
    
    $fuzzLat = round($lat, 3);
    $fuzzLng = round($lng, 3);

    // 1. Update Personal Dynamic File
    $dynamicData = file_exists($dynamicFile) ? json_decode(file_get_contents($dynamicFile), true) : [];
    $dynamicData['current_location'] = ['lat' => $fuzzLat, 'lng' => $fuzzLng, 'fuzzified' => true];
    $dynamicData['last_seen_timestamp'] = time();
    file_put_contents($dynamicFile, json_encode($dynamicData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // 2. Append to Radar Shard (O(1) operation)
    $shard = $userId[0] ?? '0'; 
    $radarDir = __DIR__ . "/../radar/$country/$city";
    if (!is_dir($radarDir)) {
        mkdir($radarDir, 0755, true);
    }
    
    $pingData = [
        'id' => $userId,
        'name' => $_SESSION['user_name'] ?? 'User',
        'lat' => $fuzzLat,
        'lng' => $fuzzLng,
        'interests' => $_SESSION['user_interests'] ?? '', 
        'time' => time()
    ];
    
    // Append a single JSON line to the live shard. Zero database lag.
    file_put_contents("$radarDir/$shard-live.jsonl", json_encode($pingData) . "\n", FILE_APPEND | LOCK_EX);

    echo json_encode(['status' => 'success']);
    exit;
}

// ---------------------------------------------------------
// ACTION: GET USERS (Read from JSONL Shards)
// ---------------------------------------------------------
if ($action === 'get_users') {
    $country = preg_replace('/[^a-z0-9]/', '', strtolower($input['country'] ?? 'unknown'));
    $city = preg_replace('/[^a-z0-9]/', '', strtolower($input['city'] ?? 'unknown'));
    
    $radarDir = __DIR__ . "/../radar/$country/$city";
    $activeUsers = [];
    $now = time();
    
    $myInterests = array_map('trim', explode(',', strtolower($_SESSION['user_interests'] ?? '')));
    $myInterests = array_filter($myInterests);

    // Read all shards in the user's city
    if (is_dir($radarDir)) {
        $files = glob("$radarDir/*-live.jsonl");
        foreach ($files as $file) {
            // Read file line by line
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $data = json_decode($line, true);
                if ($data && ($now - $data['time'] <= 300)) { // Only users active in last 5 mins
                    if ($data['id'] === $userId) continue; 
                    
                    // Overwrites duplicates, keeping only the latest ping for a user ID
                    $activeUsers[$data['id']] = $data;
                }
            }
        }
    }

    // Calculate Shared Interests
    $matches = [];
    foreach ($activeUsers as $id => $u) {
        $uInterests = array_map('trim', explode(',', strtolower($u['interests'] ?? '')));
        $shared = array_intersect($myInterests, $uInterests);
        
        if (!empty($shared)) {
            $u['shared_interests'] = array_values($shared);
            $u['match_score'] = count($shared);
            $matches[] = $u;
        }
    }

    // Sort by highest matches first
    usort($matches, function($a, $b) {
        return $b['match_score'] <=> $a['match_score'];
    });

    echo json_encode(['status' => 'success', 'data' => $matches]);
    exit;
}