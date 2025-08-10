<?php
// classes/Encryption.php
require_once __DIR__ . '/../config/encryption_key.php';

class Encryption {
    private static $key;
    private static $method;
    
    public function __construct() {
        self::$key = ENCRYPTION_KEY;
        self::$method = ENCRYPTION_METHOD;
    }
    
    /**
     * Encrypt data using AES-256-CBC
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        // Generate a random IV
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
        
        // Encrypt the data
        $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
        
        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }
        
        // Combine IV and encrypted data, then base64 encode
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt data using AES-256-CBC
     */
    public static function decrypt($encryptedData) {
        if (empty($encryptedData)) {
            return '';
        }
        
        try {
            // Decode base64
            $data = base64_decode($encryptedData);
            
            if ($data === false) {
                throw new Exception('Invalid base64 data');
            }
            
            // Extract IV length
            $ivLength = openssl_cipher_iv_length(ENCRYPTION_METHOD);
            
            if (strlen($data) < $ivLength) {
                throw new Exception('Invalid encrypted data');
            }
            
            // Extract IV and encrypted data
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            
            // Decrypt
            $decrypted = openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
            
            if ($decrypted === false) {
                throw new Exception('Decryption failed');
            }
            
            return $decrypted;
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Hash data for unique checking (one-way)
     */
    public static function hash($data) {
        return hash('sha256', $data);
    }
    
    /**
     * Generate secure random string
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Generate secure password
     */
    public static function generatePassword($length = 12) {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];
        
        $allChars = $uppercase . $lowercase . $numbers . $symbols;
        
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        return str_shuffle($password);
    }
    
    /**
     * Verify password strength
     */
    public static function isPasswordStrong($password) {
        // At least 12 characters, contains uppercase, lowercase, number, and symbol
        return strlen($password) >= 12 &&
               preg_match('/[A-Z]/', $password) &&
               preg_match('/[a-z]/', $password) &&
               preg_match('/[0-9]/', $password) &&
               preg_match('/[^A-Za-z0-9]/', $password);
    }
}
