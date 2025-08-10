<?php
// ajax/check_duplicate.php - แก้ไขใหม่ทั้งหมด
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Prevent direct access
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['exists' => false, 'message' => 'Direct access not allowed']);
    exit;
}

try {
    require_once '../config/config.php';
    require_once '../classes/Database.php';
    require_once '../classes/Register.php';
    require_once '../classes/Logger.php';
    
    // Initialize objects
    $register = new Register();
    $logger = new Logger();
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $response = ['exists' => false];
    
    // Check username
    if (isset($_GET['username']) && !empty($_GET['username'])) {
        $username = trim($_GET['username']);
        
        // Validate username format
        if (strlen($username) >= 4 && strlen($username) <= 20 && preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $exists = $register->isUsernameExists($username);
            $response['exists'] = $exists;
            $response['type'] = 'username';
            $response['message'] = $exists ? 'Username already exists' : 'Username is available';
            
            // Log the check
            $logger->logUserActivity(null, 'USERNAME_CHECK', "Username: $username, Exists: " . ($exists ? 'Yes' : 'No'), $clientIP);
        } else {
            $response['exists'] = true; // Consider invalid format as "exists" to prevent use
            $response['type'] = 'username';
            $response['message'] = 'Invalid username format';
        }
    }
    
    // Check email
    elseif (isset($_GET['email']) && !empty($_GET['email'])) {
        $email = trim($_GET['email']);
        
        // Validate email format
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $exists = $register->isEmailExists($email);
            $response['exists'] = $exists;
            $response['type'] = 'email';
            $response['message'] = $exists ? 'Email already exists' : 'Email is available';
            
            // Log the check (don't log full email for privacy)
            $emailDomain = substr($email, strpos($email, '@'));
            $logger->logUserActivity(null, 'EMAIL_CHECK', "Email domain: $emailDomain, Exists: " . ($exists ? 'Yes' : 'No'), $clientIP);
        } else {
            $response['exists'] = true; // Consider invalid format as "exists"
            $response['type'] = 'email';
            $response['message'] = 'Invalid email format';
        }
    }
    
    // Check CID
    elseif (isset($_GET['cid']) && !empty($_GET['cid'])) {
        $cid = trim($_GET['cid']);
        
        // Validate CID format
        if (preg_match('/^[0-9]{13}$/', $cid)) {
            $exists = $register->isCIDExists($cid);
            $response['exists'] = $exists;
            $response['type'] = 'cid';
            $response['message'] = $exists ? 'CID already exists' : 'CID is available';
            
            // Log the check (don't log full CID for privacy)
            $maskedCID = substr($cid, 0, 4) . '****' . substr($cid, -3);
            $logger->logUserActivity(null, 'CID_CHECK', "CID: $maskedCID, Exists: " . ($exists ? 'Yes' : 'No'), $clientIP);
        } else {
            $response['exists'] = true; // Consider invalid format as "exists"
            $response['type'] = 'cid';
            $response['message'] = 'Invalid CID format';
        }
    }
    
    else {
        http_response_code(400);
        $response = [
            'exists' => false,
            'message' => 'No valid parameter provided'
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error
    if (isset($logger)) {
        $logger->error('AJAX check_duplicate error: ' . $e->getMessage());
    }
    
    http_response_code(500);
    echo json_encode([
        'exists' => false,
        'message' => 'Internal server error'
    ]);
}
