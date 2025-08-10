<?php
// ajax/get_hospital.php - แก้ไขใหม่ทั้งหมด
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
    if (!isset($_GET['amphur_code']) || empty($_GET['amphur_code'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Amphur code is required',
            'hospitals' => []
        ]);
        exit;
    }
    
    $amphurCode = trim($_GET['amphur_code']);
    
    // Validate amphur code format (should be 4 digits)
    if (!preg_match('/^[0-9]{4}$/', $amphurCode)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid amphur code format',
            'hospitals' => []
        ]);
        exit;
    }
    
    // Get hospitals
    $register = new Register();
    $hospitals = $register->getHospitals($amphurCode);
    
    // Log the request
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logger->logUserActivity(null, 'AJAX_GET_HOSPITALS', "Amphur: $amphurCode, Results: " . count($hospitals), $clientIP);
    
    // Return response
    echo json_encode([
        'success' => true,
        'message' => 'Hospitals loaded successfully',
        'hospitals' => $hospitals,
        'count' => count($hospitals)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Log error
    if (isset($logger)) {
        $logger->error('AJAX get_hospital error: ' . $e->getMessage());
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'hospitals' => []
    ]);
}
