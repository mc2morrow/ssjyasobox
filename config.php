<?php
// config.php
$DB_DSN  = 'mysql:host=localhost;dbname=ssjbox_db;charset=utf8mb4';
$DB_USER = 'root';
$DB_PASS = 'Ktza947@test';

$pdo = new PDO($DB_DSN, $DB_USER, $DB_PASS, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ===== Security Secrets =====
// สร้างคีย์ 32 ไบต์แบบสุ่มจริง ๆ และเก็บนอกซอร์สโค้ดถ้าเป็นโปรดักชัน
$AES_KEY = hex2bin('8a8c7f0a0c5d9a1e3b6f1c2d4e7f902134567890abcdef001122334455667788'); // 32 bytes
$HMAC_KEY = hex2bin('6f2b1c9d7e5a4b3c2d1e0f9a8b7c6d5e00112233445566778899aabbccddeeff'); // 32 bytes

// reCAPTCHA v3
$RECAPTCHA_SITE_KEY = 'YOUR_RECAPTCHA_V3_SITE_KEY';
$RECAPTCHA_SECRET   = 'YOUR_RECAPTCHA_V3_SECRET';

// คะแนนขั้นต่ำ (ปรับตามจริง)
$RECAPTCHA_MIN_SCORE = 0.5;
