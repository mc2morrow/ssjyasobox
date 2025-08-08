<?php
require_once __DIR__.'/config.php';

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    if (APP_DEBUG) {
        // โหมด debug: แสดงข้อความ error เต็ม
        echo "Database connection failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    } else {
        // โหมด production: แสดงข้อความทั่วไป ไม่เปิดเผยข้อมูลภายใน
        echo "ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่ภายหลัง";
    }

    //echo "Database connection failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    //echo "ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่ภายหลัง";
    exit;
}
