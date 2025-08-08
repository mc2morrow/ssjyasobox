<?php
// config/encryption.php - Encryption Helper Class

// Prevent direct access
if (!defined('SSJBOX_ACCESS')) {
    http_response_code(403);
    exit('Access Denied');
}

class Encryption {
    private static $cipher = 'AES-256-CBC';
    private static $key;
    private static $iv_length;
    
    public static function init() {
        self::$key = hash('sha256', ENCRYPTION_KEY, true);
        self::$iv_length = openssl_cipher_iv_length(self::$cipher);
    }
    
    /**
     * Encrypt data using AES-256-CBC
     * @param string $data - Data to encrypt
     * @return string|false - Base64 encoded encrypted data or false on failure
     */
    public static function encrypt($data) {
        if (empty($data)) return '';
        
        self::init();
        
        // Generate random IV
        $iv = openssl_random_pseudo_bytes(self::$iv_length);
        
        // Encrypt the data
        $encrypted = openssl_encrypt($data, self::$cipher, self::$key, OPENSSL_RAW_DATA, $iv);
        
        if ($encrypted === false) {
            return false;
        }
        
        // Combine IV and encrypted data, then base64 encode
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt data using AES-256-CBC
     * @param string $encrypted_data - Base64 encoded encrypted data
     * @return string|false - Decrypted data or false on failure
     */
    public static function decrypt($encrypted_data) {
        if (empty($encrypted_data)) return '';
        
        self::init();
        
        // Base64 decode
        $data = base64_decode($encrypted_data);
        
        if ($data === false || strlen($data) < self::$iv_length) {
            return false;
        }
        
        // Extract IV and encrypted data
        $iv = substr($data, 0, self::$iv_length);
        $encrypted = substr($data, self::$iv_length);
        
        // Decrypt the data
        $decrypted = openssl_decrypt($encrypted, self::$cipher, self::$key, OPENSSL_RAW_DATA, $iv);
        
        return $decrypted;
    }
    
    /**
     * Hash password using bcrypt
     * @param string $password - Plain text password
     * @return string|false - Hashed password or false on failure
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verify password against hash
     * @param string $password - Plain text password
     * @param string $hash - Hashed password
     * @return bool - True if password matches, false otherwise
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random string
     * @param int $length - Length of random string
     * @return string - Random string
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Generate file hash for duplicate detection
     * @param string $file_path - Path to file
     * @return string|false - SHA-256 hash or false on failure
     */
    public static function generateFileHash($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        return hash_file('sha256', $file_path);
    }
}
