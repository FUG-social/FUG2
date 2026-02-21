<?php
// db.php - Fixed: Forces Text Mode for Stability & V3 Tables
require_once 'config.php';
require_once 'logger.php';

class TursoDB {
    private $url;
    private $token;
    private $logger;

    public function __construct() {
        $this->url = TURSO_URL;
        $this->token = TURSO_TOKEN;
        $this->logger = Logger::getInstance();
    }

    public function query($sql, $params = []) {
        $startTime = microtime(true);
        
        $args = [];
        foreach ($params as $param) {
            // FIX: Send EVERYTHING as text. 
            // SQLite is weakly typed and handles "1" (text) into INTEGER columns perfectly.
            // This fixes the mismatch where PHP sends Int but DB has Text.
            if (is_null($param)) {
                $args[] = ['type' => 'null', 'value' => null];
            } else {
                $args[] = ['type' => 'text', 'value' => (string)$param];
            }
        }

        $payload = json_encode([
            'requests' => [
                [
                    'type' => 'execute',
                    'stmt' => [
                        'sql' => $sql,
                        'args' => $args
                    ]
                ]
            ]
        ]);

        $ch = curl_init($this->url . '/v2/pipeline');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        if (curl_errno($ch)) {
            $this->logger->database('ERROR', 'Curl error', ['msg' => curl_error($ch)]);
            return false;
        }

        // FIX: Only call curl_close if it's a legacy resource (PHP 7). 
        // In PHP 8+, it's an object and cleans itself up, avoiding the deprecation warning.
        if (is_resource($ch)) {
            curl_close($ch);
        }

        if ($httpCode >= 400) {
            $this->logger->database('ERROR', 'Database HTTP Error', ['code' => $httpCode, 'res' => $response]);
            return false;
        }

        $data = json_decode($response, true);
        
        // Check for SQL errors in the response body
        $resultItem = $data['results'][0] ?? null;
        if ($resultItem && isset($resultItem['type']) && $resultItem['type'] === 'error') {
            $this->logger->database('ERROR', 'SQL Error', ['sql' => $sql, 'err' => $resultItem['error']]);
            return false;
        }

        if (!$data || !isset($resultItem['response']['result'])) {
            return []; // No results (e.g. INSERT/UPDATE success)
        }

        $result = $resultItem['response']['result'];
        $cols = $result['cols'] ?? [];
        $rows = $result['rows'] ?? [];

        $output = [];
        foreach ($rows as $row) {
            $item = [];
            foreach ($cols as $index => $col) {
                $colName = $col['name'];
                $value = $row[$index]['value'] ?? null;
                $item[$colName] = $value;
            }
            $output[] = $item;
        }

        return $output;
    }

    public function autoSetup() {
        // We use a file check to prevent running CREATE TABLE on every request (Performance)
        if (!file_exists(__DIR__ . '/setup_v3.lock')) {
            
            // 1. Create Users Table (V3)
            $this->query("CREATE TABLE IF NOT EXISTS users_v3 (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE,
                name TEXT,
                lat REAL,
                lng REAL,
                activity TEXT DEFAULT '',
                interests TEXT DEFAULT '',
                last_active DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // 2. Create Messages Table (V3)
            $this->query("CREATE TABLE IF NOT EXISTS messages_v3 (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sender_id INTEGER,
                receiver_id INTEGER,
                body TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // 3. Create Indexes
            $this->query("CREATE INDEX IF NOT EXISTS idx_msg_v3_sender ON messages_v3(sender_id)");
            $this->query("CREATE INDEX IF NOT EXISTS idx_msg_v3_receiver ON messages_v3(receiver_id)");

            // 4. Seed Default Users
            $users = $this->query("SELECT id FROM users_v3 LIMIT 1");
            if (empty($users)) {
                $this->query("INSERT INTO users_v3 (email, name, lat, lng, activity, interests) VALUES (?, ?, ?, ?, ?, ?)",
                    ['umer@hamaaz.com', 'Umer', DEFAULT_LAT, DEFAULT_LNG, 'Looking for a cricket match', 'Cricket, Badminton, Coffee']
                );
                $this->query("INSERT INTO users_v3 (email, name, lat, lng, activity, interests) VALUES (?, ?, ?, ?, ?, ?)",
                    ['saad@saad.com', 'Saad', DEFAULT_LAT + 0.001, DEFAULT_LNG + 0.001, 'Free for gym session', 'Gym, Cricket, Running']
                );
            }

            file_put_contents(__DIR__ . '/setup_v3.lock', 'done');
        }
    }
}