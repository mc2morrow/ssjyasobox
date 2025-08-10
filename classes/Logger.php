<?php
// classes/Logger.php
class Logger {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Log user activity to database
     */
    public function logUserActivity($userId, $action, $details = null, $ipAddress = null, $userAgent = null) {
        try {
            $ipAddress = $ipAddress ?: $this->getClientIP();
            $userAgent = $userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');
            
            $sql = "INSERT INTO userlogs (user_id, log_action, log_details, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $this->db->execute($sql, [$userId, $action, $details, $ipAddress, $userAgent]);
        } catch (Exception $e) {
            $this->logToFile('ERROR', 'Failed to log user activity: ' . $e->getMessage());
        }
    }
    
    /**
     * Log to file
     */
    public function logToFile($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message";
        
        if (!empty($context)) {
            $logMessage .= ' Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        $logMessage .= PHP_EOL;
        
        $logFile = LOG_PATH . strtolower($level) . '_' . date('Y-m-d') . '.log';
        
        // Ensure log directory exists
        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0755, true);
        }
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log error
     */
    public function error($message, $context = []) {
        $this->logToFile('ERROR', $message, $context);
    }
    
    /**
     * Log warning
     */
    public function warning($message, $context = []) {
        $this->logToFile('WARNING', $message, $context);
    }
    
    /**
     * Log info
     */
    public function info($message, $context = []) {
        $this->logToFile('INFO', $message, $context);
    }
    
    /**
     * Log debug
     */
    public function debug($message, $context = []) {
        if (LOG_LEVEL === 'DEBUG') {
            $this->logToFile('DEBUG', $message, $context);
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 
                   'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, 
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Clean old logs (keep only last 30 days)
     */
    public function cleanOldLogs($days = 30) {
        try {
            $cutoffDate = date('Y-m-d', strtotime("-$days days"));
            
            // Clean database logs
            $sql = "DELETE FROM userlogs WHERE DATE(log_created_at) < ?";
            $this->db->execute($sql, [$cutoffDate]);
            
            // Clean file logs
            if (is_dir(LOG_PATH)) {
                $files = glob(LOG_PATH . '*.log');
                foreach ($files as $file) {
                    $fileName = basename($file);
                    if (preg_match('/(\d{4}-\d{2}-\d{2})\.log$/', $fileName, $matches)) {
                        $fileDate = $matches[1];
                        if ($fileDate < $cutoffDate) {
                            unlink($file);
                        }
                    }
                }
            }
            
            $this->info("Cleaned logs older than $days days");
        } catch (Exception $e) {
            $this->error('Failed to clean old logs: ' . $e->getMessage());
        }
    }
}
