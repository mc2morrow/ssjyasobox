<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../classes/Crypto.php';
require_once __DIR__.'/../classes/RateLimit.php';

function badRequest($msg, $code=400){
  http_response_code($code);
  echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
  exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rate = new RateLimit($pdo);

// 1) เช็คว่า IP ถูกล็อกอยู่หรือยัง
$lock = $rate->isIpLocked($ip);
if ($lock['locked'] ?? false) {
  $rate->recordAttempt($ip, false);
  badRequest('IP นี้ถูกระงับชั่วคราว ถึงเวลา: '.$lock['locked_until']);
}

// 2) บันทึกความพยายาม (ยังไม่ success)
$rate->recordAttempt($ip, false);

// 3) ตรวจ reCAPTCHA v3
$token = $_POST['recaptcha_token'] ?? '';
if (!$token) badRequest('reCAPTCHA token missing.');
$ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POSTFIELDS => http_build_query([
    'secret' => RECAPTCHA_SECRET,
    'response' => $token
  ], '', '&'),
  CURLOPT_CONNECTTIMEOUT => 5,
  CURLOPT_TIMEOUT => 10
]);
$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);
if (!$response) badRequest('reCAPTCHA verify failed'.(APP_DEBUG ? " ($err)" : ''));
$rc = json_decode($response, true);
if (!($rc['success'] ?? false) || ($rc['score'] ?? 0) < RECAPTCHA_MIN_SCORE) {
  $rate->checkAndPenalizeIfNeeded($ip);
  badRequest('reCAPTCHA ไม่ผ่านการตรวจสอบ');
}

// 4) รับค่า + ตรวจความถูกต้องฝั่ง server
$province_code = trim($_POST['province_code'] ?? '');
$amphur_code   = trim($_POST['amphur_code'] ?? '');
$hosp_code     = trim($_POST['hosp_code'] ?? '');
$prefix        = trim($_POST['prefix'] ?? '');
$first_name    = trim($_POST['first_name'] ?? '');
$last_name     = trim($_POST['last_name'] ?? '');
$position      = trim($_POST['position'] ?? '');
$idcard        = trim($_POST['idcard'] ?? '');
$email         = trim($_POST['email'] ?? '');
$phone         = trim($_POST['phone'] ?? '');
$username      = trim($_POST['username'] ?? '');
$password      = $_POST['password'] ?? '';
$password2     = $_POST['password_confirm'] ?? '';

if (!$province_code || !$amphur_code || !$hosp_code) badRequest('โปรดเลือกจังหวัด/อำเภอ/หน่วยงาน');
if (!in_array($prefix, ['นาย','นาง','นางสาว'], true)) badRequest('คำนำหน้าไม่ถูกต้อง');
if ($first_name==='' || $last_name==='' || $position==='') badRequest('ข้อมูลชื่อ-สกุล-ตำแหน่งไม่ครบ');
if (!preg_match('/^\d{13}$/', $idcard)) badRequest('เลขบัตรประชาชนไม่ถูกต้อง');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) badRequest('อีเมลไม่ถูกต้อง');
if (!preg_match('/^\d{9,10}$/', $phone)) badRequest('เบอร์โทรไม่ถูกต้อง');
if ($username==='') badRequest('กรุณากำหนด Username');

$pwdOk = strlen($password) >= 12 &&
         preg_match('/[A-Z]/',$password) &&
         preg_match('/[a-z]/',$password) &&
         preg_match('/\d/',$password) &&
         preg_match('/[^A-Za-z0-9]/',$password);
if (!$pwdOk) badRequest('รหัสผ่านไม่ผ่านเงื่อนไขความปลอดภัย');
if ($password !== $password2) badRequest('รหัสผ่านไม่ตรงกัน');

// 5) ตรวจอ้างอิง FK
$chk = $pdo->prepare("SELECT COUNT(*) FROM amphur WHERE amphur_code=:a AND province_code=:p");
$chk->execute([':a'=>$amphur_code, ':p'=>$province_code]);
if ((int)$chk->fetchColumn() === 0) badRequest('ข้อมูลอำเภอ/จังหวัดไม่สอดคล้อง');

$chk = $pdo->prepare("SELECT COUNT(*) FROM hospital WHERE hosp_code=:h AND amphur_code=:a AND province_code=:p");
$chk->execute([':h'=>$hosp_code, ':a'=>$amphur_code, ':p'=>$province_code]);
if ((int)$chk->fetchColumn() === 0) badRequest('ข้อมูลหน่วยงานไม่สอดคล้อง');

// 6) ห้าม username ซ้ำ
$chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :u");
$chk->execute([':u'=>$username]);
if ((int)$chk->fetchColumn() > 0) badRequest('Username นี้ถูกใช้แล้ว');

// 7) เข้ารหัสข้อมูลส่วนบุคคล (AES-256-CBC) + bcrypt
$crypto = new Crypto(AES_KEY_BASE64);
$iv = $crypto->genIv();

$enc_first = $crypto->encryptWithIv($first_name, $iv);
$enc_last  = $crypto->encryptWithIv($last_name,  $iv);
$enc_pos   = $crypto->encryptWithIv($position,   $iv);
$enc_id    = $crypto->encryptWithIv($idcard,     $iv);
$enc_email = $crypto->encryptWithIv($email,      $iv);
$enc_phone = $crypto->encryptWithIv($phone,      $iv);

$pwd_hash = password_hash($password, PASSWORD_BCRYPT);

// 8) บันทึก
$stmt = $pdo->prepare("
  INSERT INTO users
  (username, prefix, first_name_enc, last_name_enc, position_enc, idcard_enc, email_enc, phone_enc, iv,
   password_hash, province_code, amphur_code, hosp_code, recaptcha_score, created_at)
  VALUES
  (:username, :prefix, :f, :l, :pos, :idc, :em, :ph, :iv,
   :pwd, :pc, :ac, :hc, :score, NOW())
");
$stmt->execute([
  ':username'=>$username,
  ':prefix'=>$prefix,
  ':f'=>$enc_first,
  ':l'=>$enc_last,
  ':pos'=>$enc_pos,
  ':idc'=>$enc_id,
  ':em'=>$enc_email,
  ':ph'=>$enc_phone,
  ':iv'=>$iv,
  ':pwd'=>$pwd_hash,
  ':pc'=>$province_code,
  ':ac'=>$amphur_code,
  ':hc'=>$hosp_code,
  ':score'=>$rc['score'] ?? null,
]);

// 9) มาร์ก attempt ล่าสุดเป็น success
$pdo->prepare("UPDATE register_attempts SET success=1 WHERE ip=:ip ORDER BY id DESC LIMIT 1")
    ->execute([':ip'=>$ip]);

echo "สมัครสมาชิกสำเร็จ";
