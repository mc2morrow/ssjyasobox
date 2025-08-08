<?php
// ===== Database =====
define('DB_DSN',  'mysql:host=localhost;dbname=ssjbox_db;charset=utf8mb4');
define('DB_USER', 'root');
define('DB_PASS', 'Ktza947@test');

// ===== AES-256-CBC Key (Base64 ของ 32 ไบต์) =====
// สร้างครั้งเดียวแล้วคงที่: ใช้คำสั่ง PHP ชั่วคราว: base64_encode(random_bytes(32))
define('AES_KEY_BASE64', 'hL6B7IHQWJu1ooWbC1HvwWCLdHLm/pCtQQLPILvMgoI=');

// ===== reCAPTCHA v3 =====
define('RECAPTCHA_SITE_KEY', '6LdcS54rAAAAAOUiwlOI3HE9e7DbvpRiGN6Pdwz2');
define('RECAPTCHA_SECRET',   '6LdcS54rAAAAALF1MaXfdxuHL76V2vCoTjpza1j4');
define('RECAPTCHA_MIN_SCORE', 0.5);

// ===== Register Rate Limit =====
define('REGISTER_MAX_IN_MINUTE', 3); // เกิน 3 ครั้งใน 1 นาที → โดนลงโทษแบบไล่ระดับ

// ===== Misc (ควบคุม header/error) =====
define('APP_DEBUG', false); // true เฉพาะตอน dev
