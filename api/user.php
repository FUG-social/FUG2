<?php
checkAuth();

if ($action === 'update_location') {
    $lat = $input['lat'] ?? 0;
    $lng = $input['lng'] ?? 0;
    
    $db->query("UPDATE ".TBL_USERS." SET lat = ?, lng = ?, last_active = CURRENT_TIMESTAMP WHERE id = ?", [$lat, $lng, $_SESSION['user_id']]);
    $realtimeStore->updateUser($_SESSION['user_id'], ['name' => $_SESSION['user_name'], 'lat' => $lat, 'lng' => $lng]);
    echo json_encode(['status' => 'success']);
}

if ($action === 'update_profile') {
    $activity = htmlspecialchars($input['activity'] ?? '');
    $interests = htmlspecialchars($input['interests'] ?? '');
    
    $db->query("UPDATE ".TBL_USERS." SET activity = ?, interests = ? WHERE id = ?", [$activity, $interests, $_SESSION['user_id']]);
    $_SESSION['user_activity'] = $activity;
    $_SESSION['user_interests'] = $interests;
    $realtimeStore->updateUser($_SESSION['user_id'], ['activity' => $activity, 'interests' => $interests]);
    echo json_encode(['status' => 'success']);
}

if ($action === 'get_users') {
    $myInterests = $_SESSION['user_interests'] ?? '';
    $users = $db->query("SELECT id, name, email, lat, lng, activity, interests FROM ".TBL_USERS." WHERE id != ?", [$_SESSION['user_id']]);
    
    $filteredUsers = [];
    $myInterestsArray = array_filter(array_map('trim', array_map('strtolower', explode(',', $myInterests))));
    
    if (is_array($users)) {
        foreach ($users as $user) {
            $uInts = array_filter(array_map('trim', array_map('strtolower', explode(',', $user['interests'] ?? ''))));
            $shared = array_values(array_intersect($myInterestsArray, $uInts));
            $user['shared_interests'] = $shared;
            $user['match_score'] = count($shared);
            $filteredUsers[] = $user;
        }
    }
    
    usort($filteredUsers, function($a, $b) { return $b['match_score'] - $a['match_score']; });
    echo json_encode(['status' => 'success', 'data' => $filteredUsers]);
}
