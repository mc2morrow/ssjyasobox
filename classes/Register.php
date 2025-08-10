<?php
// classes/Register.php
class Register {
    private $db;
    private $logger;
    private $errors = [];
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }
    
    /**
     * Register new user
     */
    public function registerUser($data) {
        try {
            $this->validateRegistrationData($data);
            
            if (!empty($this->errors)) {
                return ['success' => false, 'errors' => $this->errors];
            }
            
            // Check for duplicates
            if ($this->isDuplicateUser($data['cid'], $data['email'])) {
                return ['success' => false, 'errors' => ['เลขประจำตัวประชาชนหรืออีเมลนี้ถูกใช้แล้ว']];
            }
            
            $this->db->beginTransaction();
            
            // Insert into registers table
            $regId = $this->insertRegisterData($data);
            
            // Insert into users table
            $userId = $this->insertUserData($regId, $data);
            
            $this->db->commit();
            
            $this->logger->logUserActivity($userId, 'REGISTER', 'New user registered', $this->getClientIP());
            
            return ['success' => true, 'user_id' => $userId, 'message' => 'สมัครสมาชิกสำเร็จ รอการอนุมัติจากผู้ดูแลระบบ'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->logger->error('Registration failed: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['เกิดข้อผิดพลาดในการสมัครสมาชิก']];
        }
    }
    
    /**
     * Validate registration data
     */
    private function validateRegistrationData($data) {
        // Required fields
        $requiredFields = ['prefix', 'firstname', 'lastname', 'position', 'cid', 
                          'email', 'phone', 'province', 'amphur', 'hospital', 
                          'username', 'password', 'confirm_password'];
        
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $this->errors[] = "กรุณากรอก" . $this->getFieldName($field);
            }
        }
        
        // Validate CID (13 digits)
        if (!empty($data['cid']) && !$this->isValidCID($data['cid'])) {
            $this->errors[] = 'เลขประจำตัวประชาชนไม่ถูกต้อง (ต้องเป็น 13 หลัก)';
        }
        
        // Validate email
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
        }
        
        // Validate phone number
        if (!empty($data['phone']) && !preg_match('/^[0-9]{10}$/', $data['phone'])) {
            $this->errors[] = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 หลัก';
        }
        
        // Validate username
        if (!empty($data['username'])) {
            if (strlen($data['username']) < 4 || strlen($data['username']) > 20) {
                $this->errors[] = 'ชื่อผู้ใช้ต้องมีความยาว 4-20 ตัวอักษร';
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
                $this->errors[] = 'ชื่อผู้ใช้ประกอบด้วยตัวอักษร ตัวเลข และ _ เท่านั้น';
            }
        }
        
        // Validate password
        if (!empty($data['password'])) {
            if (!Encryption::isPasswordStrong($data['password'])) {
                $this->errors[] = 'รหัสผ่านต้องมีความยาวอย่างน้อย 12 ตัวอักษร และประกอบด้วยตัวอักษรใหญ่ เล็ก ตัวเลข และสัญลักษณ์';
            }
            
            if ($data['password'] !== $data['confirm_password']) {
                $this->errors[] = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
            }
        }
        
        // Validate Turnstile
        if (!$this->verifyTurnstile($data['cf-turnstile-response'] ?? '')) {
            $this->errors[] = 'การยืนยันความปลอดภัยไม่สำเร็จ';
        }
    }
    
    /**
     * Check for duplicate user
     */
    private function isDuplicateUser($cid, $email) {
        $cidHash = Encryption::hash($cid);
        $emailHash = Encryption::hash($email);
        
        $sql = "SELECT COUNT(*) as count FROM users WHERE user_cid_hash = ? OR user_email_hash = ?";
        $result = $this->db->fetch($sql, [$cidHash, $emailHash]);
        
        return $result['count'] > 0;
    }
    
    /**
     * Check if email exists
     */
    public function isEmailExists($email) {
        try {
            $emailHash = Encryption::hash($email);
            $sql = "SELECT COUNT(*) as count FROM users WHERE user_email_hash = ?";
            $result = $this->db->fetch($sql, [$emailHash]);
            return $result['count'] > 0;
        } catch (Exception $e) {
            $this->logger->error('Email existence check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if CID exists
     */
    public function isCIDExists($cid) {
        try {
            $cidHash = Encryption::hash($cid);
            $sql = "SELECT COUNT(*) as count FROM users WHERE user_cid_hash = ?";
            $result = $this->db->fetch($sql, [$cidHash]);
            return $result['count'] > 0;
        } catch (Exception $e) {
            $this->logger->error('CID existence check failed: ' . $e->getMessage());
            return false;
        }
    }
    /**
     * Insert registration data
     */
    private function insertRegisterData($data) {
        $sql = "INSERT INTO registers (reg_prefix, reg_firstname, reg_lastname, reg_position, 
                reg_cid, reg_email, reg_phone, reg_hosp_code) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            Encryption::encrypt($data['prefix']),
            Encryption::encrypt($data['firstname']),
            Encryption::encrypt($data['lastname']),
            Encryption::encrypt($data['position']),
            Encryption::encrypt($data['cid']),
            Encryption::encrypt($data['email']),
            Encryption::encrypt($data['phone']),
            $data['hospital']
        ];
        
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }
    
    /**
     * Insert user data
     */
    private function insertUserData($regId, $data) {
        $sql = "INSERT INTO users (reg_id, user_name, user_password, user_cid_hash, 
                user_email_hash, user_phone_hash) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $params = [
            $regId,
            $data['username'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            Encryption::hash($data['cid']),
            Encryption::hash($data['email']),
            Encryption::hash($data['phone'])
        ];
        
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }
    
    /**
     * Validate Thai National ID
     */
    private function isValidCID($cid) {
        if (!preg_match('/^[0-9]{13}$/', $cid)) {
            return false;
        }
        
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += intval($cid[$i]) * (13 - $i);
        }
        
        $checkDigit = (11 - ($sum % 11)) % 10;
        return $checkDigit == intval($cid[12]);
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
     * Get field name in Thai
     */
    private function getFieldName($field) {
        $fieldNames = [
            'prefix' => 'คำนำหน้า',
            'firstname' => 'ชื่อ',
            'lastname' => 'นามสกุล',
            'position' => 'ตำแหน่ง',
            'cid' => 'เลขประจำตัวประชาชน',
            'email' => 'อีเมล',
            'phone' => 'เบอร์โทรศัพท์',
            'province' => 'จังหวัด',
            'amphur' => 'อำเภอ',
            'hospital' => 'หน่วยงาน',
            'username' => 'ชื่อผู้ใช้',
            'password' => 'รหัสผ่าน',
            'confirm_password' => 'ยืนยันรหัสผ่าน'
        ];
        
        return $fieldNames[$field] ?? $field;
    }
    
    /**
     * Get provinces
     */
    public function getProvinces() {
        $sql = "SELECT province_code, province_name FROM provinces ORDER BY province_name";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Get amphurs by province
     */
    public function getAmphurs($provinceCode) {
        $sql = "SELECT amphur_code, amphur_name FROM amphurs 
                WHERE province_code = ? ORDER BY amphur_name";
        return $this->db->fetchAll($sql, [$provinceCode]);
    }
    
    /**
     * Get hospitals by amphur
     */
    public function getHospitals($amphurs) {
        $sql = "SELECT hosp_code, hosp_name FROM hospitals 
                WHERE amphur_code = ? ORDER BY hosp_name";
        return $this->db->fetchAll($sql, [$amphurs]);
    }
    
    /**
     * Check if username exists
     */
    public function isUsernameExists($username) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE user_name = ?";
        $result = $this->db->fetch($sql, [$username]);
        return $result['count'] > 0;
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
