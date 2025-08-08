<?php
require_once __DIR__.'/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$province_code = $_GET['province_code'] ?? '';
if ($province_code==='') { echo '[]'; exit; }

$stmt = $pdo->prepare("SELECT amphur_code, amphur_name FROM amphur WHERE province_code = :p ORDER BY amphur_name");
$stmt->execute([':p'=>$province_code]);
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
