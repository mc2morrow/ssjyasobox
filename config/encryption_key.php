<?php
// config/encryption_key.php
// สร้าง encryption key สำหรับ AES-256-CBC
// ในการใช้งานจริง ควรสร้าง key ใหม่และเก็บในที่ปลอดภัย
// hL6B7IHQWJu1ooWbC1HvwWCLdHLm/pCtQQLPILvMgoI=
define('ENCRYPTION_KEY', 'your-32-character-encryption-key-here!'); // 32 characters for AES-256
define('ENCRYPTION_METHOD', 'AES-256-CBC');

// Function to generate a secure encryption key
function generateEncryptionKey() {
    return bin2hex(random_bytes(16)); // 32 character hex string
}

// Uncomment below line to generate a new key (run once and save the result)
// echo "New encryption key: " . generateEncryptionKey();