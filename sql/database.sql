-- สร้างฐานข้อมูล ssjbox_db
CREATE DATABASE IF NOT EXISTS ssjbox_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ssjbox_db;

-- ตาราง provinces (จังหวัด)
CREATE TABLE provinces (
    province_code VARCHAR(2) PRIMARY KEY,
    province_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง amphurs (อำเภอ)
CREATE TABLE amphurs (
    amphur_code VARCHAR(4) PRIMARY KEY,
    amphur_name VARCHAR(100) NOT NULL,
    province_code VARCHAR(2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (province_code) REFERENCES provinces(province_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง hospitals (หน่วยงาน)
CREATE TABLE hospitals (
    hosp_code VARCHAR(9) PRIMARY KEY,
    hosp_name VARCHAR(255) NOT NULL,
    amphur_code VARCHAR(4) NOT NULL,
    province_code VARCHAR(2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (amphur_code) REFERENCES amphurs(amphur_code),
    FOREIGN KEY (province_code) REFERENCES provinces(province_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง registers
CREATE TABLE registers (
    reg_id INT AUTO_INCREMENT PRIMARY KEY,
    reg_prefix TEXT NOT NULL, -- เข้ารหัส AES-256-CBC
    reg_firstname TEXT NOT NULL, -- เข้ารหัส AES-256-CBC
    reg_lastname TEXT NOT NULL, -- เข้ารหัส AES-256-CBC
    reg_position TEXT NOT NULL, -- เข้ารหัส AES-256-CBC
    reg_cid TEXT NOT NULL, -- เข้ารหัส AES-256-CBC (เลขประจำตัวประชาชน)
    reg_email TEXT NOT NULL, -- เข้ารหัส AES-256-CBC
    reg_phone TEXT NOT NULL, -- เข้ารหัส AES-256-CBC
    reg_hosp_code VARCHAR(9) NOT NULL, -- รหัสหน่วยงาน
    reg_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reg_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reg_hosp_code) REFERENCES hospitals(hosp_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง users
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    reg_id INT NOT NULL,
    user_name VARCHAR(50) NOT NULL UNIQUE,
    user_password VARCHAR(255) NOT NULL, -- bcrypt hash
    user_cid_hash VARCHAR(255) NOT NULL UNIQUE, -- hash ของเลขประจำตัวประชาชนสำหรับตรวจสอบ unique
    user_email_hash VARCHAR(255) NOT NULL UNIQUE, -- hash ของอีเมลสำหรับตรวจสอบ unique
    user_phone_hash VARCHAR(255) NOT NULL,
    user_role ENUM('user', 'admin') DEFAULT 'user',
    user_status ENUM('pending', 'active', 'inactive', 'banned') DEFAULT 'pending',
    user_session_time INT DEFAULT 3600, -- เวลา session ในวินาที (default 1 ชั่วโมง)
    user_last_login TIMESTAMP NULL,
    user_login_attempts INT DEFAULT 0,
    user_locked_until TIMESTAMP NULL,
    user_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reg_id) REFERENCES registers(reg_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง uploadfiles
CREATE TABLE uploadfiles (
    file_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    new_filename VARCHAR(255) NOT NULL,
    file_size BIGINT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_category ENUM('HIS', 'F43') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    upload_date DATE NOT NULL, -- วันที่ที่ข้อมูลส่ง
    file_uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_category (user_id, file_category),
    INDEX idx_upload_date (upload_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง userlogs
CREATE TABLE userlogs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    log_action VARCHAR(100) NOT NULL,
    log_details TEXT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    log_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user_action (user_id, log_action),
    INDEX idx_created_at (log_created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง password_reset_requests
CREATE TABLE password_reset_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    request_token VARCHAR(255) NOT NULL,
    request_reason TEXT NOT NULL,
    request_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_id INT NULL,
    admin_notes TEXT NULL,
    request_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    request_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง user_sessions
CREATE TABLE user_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    session_data TEXT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง system_settings
CREATE TABLE system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    setting_description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เพิ่มข้อมูลเริ่มต้นสำหรับ system_settings
INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES
('max_file_size', '536870912', 'ขนาดไฟล์สูงสุดที่อนุญาต (512MB)'),
('allowed_extensions', '.zip,.7z,.rar', 'นามสกุลไฟล์ที่อนุญาต'),
('session_timeout', '3600', 'เวลา session timeout เริ่มต้น (วินาที)'),
('max_login_attempts', '5', 'จำนวนครั้งที่พยายาม login ผิดสูงสุด'),
('lockout_duration', '1800', 'เวลา lockout เมื่อ login ผิดเกินกำหนด (วินาที)');

-- สร้าง trigger สำหรับลบ session ที่หมดอายุ
DELIMITER //
CREATE EVENT cleanup_expired_sessions
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    DELETE FROM user_sessions WHERE expires_at < NOW();
END //
DELIMITER ;