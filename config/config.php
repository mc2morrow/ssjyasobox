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
define('DB_PASS', 'Ktza947@test');
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

