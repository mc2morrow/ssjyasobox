<?php
// config/config.php - Main Configuration File

// Prevent direct access
if (!defined('SSJBOX_ACCESS')) {
    http_response_code(403);
    exit('Access Denied');
}

// Environment Configuration
define('APP_ENV', 'development'); // production, development
define('APP_DEBUG', APP_ENV === 'development');
define('APP_NAME', 'SSJBox File Upload System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/ssjbox');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ssjbox_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Security Configuration
define('SESSION_NAME', 'SSJBOX_SESSION');
define('SESSION_SECURE', false); // Set to true for HTTPS
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Strict');

// Encryption Keys (Change these in production!)
define('ENCRYPTION_KEY', 'your-secret-encryption-key-32-chars'); // 32 characters for AES-256
define('ENCRYPTION_IV', 'your-secret-iv-16'); // 16 characters for CBC mode

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 500 * 1024 * 1024); // 500MB in bytes
define('UPLOAD_ALLOWED_EXTENSIONS', ['zip', '7z', 'rar']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Rate Limiting Configuration
define('RATE_LIMIT_REGISTER_ATTEMPTS', 3);
define('RATE_LIMIT_LOGIN_ATTEMPTS', 5);
define('RATE_LIMIT_LOCKOUT_TIMES', [
    1 => 5,    // 1st lockout: 5 minutes
    2 => 15,   // 2nd lockout: 15 minutes  
    3 => 30,   // 3rd lockout: 30 minutes
    4 => 60,   // 4th lockout: 1 hour
    5 => 1440  // 5th+ lockout: 1 day
]);

// reCAPTCHA Configuration
define('RECAPTCHA_SITE_KEY', 'your-recaptcha-site-key');
define('RECAPTCHA_SECRET_KEY', 'your-recaptcha-secret-key');
define('RECAPTCHA_MIN_SCORE', 0.5);

// Timezone Configuration
define('APP_TIMEZONE', 'Asia/Bangkok');
date_default_timezone_set(APP_TIMEZONE);

// Error Reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// PHP Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', SESSION_SECURE ? 1 : 0);
ini_set('session.cookie_samesite', SESSION_SAMESITE);
ini_set('upload_max_filesize', '500M');
ini_set('post_max_size', '500M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);
ini_set('memory_limit', '1024M');

?>

<?php
// config/database.php - Database Connection Class

// Prevent direct access
if (!defined('SSJBOX_ACCESS')) {
    http_response_code(403);
    exit('Access Denied');
}

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch(PDOException $e) {
            if (APP_DEBUG) {
                die("Database Connection Error: " . $e->getMessage());
            } else {
                error_log("Database Connection Error: " . $e->getMessage());
                die("Database connection failed. Please try again later.");
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function query($sql) {
        return $this->connection->query($sql);
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserializing
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

?>

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

?>