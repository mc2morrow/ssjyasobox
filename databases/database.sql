-- สร้างฐานข้อมูล users
CREATE DATABASE IF NOT EXISTS users CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE users;

-- สร้างตาราง users
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    register_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ข้อมูลทดสอบ (ผ่านการเข้ารหัสด้วย password_hash)
-- รหัสผ่านคือ "password123" สำหรับผู้ใช้ทดสอบ
INSERT INTO users (username, password, firstname, lastname, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'admin@example.com'),
('testuser', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test', 'User', 'test@example.com');