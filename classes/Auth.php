<?php
// classes/Auth.php - Authentication Class

// Prevent direct access
if (!defined('SSJBOX_ACCESS')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/Logger.php';

class Auth {
    private $db;
    private $user;
    private $rateLimiter;
    private $logger;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->user = new User();
        $this->rateLimiter = new RateLimiter();
        $this->logger = new Logger();
    }
    
    /**
     * Start secure session
     */
    public function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
            
            // Regenerate session ID for security
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
            }
        }
    }
    
    /**
     * Login user
     */
    public function login($username, $password, $remember_me = false, $recaptcha_response = '') {
        // Verify reCAPTCHA
        if (!$this->verifyRecaptcha($recaptcha_response)) {
            return ['success' => false, 'message' => 'reCAPTCHA verification failed'];
        }
        
        $ip = $this->getClientIP();
        
        // Check rate limiting
        if ($this->rateLimiter->isLocked($ip, 'login')) {
            $lockout_time = $this->rateLimiter->getLockoutTime($ip, 'login');
            return [
                'success' => false, 
                'message' => 'Too many login attempts. Try again in ' . $lockout_time . ' minutes.',
                'locked' => true
            ];
        }
        
        // Authenticate user
        $user_data = $this->user->authenticate($username, $password);
        
        if ($user_data) {
            // Reset rate limiting on successful login
            $this->rateLimiter->reset($ip, 'login');
            
            // Start session
            $this->startSession();
            
            // Set session data
            $_SESSION['user_id'] = $user_data['user_id'];
            $_SESSION['username'] = $user_data['username'];
            $_SESSION['role'] = $user_data['role'];
            $_SESSION['hosp_code'] = $user_data['hosp_code'];
            $_SESSION['session_timeout'] = $user_data['session_timeout'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            // Handle remember me
            if ($remember_me) {
                $this->setRememberMeCookie($user_data['user_id']);
            }
            
            // Log successful login
            $this->logger->log($user_data['user_id'], 'LOGIN_SUCCESS', 'User logged in successfully', $ip);
            
            return ['success' => true, 'user' => $user_data];
            
        } else {
            // Increment failed attempts
            $this->rateLimiter->increment($ip, 'login');
            
            // Log failed login
            $this->logger->log(null, 'LOGIN_FAILED', "Failed login attempt for username: $username", $ip);
            
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        $this->startSession();
        
        if (isset($_SESSION['user_id'])) {
            // Log logout
            $this->logger->log($_SESSION['user_id'], 'LOGOUT', 'User logged out', $this->getClientIP());
            
            // Clear remember me cookie
            $this->clearRememberMeCookie();
        }
        
        // Destroy session
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        
        return true;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        $this->startSession();
        
        if (!isset($_SESSION['user_id'])) {
            // Check remember me cookie
            return $this->checkRememberMe();
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            $session_timeout = $_SESSION['session_timeout'] ?? 3600;
            if ((time() - $_SESSION['last_activity']) > $session_timeout) {
                $this->logout();
                return false;
            }
            $_SESSION['last_activity'] = time();
        }
        
        return true;
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return $this->user->getDecryptedUserData($_SESSION['user_id']);
        }
        return null;
    }
    
    /**
     * Check user role
     */
    public function hasRole($role) {
        $this->startSession();
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }
    
    /**
     * Set remember me cookie
     */
    private function setRememberMeCookie($user_id) {
        $token = Encryption::generateRandomString(64);
        $expires = time() + (30 * 24 * 60 * 60); // 30 days
        
        // Store token in database
        $stmt = $this->db->prepare("UPDATE users SET remember_token = ?, remember_expires = FROM_UNIXTIME(?) WHERE user_id = ?");
        $stmt->execute([hash('sha256', $token), $expires, $user_id]);
        
        // Set cookie
        setcookie('remember_me', $token, $expires, '/', '', SESSION_SECURE, true);
    }
    
    /**
     * Clear remember me cookie
     */
    private function clearRememberMeCookie() {
        if (isset($_SESSION['user_id'])) {
            $stmt = $this->db->prepare("UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }
        
        setcookie('remember_me', '', time() - 3600, '/', '', SESSION_SECURE, true);
    }
    
    /**
     * Check remember me cookie
     */
    private function checkRememberMe() {
        if (!isset($_COOKIE['remember_me'])) {
            return false;
        }
        
        $token = $_COOKIE['remember_me'];
        $hashed_token = hash('sha256', $token);
        
        $stmt = $this->db->prepare("
            SELECT user_id, username, role, hosp_code, session_timeout 
            FROM users 
            WHERE remember_token = ? AND remember_expires > NOW() AND status = 'active'
        ");
        $stmt->execute([$hashed_token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Restore session
            $this->startSession();
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['hosp_code'] = $user['hosp_code'];
            $_SESSION['session_timeout'] = $user['session_timeout'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            return true;
        } else {
            // Invalid token, clear cookie
            $this->clearRememberMeCookie();
            return false;
        }
    }
    
    /**
     * Verify reCAPTCHA
     */
    private function verifyRecaptcha($response) {
        if (empty(RECAPTCHA_SECRET_KEY)) {
            return true; // Skip if not configured
        }
        
        $data = [
            'secret' => RECAPTCHA_SECRET_KEY,
            'response' => $response,
            'remoteip' => $this->getClientIP()
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
        $json = json_decode($result, true);
        
        return $json['success'] && $json['score'] >= RECAPTCHA_MIN_SCORE;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
