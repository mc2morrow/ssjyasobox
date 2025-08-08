<?php
require_once __DIR__.'/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$amphur_code = $_GET['amphur_code'] ?? '';
if ($amphur_code==='') { echo '[]'; exit; }

$stmt = $pdo->prepare("SELECT hosp_code, hosp_shortname, hosp_fullname FROM hospital WHERE amphur_code = :a
AND hosp_code9new NOT LIKE 'AA%' AND hosp_code9new NOT LIKE 'BA%' AND hosp_code9new NOT LIKE 'EA%' ORDER BY hosp_shortname");
$stmt->execute([':a'=>$amphur_code]);
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
