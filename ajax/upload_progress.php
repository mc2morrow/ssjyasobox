<?php
// ajax/upload_progress.php - แก้ไขใหม่ทั้งหมด
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Prevent direct access
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['error' => 'Direct access not allowed']);
    exit;
}

try {
    require_once '../config/config.php';
    require_once '../classes/Session.php';
    
    $session = new Session();
    
    // Check if user is logged in
    if (!$session->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    
    if (!isset($_GET['upload_id']) || empty($_GET['upload_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No upload ID provided']);
        exit;
    }
    
    $uploadId = trim($_GET['upload_id']);
    
    // Validate upload ID format (should be alphanumeric)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $uploadId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid upload ID format']);
        exit;
    }
    
    // Check for upload progress in session
    $progressKey = 'upload_progress_' . $uploadId;
    $progress = $_SESSION[$progressKey] ?? null;
    
    if ($progress) {
        echo json_encode($progress);
    } else {
        // Default response when no progress found
        echo json_encode([
            'percent' => 0,
            'uploaded' => 0,
            'total' => 0,
            'speed' => 0,
            'status' => 'starting',
            'message' => 'Upload starting...'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'percent' => 0
    ]);
}
