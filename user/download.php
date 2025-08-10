<?php
// user/download.php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Session.php';
require_once '../classes/FileUpload.php';

$session = new Session();
$fileUpload = new FileUpload();

if (!$session->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$userId = $session->getUserId();
$fileId = $_GET['id'] ?? null;

if (!$fileId || !is_numeric($fileId)) {
    header('Location: dashboard.php?msg=invalid_file');
    exit;
}

$result = $fileUpload->downloadFile($fileId, $userId);

if (!$result['success']) {
    header('Location: dashboard.php?msg=download_error');
    exit;
}

$file = $result['file'];

// Check if file exists
if (!file_exists($file['file_path'])) {
    header('Location: dashboard.php?msg=file_not_found');
    exit;
}

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file['file_path']));

// Clear output buffer
ob_clean();
flush();

// Read and output file
readfile($file['file_path']);
exit;