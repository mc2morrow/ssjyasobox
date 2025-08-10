<?php
// classes/User.php
class User {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }
    
    /**
     * Authenticate user login
     */
    public function login($username, $password, $turnstileToken = '') {
        try {
            // Verify Turnstile
            if (!$this->verifyTurnstile($turnstileToken)) {
                return ['success' => false, 'message' => 'การยืนยันความปลอดภัยไม่สำเร็จ'];
            }
            
            // Get user data
            $user = $this->getUserByUsername($username);
            
            if (!$user) {
                $this->logger->logUserActivity(null, 'LOGIN_FAILED', "Invalid username: $username");
                return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
            }
            
            // Check if account is locked
            if ($this->isAccountLocked($user['user_id'])) {
                return ['success' => false, 'message' => 'บัญชีถูกล็อก กรุณาลองใหม่ภายหลัง'];
            }
            
            // Check if account is active
            if ($user['user_status'] !== 'active') {
                return ['success' => false, 'message' => 'บัญชียังไม่ได้รับการอนุมัติ'];
            }
            
            // Verify password
            if (!password_verify($password, $user['user_password'])) {
                $this->incrementLoginAttempts($user['user_id']);
                $this->logger->logUserActivity($user['user_id'], 'LOGIN_FAILED', 'Invalid password');
                return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
            }
            
            // Reset login attempts on successful login
            $this->resetLoginAttempts($user['user_id']);
            
            return ['success' => true, 'user' => $user];
            
        } catch (Exception $e) {
            $this->logger->error('Login error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ'];
        }
    }
    
    /**
     * Get user by username
     */
    private function getUserByUsername($username) {
        $sql = "SELECT u.*, r.reg_hosp_code 
                FROM users u 
                LEFT JOIN registers r ON u.reg_id = r.reg_id 
                WHERE u.user_name = ?";
        return $this->db->fetch($sql, [$username]);
    }
    
    /**
     * Get user profile with decrypted data
     */
    public function getUserProfile($userId) {
        $sql = "SELECT u.*, r.* 
                FROM users u 
                LEFT JOIN registers r ON u.reg_id = r.reg_id 
                WHERE u.user_id = ?";
        
        $user = $this->db->fetch($sql, [$userId]);
        
        if ($user) {
            // Decrypt personal data
            $user['reg_prefix'] = Encryption::decrypt($user['reg_prefix']);
            $user['reg_firstname'] = Encryption::decrypt($user['reg_firstname']);
            $user['reg_lastname'] = Encryption::decrypt($user['reg_lastname']);
            $user['reg_position'] = Encryption::decrypt($user['reg_position']);
            $user['reg_cid'] = Encryption::decrypt($user['reg_cid']);
            $user['reg_email'] = Encryption::decrypt($user['reg_email']);
            $user['reg_phone'] = Encryption::decrypt($user['reg_phone']);
            
            // Get hospital info
            $hospitalInfo = $this->getHospitalInfo($user['reg_hosp_code']);
            $user = array_merge($user, $hospitalInfo);
        }
        
        return $user;
    }
    
    /**
     * Get hospital information
     */
    private function getHospitalInfo($hospCode) {
        $sql = "SELECT h.hosp_name, a.amphur_name, p.province_name 
                FROM hospitals h 
                LEFT JOIN amphurs a ON h.amphur_code = a.amphur_code 
                LEFT JOIN provinces p ON h.province_code = p.province_code 
                WHERE h.hosp_code = ?";
        
        return $this->db->fetch($sql, [$hospCode]) ?: [];
    }
    
    /**
     * Update user profile
     */
    public function updateProfile($userId, $data) {
        try {
            $this->db->beginTransaction();
            
            // Update registers table
            $sql = "UPDATE registers SET 
                    reg_prefix = ?, reg_firstname = ?, reg_lastname = ?, 
                    reg_position = ?, reg_phone = ?, reg_updated_at = NOW() 
                    WHERE reg_id = (SELECT reg_id FROM users WHERE user_id = ?)";
            
            $params = [
                Encryption::encrypt($data['prefix']),
                Encryption::encrypt($data['firstname']),
                Encryption::encrypt($data['lastname']),
                Encryption::encrypt($data['position']),
                Encryption::encrypt($data['phone']),
                $userId
            ];
            
            $this->db->execute($sql, $params);
            
            // Update users table if session time is provided
            if (isset($data['session_time'])) {
                $sessionTime = max(3600, min(28800, intval($data['session_time']))); // 1-8 hours
                $sql = "UPDATE users SET user_session_time = ?, user_updated_at = NOW() WHERE user_id = ?";
                $this->db->execute($sql, [$sessionTime, $userId]);
            }
            
            $this->db->commit();
            
            $this->logger->logUserActivity($userId, 'PROFILE_UPDATE', 'User updated profile');
            
            return ['success' => true, 'message' => 'อัพเดทข้อมูลสำเร็จ'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->logger->error('Profile update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัพเดทข้อมูล'];
        }
    }
    
    /**
     * Change user password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Get current password
            $sql = "SELECT user_password FROM users WHERE user_id = ?";
            $user = $this->db->fetch($sql, [$userId]);
            
            if (!$user || !password_verify($currentPassword, $user['user_password'])) {
                return ['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'];
            }
            
            // Validate new password strength
            if (!Encryption::isPasswordStrong($newPassword)) {
                return ['success' => false, 'message' => 'รหัสผ่านใหม่ไม่ตรงตามเงื่อนไข'];
            }
            
            // Update password
            $sql = "UPDATE users SET user_password = ?, user_updated_at = NOW() WHERE user_id = ?";
            $this->db->execute($sql, [password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            
            $this->logger->logUserActivity($userId, 'PASSWORD_CHANGE', 'User changed password');
            
            return ['success' => true, 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ'];
            
        } catch (Exception $e) {
            $this->logger->error('Password change error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน'];
        }
    }
    
    /**
     * Request password reset
     */
    public function requestPasswordReset($username, $reason) {
        try {
            $user = $this->getUserByUsername($username);
            
            if (!$user) {
                return ['success' => false, 'message' => 'ไม่พบชื่อผู้ใช้นี้ในระบบ'];
            }
            
            // Check if there's already a pending request
            $sql = "SELECT COUNT(*) as count FROM password_reset_requests 
                    WHERE user_id = ? AND request_status = 'pending'";
            $result = $this->db->fetch($sql, [$user['user_id']]);
            
            if ($result['count'] > 0) {
                return ['success' => false, 'message' => 'มีคำขอรีเซ็ตรหัสผ่านที่รออนุมัติอยู่แล้ว'];
            }
            
            // Create reset request
            $token = Encryption::generateRandomString(64);
            $sql = "INSERT INTO password_reset_requests (user_id, request_token, request_reason) 
                    VALUES (?, ?, ?)";
            $this->db->execute($sql, [$user['user_id'], $token, $reason]);
            
            $this->logger->logUserActivity($user['user_id'], 'PASSWORD_RESET_REQUEST', 'User requested password reset');
            
            return ['success' => true, 'message' => 'ส่งคำขอรีเซ็ตรหัสผ่านสำเร็จ รอการอนุมัติจากผู้ดูแลระบบ'];
            
        } catch (Exception $e) {
            $this->logger->error('Password reset request error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการส่งคำขอ'];
        }
    }
    
    /**
     * Check if account is locked
     */
    private function isAccountLocked($userId) {
        $sql = "SELECT user_login_attempts, user_locked_until FROM users WHERE user_id = ?";
        $result = $this->db->fetch($sql, [$userId]);
        
        if (!$result) {
            return false;
        }
        
        // Check if account is currently locked
        if ($result['user_locked_until'] && strtotime($result['user_locked_until']) > time()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Increment login attempts
     */
    private function incrementLoginAttempts($userId) {
        $sql = "UPDATE users SET 
                user_login_attempts = user_login_attempts + 1,
                user_locked_until = CASE 
                    WHEN user_login_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? SECOND)
                    ELSE user_locked_until 
                END
                WHERE user_id = ?";
        
        $this->db->execute($sql, [MAX_LOGIN_ATTEMPTS, LOCKOUT_DURATION, $userId]);
    }
    
    /**
     * Reset login attempts
     */
    private function resetLoginAttempts($userId) {
        $sql = "UPDATE users SET user_login_attempts = 0, user_locked_until = NULL WHERE user_id = ?";
        $this->db->execute($sql, [$userId]);
    }
    
    /**
     * Verify Cloudflare Turnstile
     */
    private function verifyTurnstile($token) {
        if (empty($token)) {
            return false;
        }
        
        $data = [
            'secret' => TURNSTILE_SECRET_KEY,
            'response' => $token,
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
        $result = file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
        
        if ($result === false) {
            return false;
        }
        
        $response = json_decode($result, true);
        return isset($response['success']) && $response['success'] === true;
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
}
