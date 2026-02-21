<?php
// Logger.php - Comprehensive Logging System for FUG App

require_once 'config.php';

class Logger {
    private static $instance = null;
    private $logsDir;
    private $logFiles;
    private $enabled;
    
    // Log types
    const LOG_DATABASE = 'database';
    const LOG_ERROR = 'error';
    const LOG_CHAT = 'chat';
    const LOG_LOCATION = 'location';
    const LOG_PROFILE = 'profile';
    const LOG_AUTH = 'auth';
    const LOG_API = 'api';
    const LOG_DEBUG = 'debug';
    const LOG_REALTIME = 'realtime';
    
    private function __construct() {
        $this->logsDir = LOGS_DIR;
        $this->enabled = ENABLE_LOGGING;
        $this->logFiles = [
            self::LOG_DATABASE => 'database.log',
            self::LOG_ERROR => 'error.log',
            self::LOG_CHAT => 'chat.log',
            self::LOG_LOCATION => 'location.log',
            self::LOG_PROFILE => 'profile.log',
            self::LOG_AUTH => 'auth.log',
            self::LOG_API => 'api.log',
            self::LOG_DEBUG => 'debug.log',
            self::LOG_REALTIME => 'realtime.log'
        ];
        
        $this->ensureLogDirectory();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function ensureLogDirectory() {
        if (!file_exists($this->logsDir)) {
            mkdir($this->logsDir, 0755, true);
        }
    }
    
    private function getLogPath($type) {
        $filename = $this->logFiles[$type] ?? 'general.log';
        return $this->logsDir . $filename;
    }
    
    private function formatMessage($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s.u');
        $sessionId = session_id() ?: 'NO_SESSION';
        $userId = $_SESSION['user_id'] ?? 'GUEST';
        
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }
        
        return "[$timestamp] [$level] [User:$userId] [Session:$sessionId] $message$contextStr" . PHP_EOL;
    }
    
    public function log($type, $level, $message, $context = []) {
        if (!$this->enabled) return;
        
        $logPath = $this->getLogPath($type);
        $formattedMessage = $this->formatMessage($level, $message, $context);
        
        // Write to specific log file
        file_put_contents($logPath, $formattedMessage, FILE_APPEND | LOCK_EX);
        
        // Also write errors to the main error log
        if ($level === 'ERROR') {
            $errorPath = $this->getLogPath(self::LOG_ERROR);
            file_put_contents($errorPath, "[$type] $formattedMessage", FILE_APPEND | LOCK_EX);
        }
    }
    
    // Convenience methods for each log type
    public function database($level, $message, $context = []) {
        $this->log(self::LOG_DATABASE, $level, $message, $context);
    }
    
    public function error($level, $message, $context = []) {
        $this->log(self::LOG_ERROR, $level, $message, $context);
    }
    
    public function chat($level, $message, $context = []) {
        $this->log(self::LOG_CHAT, $level, $message, $context);
    }
    
    public function location($level, $message, $context = []) {
        $this->log(self::LOG_LOCATION, $level, $message, $context);
    }
    
    public function profile($level, $message, $context = []) {
        $this->log(self::LOG_PROFILE, $level, $message, $context);
    }
    
    public function auth($level, $message, $context = []) {
        $this->log(self::LOG_AUTH, $level, $message, $context);
    }
    
    public function api($level, $message, $context = []) {
        $this->log(self::LOG_API, $level, $message, $context);
    }
    
    public function debug($level, $message, $context = []) {
        $this->log(self::LOG_DEBUG, $level, $message, $context);
    }
    
    public function realtime($level, $message, $context = []) {
        $this->log(self::LOG_REALTIME, $level, $message, $context);
    }
    
    // Get logs for display
    public function getLogs($type, $lines = 100) {
        $logPath = $this->getLogPath($type);
        
        if (!file_exists($logPath)) {
            return [];
        }
        
        $content = file_get_contents($logPath);
        $allLines = explode(PHP_EOL, $content);
        $allLines = array_filter($allLines);
        
        // Return last N lines
        return array_slice(array_reverse($allLines), 0, $lines);
    }
    
    // Get all log types
    public function getLogTypes() {
        return array_keys($this->logFiles);
    }
    
    // Clear a log file
    public function clearLog($type) {
        $logPath = $this->getLogPath($type);
        if (file_exists($logPath)) {
            file_put_contents($logPath, '');
            return true;
        }
        return false;
    }
}

// Global helper function for quick logging
function fug_log($type, $level, $message, $context = []) {
    Logger::getInstance()->log($type, $level, $message, $context);
}