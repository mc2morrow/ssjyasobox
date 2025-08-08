<?php
// classes/Logger.php - System Logger Class

// Prevent direct access
if (!defined('SSJBOX_ACCESS')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/../config/database.php';

class Logger {
    private $db;
    private $table = 'userlogs';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Log user activity
     */
    public function log($user_id, $action, $description = '', $ip_address = null, $user_agent = null) {
        try {
            $ip_address = $ip_address ?: $this->getClientIP();
            $user_agent = $user_agent ?: $_SERVER['HTTP_USER_AGENT'] ?? '';
            $session_id = session_id();
            
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} (user_id, action, description, ip_address, user_agent, session_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            return $stmt->execute([
                $user_id,
                $action,
                $description,
                $ip_address,
                $user_agent,
                $session_id
            ]);
            
        } catch (Exception $e) {
            error_log("Logger error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user logs with pagination
     */
    public function getUserLogs($user_id, $limit = 50, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT log_id, action, description, ip_address, created_at
            FROM {$this->table}
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$user_id, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get all logs (for admin)
     */
    public function getAllLogs($limit = 100, $offset = 0, $filters = []) {
        $where_conditions = [];
        $params = [];
        
        // Build WHERE clause based on filters
        if (!empty($filters['user_id'])) {
            $where_conditions[] = "l.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $where_conditions[] = "l.action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(l.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(l.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['ip_address'])) {
            $where_conditions[] = "l.ip_address = ?";
            $params[] = $filters['ip_address'];
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $sql = "
            SELECT l.log_id, l.user_id, u.username, l.action, l.description, 
                   l.ip_address, l.created_at
            FROM {$this->table} l
            LEFT JOIN users u ON l.user_id = u.user_id
            {$where_clause}
            ORDER BY l.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get log statistics
     */
    public function getLogStats($days = 7) {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as log_date,
                action,
                COUNT(*) as count
            FROM {$this->table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at), action
            ORDER BY log_date DESC, action
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get unique actions for filter dropdown
     */
    public function getUniqueActions() {
        $stmt = $this->db->prepare("
            SELECT DISTINCT action 
            FROM {$this->table} 
            ORDER BY action
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);