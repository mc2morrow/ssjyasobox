<?php
// classes/Session.php
class Session {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
        
        $this->checkSessionValidity();
    }
    
    /**
     * Start user session
     */
    public function startUserSession($userId, $sessionTime = SESSION_TIMEOUT, $rememberMe = false) {
        try {
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            $sessionId = session_id();
            $expiresAt = date('Y-m-d H:i:s', time() + $sessionTime);
            
            // Store session in database
            $sql = "INSERT INTO user_sessions (session_id, user_id, session_data, expires_at) 
                    VALUES (?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    session_data = VALUES(session_data), 
                    expires_at = VALUES(expires_at)";
            
            $sessionData = json_encode([
                'user_id' => $userId,
                'login_time' => time(),
                'last_activity' => time(),
                'ip_address' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            $this->db->execute($sql, [$sessionId, $userId, $sessionData, $expiresAt]);
            
            // Set session variables
            $_SESSION['user_id'] = $userId;
            $_SESSION['session_time'] = $sessionTime;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['csrf_token'] = $this->generateCSRFToken();
            
            // Set remember me cookie if requested
            if ($rememberMe) {
                $rememberToken = Encryption::generateRandomString(64);
                setcookie('remember_token', $rememberToken, time() + COOKIE_LIFETIME, '/', '', false, true);
                
                // Store remember token in database (you might want to create a separate table for this)
                $sql = "UPDATE users SET remember_token = ? WHERE user_id = ?";
                $this->db->execute($sql, [hash('sha256', $rememberToken), $userId]);
            }
            
            // Update last login time
            $sql = "UPDATE users SET user_last_login = NOW() WHERE user_id = ?";
            $this->db->execute($sql, [$userId]);
            
            $this->logger->logUserActivity($userId, 'LOGIN', 'User logged in successfully');
            
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to start user session: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && $this->isValidSession();
    }
    
    /**
     * Get current user ID
     */
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get user profile (เพิ่มใน Session class)
     */
    public function getUserProfile() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            // สร้าง User object ภายใน method
            require_once __DIR__ . '/User.php';
            $user = new User();
            return $user->getUserProfile($this->getUserId());
        } catch (Exception $e) {
            $this->logger->error('Failed to get user profile: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get user role (เพิ่มใน Session class เพื่อความสะดวก)
     */
    public function getUserRole() {
        $profile = $this->getUserProfile();
        return $profile ? $profile['user_role'] : null;
    }

    /**
     * Check if current user is admin (เพิ่มใน Session class)
     */
    public function isAdmin() {
        return $this->getUserRole() === 'admin';
    }

    /**
     * Check if current user is regular user (เพิ่มใน Session class)
     */
    public function isUser() {
        return $this->getUserRole() === 'user';
    }
    
    /**
     * Update session activity
     */
    public function updateActivity() {
        if ($this->isLoggedIn()) {
            $_SESSION['last_activity'] = time();
            
            $sessionId = session_id();
            $sessionTime = $_SESSION['session_time'] ?? SESSION_TIMEOUT;
            $expiresAt = date('Y-m-d H:i:s', time() + $sessionTime);
            
            $sql = "UPDATE user_sessions SET expires_at = ? WHERE session_id = ?";
            $this->db->execute($sql, [$expiresAt, $sessionId]);
        }
    }
    
    /**
     * Destroy user session
     */
    public function destroySession() {
        $userId = $this->getUserId();
        
        if ($userId) {
            $this->logger->logUserActivity($userId, 'LOGOUT', 'User logged out');
        }
        
        // Remove session from database
        $sessionId = session_id();
        $sql = "DELETE FROM user_sessions WHERE session_id = ?";
        $this->db->execute($sql, [$sessionId]);
        
        // Clear remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
        
        // Destroy session
        session_unset();
        session_destroy();
        
        // Start new session
        session_start();
        session_regenerate_id(true);
    }
    
    /**
     * Check session validity
     */
    private function checkSessionValidity() {
        if (isset($_SESSION['user_id'])) {
            // Check if session has expired
            if (!$this->isValidSession()) {
                $this->destroySession();
                return;
            }
            
            // Update last activity
            $this->updateActivity();
        } else {
            // Check for remember me cookie
            $this->checkRememberMe();
        }
    }
    
    /**
     * Check if session is still valid
     */
    private function isValidSession() {
        if (!isset($_SESSION['last_activity']) || !isset($_SESSION['session_time'])) {
            return false;
        }
        
        $sessionTimeout = $_SESSION['session_time'];
        $lastActivity = $_SESSION['last_activity'];
        
        return (time() - $lastActivity) < $sessionTimeout;
    }
    
    /**
     * Check remember me functionality
     */
    private function checkRememberMe() {
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $hashedToken = hash('sha256', $token);
            
            $sql = "SELECT user_id FROM users WHERE remember_token = ? AND user_status = 'active'";
            $result = $this->db->fetch($sql, [$hashedToken]);
            
            if ($result) {
                // Auto-login user
                $this->startUserSession($result['user_id']);
                $this->logger->logUserActivity($result['user_id'], 'AUTO_LOGIN', 'User auto-logged in via remember me');
            } else {
                // Invalid token, remove cookie
                setcookie('remember_token', '', time() - 3600, '/', '', false, true);
            }
        }
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        $token = Encryption::generateRandomString(32);
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        return $token;
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // Check if token has expired
        if ((time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_EXPIRE) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
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
     * Clean expired sessions
     */
    public function cleanExpiredSessions() {
        try {
            $sql = "DELETE FROM user_sessions WHERE expires_at < NOW()";
            $this->db->execute($sql);
        } catch (Exception $e) {
            $this->logger->error('Failed to clean expired sessions: ' . $e->getMessage());
        }
    }
}
