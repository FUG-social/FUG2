<?php
// RealtimeStore.php - Real-time location and interest storage using JSON file

require_once 'config.php';
require_once 'logger.php';

class RealtimeStore {
    private static $instance = null;
    private $filePath;
    private $logger;
    private $data;
    private $lastSave;
    
    private function __construct() {
        $this->filePath = LIVE_USERS_FILE;
        $this->logger = Logger::getInstance();
        $this->data = ['users' => [], 'last_updated' => time()];
        $this->lastSave = 0;
        
        $this->load();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Load data from JSON file
    private function load() {
        if (file_exists($this->filePath)) {
            $content = file_get_contents($this->filePath);
            if ($content) {
                $decoded = json_decode($content, true);
                if ($decoded !== null) {
                    $this->data = $decoded;
                    $this->logger->realtime('INFO', 'Loaded live_users.json', ['users_count' => count($this->data['users'] ?? [])]);
                }
            }
        }
    }
    
    // Save data to JSON file
    private function save() {
        // Throttle saves to max once per second
        $now = time();
        if ($now - $this->lastSave < 1) {
            return;
        }
        
        $this->data['last_updated'] = $now;
        $this->lastSave = $now;
        
        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->filePath, $json, LOCK_EX);
        
        $this->logger->realtime('DEBUG', 'Saved live_users.json', ['users_count' => count($this->data['users'] ?? [])]);
    }
    
    // Update or add a user
    public function updateUser($userId, $data) {
        if (!isset($this->data['users'])) {
            $this->data['users'] = [];
        }
        
        $userId = (string)$userId;
        
        // Get existing data or create new
        $existing = $this->data['users'][$userId] ?? [];
        
        // Merge with new data
        $this->data['users'][$userId] = array_merge($existing, $data, [
            'id' => $userId,
            'last_seen' => time(),
            'last_seen_formatted' => date('Y-m-d H:i:s')
        ]);
        
        $this->logger->realtime('INFO', 'User updated in realtime store', [
            'user_id' => $userId,
            'data' => $data
        ]);
        
        $this->save();
        return true;
    }
    
    // Get a specific user
    public function getUser($userId) {
        $userId = (string)$userId;
        return $this->data['users'][$userId] ?? null;
    }
    
    // Get all users
    public function getAllUsers() {
        return $this->data['users'] ?? [];
    }
    
    // Get users with shared interests
    public function getUsersWithSharedInterests($myInterests, $excludeUserId = null) {
        if (empty($myInterests)) {
            return [];
        }
        
        $myInterestsArray = is_array($myInterests) ? $myInterests : explode(',', $myInterests);
        $myInterestsArray = array_map('trim', $myInterestsArray);
        $myInterestsArray = array_map('strtolower', $myInterestsArray);
        
        $matches = [];
        
        foreach ($this->data['users'] as $userId => $user) {
            if ($excludeUserId && $userId == $excludeUserId) {
                continue;
            }
            
            $userInterests = $user['interests'] ?? '';
            if (empty($userInterests)) {
                continue;
            }
            
            $userInterestsArray = explode(',', $userInterests);
            $userInterestsArray = array_map('trim', $userInterestsArray);
            $userInterestsArray = array_map('strtolower', $userInterestsArray);
            
            // Find shared interests
            $shared = array_intersect($myInterestsArray, $userInterestsArray);
            
            if (!empty($shared)) {
                $user['shared_interests'] = array_values($shared);
                $user['match_score'] = count($shared);
                $matches[$userId] = $user;
            }
        }
        
        // Sort by match score (highest first)
        uasort($matches, function($a, $b) {
            return $b['match_score'] - $a['match_score'];
        });
        
        $this->logger->realtime('INFO', 'Found users with shared interests', [
            'my_interests' => $myInterestsArray,
            'matches_count' => count($matches)
        ]);
        
        return $matches;
    }
    
    // Remove inactive users (older than 5 minutes)
    public function cleanupInactiveUsers($maxAgeSeconds = 300) {
        $now = time();
        $removed = [];
        
        foreach ($this->data['users'] as $userId => $user) {
            $lastSeen = $user['last_seen'] ?? 0;
            if ($now - $lastSeen > $maxAgeSeconds) {
                $removed[] = $userId;
                unset($this->data['users'][$userId]);
            }
        }
        
        if (!empty($removed)) {
            $this->logger->realtime('INFO', 'Cleaned up inactive users', [
                'removed_count' => count($removed),
                'removed_ids' => $removed
            ]);
            $this->save();
        }
        
        return $removed;
    }
    
    // Remove a specific user
    public function removeUser($userId) {
        $userId = (string)$userId;
        if (isset($this->data['users'][$userId])) {
            unset($this->data['users'][$userId]);
            $this->logger->realtime('INFO', 'User removed from realtime store', ['user_id' => $userId]);
            $this->save();
            return true;
        }
        return false;
    }
    
    // Get stats
    public function getStats() {
        return [
            'total_users' => count($this->data['users'] ?? []),
            'last_updated' => $this->data['last_updated'] ?? time(),
            'last_updated_formatted' => date('Y-m-d H:i:s', $this->data['last_updated'] ?? time())
        ];
    }
    
    // Get raw data for debugging
    public function getRawData() {
        return $this->data;
    }
}