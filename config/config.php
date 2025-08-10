<?php
// config/config.php - แก้ไขส่วน Error Reporting และเพิ่มการตั้งค่าที่ขาดหายไป
define('APP_NAME', 'SSJBox File Upload System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/ssjbox');
define('APP_TIMEZONE', 'Asia/Bangkok');

// File Upload Settings
define('MAX_FILE_SIZE', 536870912); // 512MB
define('ALLOWED_EXTENSIONS', ['.zip', '.7z', '.rar']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Session Settings
define('SESSION_TIMEOUT', 3600); // 1 hour default
define('SESSION_NAME', 'SSJBOX_SESSION');
define('COOKIE_LIFETIME', 86400 * 7); // 7 days for remember me

// Security Settings
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 1800); // 30 minutes
define('CSRF_TOKEN_EXPIRE', 3600); // 1 hour

// Cloudflare Turnstile
define('TURNSTILE_SITE_KEY', '0x4AAAAAABp8ocTYg6Sn98ai');
define('TURNSTILE_SECRET_KEY', '0x4AAAAAABp8oYOfiQvZZmLKs2hk-HkD7S4');

// Logging
define('LOG_PATH', __DIR__ . '/../logs/');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR

// Environment และ Error Reporting - แก้ไขใหม่
define('ENVIRONMENT', 'development'); // development, staging, production

// Error Reporting Configuration
if (ENVIRONMENT === 'development') {
    // Development Environment
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . 'php_errors.log');
    
    // Enable detailed error messages
    ini_set('html_errors', 1);
    ini_set('docref_root', 'http://www.php.net/');
    
    // Show all PHP errors in development
    ini_set('track_errors', 1);
    
} elseif (ENVIRONMENT === 'staging') {
    // Staging Environment
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . 'php_errors.log');
    
    // Limited error display for staging
    ini_set('html_errors', 0);
    
} else {
    // Production Environment
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . 'php_errors.log');
    
    // No error display in production
    ini_set('html_errors', 0);
    ini_set('track_errors', 0);
    
    // Additional production security
    ini_set('expose_php', 0);
    ini_set('allow_url_fopen', 0);
    ini_set('allow_url_include', 0);
}

// Custom Error Handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    // Don't execute PHP internal error handler
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE_ERROR',
        E_CORE_WARNING => 'CORE_WARNING',
        E_COMPILE_ERROR => 'COMPILE_ERROR',
        E_COMPILE_WARNING => 'COMPILE_WARNING',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        E_STRICT => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER_DEPRECATED'
    ];
    
    $errorType = $errorTypes[$errno] ?? 'UNKNOWN';
    $timestamp = date('Y-m-d H:i:s');
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'Unknown';
    $clientIP = getClientIP();
    
    $errorMessage = "[{$timestamp}] [{$errorType}] {$errstr} in {$errfile} on line {$errline}" . PHP_EOL;
    $errorMessage .= "Request: {$requestUri}" . PHP_EOL;
    $errorMessage .= "User Agent: {$userAgent}" . PHP_EOL;
    $errorMessage .= "Client IP: {$clientIP}" . PHP_EOL;
    $errorMessage .= "Memory Usage: " . memory_get_usage(true) . " bytes" . PHP_EOL;
    $errorMessage .= "Peak Memory: " . memory_get_peak_usage(true) . " bytes" . PHP_EOL;
    $errorMessage .= str_repeat('-', 80) . PHP_EOL;
    
    // Ensure log directory exists
    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }
    
    // Write to log file
    file_put_contents(LOG_PATH . 'php_errors.log', $errorMessage, FILE_APPEND | LOCK_EX);
    
    // In development, also display error
    if (ENVIRONMENT === 'development') {
        echo "<div style='background: #ffcccc; border: 1px solid #ff0000; padding: 10px; margin: 10px; font-family: monospace;'>";
        echo "<strong>[{$errorType}]</strong> {$errstr}<br>";
        echo "<strong>File:</strong> {$errfile}<br>";
        echo "<strong>Line:</strong> {$errline}<br>";
        echo "</div>";
    }
    
    return true;
}

// Custom Exception Handler
function customExceptionHandler($exception) {
    $timestamp = date('Y-m-d H:i:s');
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'Unknown';
    $clientIP = getClientIP();
    
    $errorMessage = "[{$timestamp}] [EXCEPTION] " . $exception->getMessage() . PHP_EOL;
    $errorMessage .= "File: " . $exception->getFile() . " Line: " . $exception->getLine() . PHP_EOL;
    $errorMessage .= "Request: {$requestUri}" . PHP_EOL;
    $errorMessage .= "User Agent: {$userAgent}" . PHP_EOL;
    $errorMessage .= "Client IP: {$clientIP}" . PHP_EOL;
    $errorMessage .= "Stack Trace:" . PHP_EOL . $exception->getTraceAsString() . PHP_EOL;
    $errorMessage .= str_repeat('-', 80) . PHP_EOL;
    
    // Ensure log directory exists
    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }
    
    // Write to log file
    file_put_contents(LOG_PATH . 'exceptions.log', $errorMessage, FILE_APPEND | LOCK_EX);
    
    // Show user-friendly error page
    if (ENVIRONMENT === 'production') {
        // Redirect to error page
        if (!headers_sent()) {
            http_response_code(500);
            header('Location: /pages/500.php');
            exit;
        }
    } else {
        // Show detailed error in development
        echo "<div style='background: #ffeeee; border: 2px solid #ff0000; padding: 20px; margin: 20px; font-family: monospace;'>";
        echo "<h3 style='color: #cc0000;'>Uncaught Exception</h3>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
        echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
        echo "<h4>Stack Trace:</h4>";
        echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        echo "</div>";
    }
}

// Fatal Error Handler
function fatalErrorHandler() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $timestamp = date('Y-m-d H:i:s');
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'Unknown';
        $clientIP = getClientIP();
        
        $errorMessage = "[{$timestamp}] [FATAL] {$error['message']} in {$error['file']} on line {$error['line']}" . PHP_EOL;
        $errorMessage .= "Request: {$requestUri}" . PHP_EOL;
        $errorMessage .= "User Agent: {$userAgent}" . PHP_EOL;
        $errorMessage .= "Client IP: {$clientIP}" . PHP_EOL;
        $errorMessage .= str_repeat('-', 80) . PHP_EOL;
        
        // Ensure log directory exists
        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0755, true);
        }
        
        file_put_contents(LOG_PATH . 'fatal_errors.log', $errorMessage, FILE_APPEND | LOCK_EX);
        
        if (ENVIRONMENT === 'production' && !headers_sent()) {
            http_response_code(500);
            include_once __DIR__ . '/../pages/500.php';
            exit;
        }
    }
}

// Get Client IP function
function getClientIP() {
    $ipKeys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Set custom error handlers
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');
register_shutdown_function('fatalErrorHandler');

// PHP Configuration for Security and Performance
ini_set('max_execution_time', 300); // 5 minutes for file uploads
ini_set('max_input_time', 300);
ini_set('memory_limit', '256M');
ini_set('post_max_size', '550M'); // Slightly more than max file size
ini_set('upload_max_filesize', '512M');
ini_set('max_file_uploads', 5);

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Ensure required directories exist
$requiredDirs = [LOG_PATH, UPLOAD_PATH, UPLOAD_PATH . 'his/', UPLOAD_PATH . 'f43/'];
foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Application Health Check
function checkSystemHealth() {
    $health = [
        'status' => 'ok',
        'timestamp' => date('c'),
        'checks' => []
    ];
    
    // Check database connection
    try {
        require_once __DIR__ . '/database.php';
        $dsn = "mysql:host=" . DatabaseConfig::HOST . ";dbname=" . DatabaseConfig::DATABASE;
        $pdo = new PDO($dsn, DatabaseConfig::USERNAME, DatabaseConfig::PASSWORD);
        $health['checks']['database'] = 'ok';
    } catch (Exception $e) {
        $health['checks']['database'] = 'error';
        $health['status'] = 'error';
    }
    
    // Check upload directory
    $health['checks']['upload_dir'] = is_writable(UPLOAD_PATH) ? 'ok' : 'error';
    if (!is_writable(UPLOAD_PATH)) {
        $health['status'] = 'error';
    }
    
    // Check log directory
    $health['checks']['log_dir'] = is_writable(LOG_PATH) ? 'ok' : 'error';
    if (!is_writable(LOG_PATH)) {
        $health['status'] = 'error';
    }
    
    // Check disk space
    $freeBytes = disk_free_space(UPLOAD_PATH);
    $health['checks']['disk_space'] = $freeBytes > (1024 * 1024 * 1024) ? 'ok' : 'warning'; // 1GB minimum
    
    // Check memory usage
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = ini_get('memory_limit');
    $health['checks']['memory'] = $memoryUsage < (0.8 * return_bytes($memoryLimit)) ? 'ok' : 'warning';
    
    return $health;
}

// Convert memory limit to bytes
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

// Log application startup
if (ENVIRONMENT === 'development') {
    $startupMessage = "[" . date('Y-m-d H:i:s') . "] Application started in " . ENVIRONMENT . " mode" . PHP_EOL;
    file_put_contents(LOG_PATH . 'application.log', $startupMessage, FILE_APPEND | LOCK_EX);
}
