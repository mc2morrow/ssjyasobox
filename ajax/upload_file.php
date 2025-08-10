<?php
// ajax/upload_file.php - ไฟล์ใหม่สำหรับ AJAX upload
header('Content-Type: application/json; charset=utf-8');

// Prevent direct access
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Direct access not allowed']);
    exit;
}

try {
    require_once '../config/config.php';
    require_once '../classes/Database.php';
    require_once '../classes/Session.php';
    require_once '../classes/FileUpload.php';
    require_once '../classes/Logger.php';
    
    $session = new Session();
    $fileUpload = new FileUpload();
    $logger = new Logger();
    
    // Check if user is logged in
    if (!$session->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'ไม่ได้เข้าสู่ระบบ']);
        exit;
    }
    
    $userId = $session->getUserId();
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$session->verifyCSRFToken($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    
    // Check if files were uploaded
    if (!isset($_FILES['upload_file']) || !isset($_POST['file_category']) || !isset($_POST['upload_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }
    
    // Validate file category
    $allowedCategories = ['HIS', 'F43'];
    if (!in_array($_POST['file_category'], $allowedCategories)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ประเภทไฟล์ไม่ถูกต้อง']);
        exit;
    }
    
    // Validate upload date
    $uploadDate = $_POST['upload_date'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $uploadDate) || strtotime($uploadDate) === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'วันที่ไม่ถูกต้อง']);
        exit;
    }
    
    // Check if upload date is not in the future
    if (strtotime($uploadDate) > time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'วันที่ส่งข้อมูลไม่สามารถเป็นวันในอนาคตได้']);
        exit;
    }
    
    // Upload file
    $result = $fileUpload->uploadFile(
        $userId, 
        $_FILES['upload_file'], 
        $_POST['file_category'], 
        $uploadDate
    );
    
    // Log the upload attempt
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $fileName = $_FILES['upload_file']['name'] ?? 'unknown';
    $fileSize = $_FILES['upload_file']['size'] ?? 0;
    
    if ($result['success']) {
        $logger->logUserActivity($userId, 'FILE_UPLOAD_SUCCESS', 
            "File: $fileName, Size: $fileSize, Category: {$_POST['file_category']}", $clientIP);
    } else {
        $logger->logUserActivity($userId, 'FILE_UPLOAD_FAILED', 
            "File: $fileName, Error: {$result['message']}", $clientIP);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    // Log error
    if (isset($logger) && isset($userId)) {
        $logger->error('AJAX file upload error: ' . $e->getMessage());
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage()
    ]);
}
