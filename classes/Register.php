<?php
// classes/Register.php - User Registration Class

// Prevent direct access
if (!defined('SSJBOX_ACCESS')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/encryption.php';
require_once __DIR__ . '/RateLimiter.php';

class Register {
    private $db;
    private $table = 'registers';
    private $rateLimiter;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->rateLimiter = new RateLimiter();
    }
    
    /**
     * Register new user
     */
    public function registerUser($data, $recaptcha_response = '') {
        try {
            // Verify reCAPTCHA
            if (!$this->verifyRecaptcha($recaptcha_response)) {
                return ['success' => false, 'message' => 'reCAPTCHA verification failed'];
            }
            
            $ip = $this->getClientIP();
            
            // Check rate limiting
            if ($this->rateLimiter->isLocked($ip, 'register')) {
                $lockout_time = $this->rateLimiter->getLockoutTime($ip, 'register');
                return [
                    'success' => false, 
                    'message' => 'Too many registration attempts. Try again in ' . $lockout_time . ' minutes.',
                    'locked' => true
                ];
            }
            
            // Validate data
            $validation = $this->validateRegistrationData($data);
            if (!$validation['valid']) {
                $this->rateLimiter->increment($ip, 'register');
                return ['success' => false, 'message' => $validation['message']];
            }
            
            // Check if username already exists
            if ($this->usernameExists($data['username'])) {
                $this->rateLimiter->increment($ip, 'register');
                return ['success' => false, 'message' => 'Username already exists'];
            }
            
            // Check if citizen ID already exists
            $encrypted_citizen_id = Encryption::encrypt($data['citizen_id']);
            if ($this->citizenIdExists($encrypted_citizen_id)) {
                $this->rateLimiter->increment($ip, 'register');
                return ['success' => false, 'message' => 'Citizen ID already registered'];
            }
            
            // Hash password
            $password_hash = Encryption::hashPassword($data['password']);
            if (!$password_hash) {
                return ['success' => false, 'message' => 'Failed to hash password'];
            }
            
            // Encrypt sensitive data
            $encrypted_data = [
                'firstname' => Encryption::encrypt($data['firstname']),
                'lastname' => Encryption::encrypt($data['lastname']),
                'position' => Encryption::encrypt($data['position']),
                'citizen_id' => $encrypted_citizen_id,
                'email' => Encryption::encrypt($data['email']),
                'phone' => Encryption::encrypt($data['phone'])
            ];
            
            // Insert registration
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} (
                    hosp_code, prefix, firstname, lastname, position, citizen_id, 
                    email, phone, password_hash, username, ip_address, user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['hosp_code'],
                $data['prefix'],
                $encrypted_data['firstname'],
                $encrypted_data['lastname'],
                $encrypted_data['position'],
                $encrypted_data['citizen_id'],
                $encrypted_data['email'],
                $encrypted_data['phone'],
                $password_hash,
                $data['username'],
                $ip,
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            if ($result) {
                // Reset rate limiting on successful registration
                $this->rateLimiter->reset($ip, 'register');
                
                $register_id = $this->db->lastInsertId();
                return [
                    'success' => true, 
                    'message' => 'Registration submitted successfully. Please wait for approval.',
                    'register_id' => $register_id
                ];
            } else {
                return ['success' => false, 'message' => 'Registration failed. Please try again.'];
            }
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get all registrations (for admin)
     */
    public function getAllRegistrations($status = null, $limit = 50, $offset = 0) {
        $where_clause = "";
        $params = [];
        
        if ($status) {
            $where_clause = "WHERE r.status = ?";
            $params[] = $status;
        }
        
        $sql = "
            SELECT r.register_id, r.username, r.prefix, r.hosp_code, h.hosp_name,
                   r.status, r.created_at
            FROM {$this->table} r
            JOIN hospitals h ON r.hosp_code = h.hosp_code
            {$where_clause}
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get registration details with decrypted data (for admin)
     */
    public function getRegistrationDetails($register_id) {
        $stmt = $this->db->prepare("
            SELECT r.*, h.hosp_name, a.amphur_name, p.province_name
            FROM {$this->table} r
            JOIN hospitals h ON r.hosp_code = h.hosp_code
            JOIN amphurs a ON h.amphur_code = a.amphur_code
            JOIN provinces p ON h.province_code = p.province_code
            WHERE r.register_id = ?
        ");
        $stmt->execute([$register_id]);
        $registration = $stmt->fetch();
        
        if ($registration) {
            // Decrypt sensitive data
            $encrypted_fields = ['firstname', 'lastname', 'position', 'citizen_id', 'email', 'phone'];
            foreach ($encrypted_fields as $field) {
                $registration[$field] = Encryption::decrypt($registration[$field]);
            }
        }
        
        return $registration;
    }
    
    /**
     * Update registration status
     */
    public function updateRegistrationStatus($register_id, $status) {
        $stmt = $this->db->prepare("
            UPDATE {$this->table} 
            SET status = ?, updated_at = NOW() 
            WHERE register_id = ?
        ");
        return $stmt->execute([$status, $register_id]);
    }
    
    /**
     * Get provinces
     */
    public function getProvinces() {
        $stmt = $this->db->prepare("SELECT province_code, province_name FROM provinces ORDER BY province_name");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get amphurs by province
     */
    public function getAmphurs($province_code) {
        $stmt = $this->db->prepare("
            SELECT amphur_code, amphur_name 
            FROM amphurs 
            WHERE province_code = ? 
            ORDER BY amphur_name
        ");
        $stmt->execute([$province_code]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get hospitals by amphur
     */
    public function getHospitals($amphur_code) {
        $stmt = $this->db->prepare("
            SELECT hosp_code, hosp_name 
            FROM hospitals 
            WHERE amphur_code = ? 
            ORDER BY hosp_name
        ");
        $stmt->execute([$amphur_code]);
        return $stmt->fetchAll();
    }
    
    /**
     * Validate registration data
     */
    private function validateRegistrationData($data) {
        $required_fields = [
            'hosp_code', 'prefix', 'firstname', 'lastname', 'position', 
            'citizen_id', 'email', 'phone', 'password', 'confirm_password', 'username'
        ];
        
        // Check required fields
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return ['valid' => false, 'message' => "Field '{$field}' is required"];
            }
        }
        
        // Validate citizen ID (13 digits)
        if (!preg_match('/^\d{13}$/', $data['citizen_id'])) {
            return ['valid' => false, 'message' => 'Citizen ID must be exactly 13 digits'];
        }
        
        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Invalid email format'];
        }
        
        // Validate phone (10-11 digits)
        if (!preg_match('/^\d{10,11}$/', $data['phone'])) {
            return ['valid' => false, 'message' => 'Phone number must be 10-11 digits'];
        }
        
        // Validate password
        $password_validation = $this->validatePassword($data['password']);
        if (!$password_validation['valid']) {
            return $password_validation;
        }
        
        // Check password confirmation
        if ($data['password'] !== $data['confirm_password']) {
            return ['valid' => false, 'message' => 'Password confirmation does not match'];
        }
        
        // Validate username (alphanumeric, 3-50 characters)
        if (!preg_match('/^[a-zA-Z0-9]{3,50}$/', $data['username'])) {
            return ['valid' => false, 'message' => 'Username must be 3-50 alphanumeric characters'];
        }
        
        return ['valid' => true, 'message' => 'Data is valid'];
    }
    
    /**
     * Validate password strength
     */
    private function validatePassword($password) {
        if (strlen($password) < 12) {
            return ['valid' => false, 'message' => 'Password must be at least 12 characters long'];
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one lowercase letter'];
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one uppercase letter'];
        }
        
        if (!preg_match('/\d/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one number'];
        }
        
        if (!preg_match('/[^a-zA-Z\d]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one special character'];
        }
        
        return ['valid' => true, 'message' => 'Password is strong'];
    }
    
    /**
     * Check if username exists
     */
    private function usernameExists($username) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE username = ?");
        $stmt->execute([$username]);
        $count1 = $stmt->fetchColumn();
        
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $count2 = $stmt->fetchColumn();
        
        return ($count1 + $count2) > 0;
    }
    
    /**
     * Check if citizen ID exists
     */
    private function citizenIdExists($encrypted_citizen_id) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE citizen_id = ?");
        $stmt->execute([$encrypted_citizen_id]);
        $count1 = $stmt->fetchColumn();
        
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE citizen_id = ?");
        $stmt->execute([$encrypted_citizen_id]);
        $count2 = $stmt->fetchColumn();
        
        return ($count1 + $count2) > 0;
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