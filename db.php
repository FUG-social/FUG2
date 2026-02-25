<?php
// db.php - V2 Architecture: Turso Identity Bridge & Schema
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

        if (is_resource($ch)) {
            curl_close($ch);
        }

        if ($httpCode >= 400) {
            $this->logger->database('ERROR', 'Database HTTP Error', ['code' => $httpCode, 'res' => $response]);
            return false;
        }

        $data = json_decode($response, true);
        
        $resultItem = $data['results'][0] ?? null;
        if ($resultItem && isset($resultItem['type']) && $resultItem['type'] === 'error') {
            $this->logger->database('ERROR', 'SQL Error', ['sql' => $sql, 'err' => $resultItem['error']]);
            return false;
        }

        if (!$data || !isset($resultItem['response']['result'])) {
            return []; 
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
        // V2 Update: Lock file updated to setup_v4
        if (!file_exists(__DIR__ . '/setup_v4.lock')) {
            
            // 1. Create Identity Bridge Table (Replaces users_v3)
            $this->query("CREATE TABLE IF NOT EXISTS user_identity_v1 (
                auth0_sub TEXT PRIMARY KEY,
                email TEXT UNIQUE,
                internal_id TEXT UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $this->query("CREATE INDEX IF NOT EXISTS idx_internal_id ON user_identity_v1(internal_id)");

            // 2. Create Messages Table with Alphanumeric TEXT IDs
            $this->query("CREATE TABLE IF NOT EXISTS messages_v4 (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id TEXT,
                sender_id TEXT,
                receiver_id TEXT,
                body TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $this->query("CREATE INDEX IF NOT EXISTS idx_msg_v4_room ON messages_v4(room_id)");

            // 3. Seed Default Users with new 6-char IDs
            $users = $this->query("SELECT internal_id FROM user_identity_v1 LIMIT 1");
            if (empty($users)) {
                $this->query("INSERT INTO user_identity_v1 (auth0_sub, email, internal_id) VALUES (?, ?, ?)",
                    ['mock|admin1', 'umer@hamaaz.com', 'af1012']
                );
                $this->query("INSERT INTO user_identity_v1 (auth0_sub, email, internal_id) VALUES (?, ?, ?)",
                    ['mock|admin2', 'saad@saad.com', 'bc9999']
                );
            }

            file_put_contents(__DIR__ . '/setup_v4.lock', 'done');
        }
    }
}