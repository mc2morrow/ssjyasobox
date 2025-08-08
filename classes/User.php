<?php
// classes/User.php - User Management Class

// Prevent direct access
if (!defined('SSJBOX_ACCESS')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/encryption.php';

class User {
    private $db;
    private $table = 'users';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create new user from registration
     */
    public function createFromRegister($register_id) {
        try {
            $this->db->beginTransaction();
            
            // Get registration data
            $stmt = $this->db->prepare("SELECT * FROM registers WHERE register_id = ? AND status = 'approved'");
            $stmt->execute([$register_id]);
            $register = $stmt->fetch();
            
            if (!$register) {
                throw new Exception("Registration not found or not approved");
            }
            
            // Insert into users table
            $stmt = $this->db->prepare("
                INSERT INTO users (
                    username, password_hash, hosp_code, prefix, firstname, lastname, 
                    position, citizen_id, email, phone, role, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'user', 'active')
            ");
            
            $result = $stmt->execute([
                $register['username'],
                $register['password_hash'],
                $register['hosp_code'],
                $register['prefix'],
                $register['firstname'],
                $register['lastname'],
                $register['position'],
                $register['citizen_id'],
                $register['email'],
                $register['phone']
            ]);
            
            if ($result) {
                $user_id = $this->db->lastInsertId();
                
                // Update register status
                $stmt = $this->db->prepare("UPDATE registers SET status = 'transferred' WHERE register_id = ?");
                $stmt->execute([$register_id]);
                
                $this->db->commit();
                return $user_id;
            } else {
                throw new Exception("Failed to create user");
            }
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Authenticate user login
     */
    public function authenticate($username, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && Encryption::verifyPassword($password, $user['password_hash'])) {
            // Update last login
            $this->updateLastLogin($user['user_id']);
            return $user;
        }
        
        return false;
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($user_id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
    
    /**
     * Get user with hospital info
     */
    public function getUserWithHospital($user_id) {
        $stmt = $this->db->prepare("
            SELECT u.*, h.hosp_name, a.amphur_name, p.province_name
            FROM users u
            JOIN hospitals h ON u.hosp_code = h.hosp_code
            JOIN amphurs a ON h.amphur_code = a.amphur_code
            JOIN provinces p ON h.province_code = p.province_code
            WHERE u.user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
    
    /**
     * Update user profile
     */
    public function updateProfile($user_id, $data) {
        $allowed_fields = ['prefix', 'firstname', 'lastname', 'position', 'email', 'phone', 'session_timeout'];
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                // Encrypt sensitive fields
                if (in_array($field, ['firstname', 'lastname', 'position', 'email', 'phone'])) {
                    $value = Encryption::encrypt($value);
                }
                $fields[] = $field . " = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $user_id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }
    
    /**
     * Update last login
     */
    private function updateLastLogin($user_id) {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW(), login_attempts = 0 WHERE user_id = ?");
        return $stmt->execute([$user_id]);
    }
    
    /**
     * Get all users (for admin)
     */
    public function getAllUsers($limit = 50, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT u.user_id, u.username, u.hosp_code, h.hosp_name, u.prefix, 
                   u.role, u.status, u.last_login, u.created_at
            FROM users u
            JOIN hospitals h ON u.hosp_code = h.hosp_code
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Update user status (for admin)
     */
    public function updateUserStatus($user_id, $status) {
        $stmt = $this->db->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        return $stmt->execute([$status, $user_id]);
    }
    
    /**
     * Get decrypted user data
     */
    public function getDecryptedUserData($user_id) {
        $user = $this->getUserById($user_id);
        if ($user) {
            $encrypted_fields = ['firstname', 'lastname', 'position', 'citizen_id', 'email', 'phone'];
            foreach ($encrypted_fields as $field) {
                if (isset($user[$field])) {
                    $user[$field] = Encryption::decrypt($user[$field]);
                }
            }
        }
        return $user;
    }
}
