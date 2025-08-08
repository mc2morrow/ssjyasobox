<?php
// classes/RateLimiter.php - Rate Limiting Class

// Prevent direct access
if (!defined('SSJBOX_ACCESS')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/../config/database.php';

class RateLimiter {
    private $db;
    private $table = 'rate_limits';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Check if identifier is locked for specific action
     */
    public function isLocked($identifier, $action) {
        $stmt = $this->db->prepare("
            SELECT lockout_until 
            FROM {$this->table} 
            WHERE identifier = ? AND action = ? AND lockout_until > NOW()
        ");
        $stmt->execute([$identifier, $action]);
        $result = $stmt->fetch();
        
        return $result !== false;
    }
    
    /**
     * Increment attempts for identifier and action
     */
    public function increment($identifier, $action) {
        try {
            $this->db->beginTransaction();
            
            // Get current attempts
            $stmt = $this->db->prepare("
                SELECT attempts, lockout_count 
                FROM {$this->table} 
                WHERE identifier = ? AND action = ?
            ");
            $stmt->execute([$identifier, $action]);
            $current = $stmt->fetch();
            
            if ($current) {
                $new_attempts = $current['attempts'] + 1;
                $max_attempts = $this->getMaxAttempts($action);
                
                if ($new_attempts >= $max_attempts) {
                    // Lock the identifier
                    $lockout_count = $current['lockout_count'] + 1;
                    $lockout_minutes = $this->getLockoutDuration($lockout_count);
                    $lockout_until = date('Y-m-d H:i:s', time() + ($lockout_minutes * 60));
                    
                    $stmt = $this->db->prepare("
                        UPDATE {$this->table} 
                        SET attempts = 0, lockout_until = ?, lockout_count = ?, updated_at = NOW()
                        WHERE identifier = ? AND action = ?
                    ");
                    $stmt->execute([$lockout_until, $lockout_count, $identifier, $action]);
                } else {
                    // Just increment attempts
                    $stmt = $this->db->prepare("
                        UPDATE {$this->table} 
                        SET attempts = ?, updated_at = NOW()
                        WHERE identifier = ? AND action = ?
                    ");
                    $stmt->execute([$new_attempts, $identifier, $action]);
                }
            } else {
                // First attempt
                $stmt = $this->db->prepare("
                    INSERT INTO {$this->table} (identifier, action, attempts, created_at, updated_at)
                    VALUES (?, ?, 1, NOW(), NOW())
                ");
                $stmt->execute([$identifier, $action]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("RateLimiter increment error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reset attempts for identifier and action
     */
    public function reset($identifier, $action) {
        $stmt = $this->db->prepare("
            UPDATE {$this->table} 
            SET attempts = 0, lockout_until = NULL, updated_at = NOW()
            WHERE identifier = ? AND action = ?
        ");
        return $stmt->execute([$identifier, $action]);
    }
    
    /**
     * Get lockout time remaining in minutes
     */
    public function getLockoutTime($identifier, $action) {
        $stmt = $this->db->prepare("
            SELECT CEIL(TIMESTAMPDIFF(SECOND, NOW(), lockout_until) / 60) as minutes_remaining
            FROM {$this->table} 
            WHERE identifier = ? AND action = ? AND lockout_until > NOW()
        ");
        $stmt->execute([$identifier, $action]);
        $result = $stmt->fetch();
        
        return $result ? max(1, $result['minutes_remaining']) : 0;
    }
    
    /**
     * Get current attempts count
     */
    public function getAttempts($identifier, $action) {
        $stmt = $this->db->prepare("
            SELECT attempts 
            FROM {$this->table} 
            WHERE identifier = ? AND action = ?
        ");
        $stmt->execute([$identifier, $action]);
        $result = $stmt->fetch();
        
        return $result ? $result['attempts'] : 0;
    }
    
    /**
     * Clean up expired lockouts
     */
    public function cleanup() {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table} 
            WHERE lockout_until IS NOT NULL AND lockout_until < NOW() 
            AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        return $stmt->execute();
    }
    
    /**
     * Get maximum attempts for action
     */
    private function getMaxAttempts($action) {
        switch ($action) {
            case 'register':
                return RATE_LIMIT_REGISTER_ATTEMPTS;
            case 'login':
                return RATE_LIMIT_LOGIN_ATTEMPTS;
            default:
                return 5;
        }
    }
    
    /**
     * Get lockout duration in minutes based on lockout count
     */
    private function getLockoutDuration($lockout_count) {
        $lockout_times = RATE_LIMIT_LOCKOUT_TIMES;
        
        if (isset($lockout_times[$lockout_count])) {
            return $lockout_times[$lockout_count];
        }
        
        // Return maximum lockout time if exceeded
        return end($lockout_times);
    }
    
    /**
     * Get all locked IPs (for admin)
     */
    public function getLockedIPs() {
        $stmt = $this->db->prepare("
            SELECT identifier, action, lockout_until, lockout_count,
                   CEIL(TIMESTAMPDIFF(SECOND, NOW(), lockout_until) / 60) as minutes_remaining
            FROM {$this->table} 
            WHERE lockout_until > NOW()
            ORDER BY lockout_until DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Unlock IP (for admin)
     */
    public function unlock($identifier, $action = null) {
        if ($action) {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET lockout_until = NULL, attempts = 0, updated_at = NOW()
                WHERE identifier = ? AND action = ?
            ");
            return $stmt->execute([$identifier, $action]);
        } else {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET lockout_until = NULL, attempts = 0, updated_at = NOW()
                WHERE identifier = ?
            ");
            return $stmt->execute([$identifier]);
        }
    }
}

?>

