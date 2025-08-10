-- ข้อมูลตัวอย่างจังหวัด อำเภอ และหน่วยงาน
USE ssjbox_db;

-- เพิ่มข้อมูลจังหวัด (ตัวอย่างบางจังหวัด)
INSERT INTO provinces (province_code, province_name) VALUES
('34', 'อุบลราชธานี'),
('30', 'นครราชสีมา'),
('10', 'กรุงเทพมหานคร'),
('50', 'เชียงใหม่'),
('80', 'สุราษฎร์ธานี');

-- เพิ่มข้อมูลอำเภอ (ตัวอย่าง)
INSERT INTO amphurs (amphur_code, amphur_name, province_code) VALUES
('3401', 'เมืองอุบลราชธานี', '34'),
('3402', 'ศรีเมืองใหม่', '34'),
('3403', 'เขื่องใน', '34'),
('3001', 'เมืองนครราชสีมา', '30'),
('3002', 'ครบุรี', '30'),
('1001', 'พระนคร', '10'),
('1002', 'ดุสิต', '10'),
('5001', 'เมืองเชียงใหม่', '50'),
('8001', 'เมืองสุราษฎร์ธานี', '80');

-- เพิ่มข้อมูลหน่วยงาน (ตัวอย่าง)
INSERT INTO hospitals (hosp_code, hosp_name, amphur_code, province_code) VALUES
('340100001', 'โรงพยาบาลอุบลราชธานี', '3401', '34'),
('340100002', 'โรงพยาบาลส่งเสริมสุขภาพตำบลแสนสุข', '3401', '34'),
('340200001', 'โรงพยาบาลศรีเมืองใหม่', '3402', '34'),
('340300001', 'โรงพยาบาลเขื่องใน', '3403', '34'),
('300100001', 'โรงพยาบาลมหาราชนครราชสีมา', '3001', '30'),
('100100001', 'โรงพยาบาลราชวิถี', '1001', '10'),
('500100001', 'โรงพยาบาลมหาราชนครเชียงใหม่', '5001', '50'),
('800100001', 'โรงพยาบาลสุราษฎร์ธานี', '8001', '80');

-- สร้างข้อมูลทดสอบ Admin
INSERT INTO registers (reg_prefix, reg_firstname, reg_lastname, reg_position, reg_cid, reg_email, reg_phone, reg_hosp_code) VALUES
-- ข้อมูลนี้จะต้องเข้ารหัสด้วย AES-256-CBC ในระบบจริง
('encrypted_นาย', 'encrypted_ผู้ดูแลระบบ', 'encrypted_หลัก', 'encrypted_ผู้ดูแลระบบ', 'encrypted_1234567890123', 'encrypted_admin@ssjbox.com', 'encrypted_0812345678', '340100001');

-- สร้างข้อมูล user admin
INSERT INTO users (reg_id, user_name, user_password, user_cid_hash, user_email_hash, user_phone_hash, user_role, user_status) VALUES
(1, 'admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password123
 SHA2('1234567890123', 256), SHA2('admin@ssjbox.com', 256), SHA2('0812345678', 256), 'admin', 'active');

-- สร้างข้อมูลทดสอบ User
INSERT INTO registers (reg_prefix, reg_firstname, reg_lastname, reg_position, reg_cid, reg_email, reg_phone, reg_hosp_code) VALUES
('encrypted_นางสาว', 'encrypted_สมหญิง', 'encrypted_ใจดี', 'encrypted_เจ้าหน้าที่คอมพิวเตอร์', 'encrypted_9876543210987', 'encrypted_user@hospital.com', 'encrypted_0898765432', '340100001');

INSERT INTO users (reg_id, user_name, user_password, user_cid_hash, user_email_hash, user_phone_hash, user_role, user_status) VALUES
(2, 'user001', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password123
 SHA2('9876543210987', 256), SHA2('user@hospital.com', 256), SHA2('0898765432', 256), 'user', 'active');

-- อีกหนึ่งข้อมูลทดสอบ User
INSERT INTO registers (reg_prefix, reg_firstname, reg_lastname, reg_position, reg_cid, reg_email, reg_phone, reg_hosp_code) VALUES
('encrypted_นาย', 'encrypted_สมชาย', 'encrypted_ดีมาก', 'encrypted_นักวิเคราะห์ระบบ', 'encrypted_5555555555555', 'encrypted_test@hospital.com', 'encrypted_0855555555', '340200001');

INSERT INTO users (reg_id, user_name, user_password, user_cid_hash, user_email_hash, user_phone_hash, user_role, user_status) VALUES
(3, 'test001', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password123
 SHA2('5555555555555', 256), SHA2('test@hospital.com', 256), SHA2('0855555555', 256), 'user', 'active');