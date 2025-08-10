<?php
// ajax/get_amphur.php - แก้ไขใหม่ทั้งหมด
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Prevent direct access
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Direct access not allowed']);
    exit;
}

try {
    require_once '../config/config.php';
    require_once '../classes/Database.php';
    require_once '../classes/Register.php';
    require_once '../classes/Logger.php';
    
    // Initialize logger
    $logger = new Logger();
    
    // Validate input
    if (!isset($_GET['province_code']) || empty($_GET['province_code'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Province code is required',
            'amphurs' => []
        ]);
        exit;
    }
    
    $provinceCode = trim($_GET['province_code']);
    
    // Validate province code format (should be 2 digits)
    if (!preg_match('/^[0-9]{2}$/', $provinceCode)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid province code format',
            'amphurs' => []
        ]);
        exit;
    }
    
    // Get amphurs
    $register = new Register();
    $amphurs = $register->getAmphurs($provinceCode);
    
    // Log the request
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logger->logUserActivity(null, 'AJAX_GET_AMPHURS', "Province: $provinceCode, Results: " . count($amphurs), $clientIP);
    
    // Return response
    echo json_encode([
        'success' => true,
        'message' => 'Amphurs loaded successfully',
        'amphurs' => $amphurs,
        'count' => count($amphurs)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Log error
    if (isset($logger)) {
        $logger->error('AJAX get_amphur error: ' . $e->getMessage());
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'amphurs' => []
    ]);
}
