<?php
// register.php - แก้ไขใหม่ทั้งหมด
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/Register.php';
require_once 'classes/Encryption.php';
require_once 'classes/Logger.php';
require_once 'classes/Session.php';
require_once 'classes/User.php';

// Initialize objects
$session = new Session();
$user = new User();
$register = new Register();
$logger = new Logger();

// Redirect if already logged in
if ($session->isLoggedIn()) {
    $userId = $session->getUserId();
    
    try {
        $userProfile = $user->getUserProfile($userId);
        
        if ($userProfile) {
            // Log the access attempt
            $logger->logUserActivity($userId, 'REGISTER_PAGE_ACCESS', 'User tried to access register page while logged in');
            
            // Check user status
            if ($userProfile['user_status'] !== 'active') {
                $session->destroySession();
                header('Location: login.php?msg=account_not_active');
                exit;
            }
            
            // Redirect based on role
            $redirectUrl = ($userProfile['user_role'] === 'admin') ? 'admin/dashboard.php' : 'user/dashboard.php';
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            // Profile not found, destroy session
            $session->destroySession();
            $logger->logUserActivity($userId, 'SESSION_INVALID', 'User profile not found, session destroyed');
            header('Location: login.php?msg=session_expired');
            exit;
        }
    } catch (Exception $e) {
        // Error getting profile, destroy session and log error
        $session->destroySession();
        $logger->error('Error getting user profile during registration redirect: ' . $e->getMessage());
        header('Location: login.php?msg=session_error');
        exit;
    }
}

$errors = [];
$success = '';

// Handle URL error parameters
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'session_invalid':
            $errors[] = 'เซสชันไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่';
            break;
        case 'registration_failed':
            $errors[] = 'การสมัครสมาชิกล้มเหลว กรุณาลองใหม่อีกครั้ง';
            break;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$session->verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'ข้อมูลการรักษาความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        $logger->logUserActivity(null, 'CSRF_VALIDATION_FAILED', 'Invalid CSRF token in registration', $clientIP);
    } else {
        try {
            $result = $register->registerUser($_POST);
            
            if ($result['success']) {
                $success = $result['message'];
                $_POST = []; // Clear form data on success
                
                // Log successful registration
                $logger->logUserActivity($result['user_id'] ?? null, 'USER_REGISTERED', 'New user registration successful', $clientIP);
            } else {
                $errors = $result['errors'];
                
                // Log failed registration
                $logger->logUserActivity(null, 'REGISTRATION_FAILED', 'Registration failed: ' . implode(', ', $errors), $clientIP);
            }
        } catch (Exception $e) {
            $errors[] = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
            $logger->error('Registration system error: ' . $e->getMessage());
        }
    }
}

// Generate new CSRF token
$csrfToken = $session->generateCSRFToken();

// Get provinces with error handling
try {
    $provinces = $register->getProvinces();
} catch (Exception $e) {
    $provinces = [];
    $errors[] = 'ไม่สามารถโหลดข้อมูลจังหวัดได้ กรุณาลองใหม่อีกครั้ง';
    $logger->error('Failed to load provinces: ' . $e->getMessage());
}

// Form persistence data
$formData = [
    'province' => $_POST['province'] ?? '',
    'amphur' => $_POST['amphur'] ?? '',
    'hospital' => $_POST['hospital'] ?? '',
    'prefix' => $_POST['prefix'] ?? '',
    'firstname' => $_POST['firstname'] ?? '',
    'lastname' => $_POST['lastname'] ?? '',
    'position' => $_POST['position'] ?? '',
    'cid' => $_POST['cid'] ?? '',
    'email' => $_POST['email'] ?? '',
    'phone' => $_POST['phone'] ?? '',
    'username' => $_POST['username'] ?? ''
];

// Pre-load dependent data
$amphurs = [];
$hospitals = [];

if ($formData['province']) {
    try {
        $amphurs = $register->getAmphurs($formData['province']);
    } catch (Exception $e) {
        $logger->error('Failed to load amphurs: ' . $e->getMessage());
    }
}

if ($formData['amphur']) {
    try {
        $hospitals = $register->getHospitals($formData['amphur']);
    } catch (Exception $e) {
        $logger->error('Failed to load hospitals: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        .strength-weak { background: linear-gradient(90deg, #dc3545 0%, #dc3545 33%, #e9ecef 33%, #e9ecef 100%); }
        .strength-medium { background: linear-gradient(90deg, #ffc107 0%, #ffc107 66%, #e9ecef 66%, #e9ecef 100%); }
        .strength-strong { background: linear-gradient(90deg, #198754 0%, #198754 100%); }
        
        .form-control.is-valid {
            border-color: #198754;
            padding-right: calc(1.5em + 0.75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='m2.3 6.73.5-.5 2.93-2.93a.75.75 0 0 1 1.06 1.06l-3.59 3.59a.75.75 0 0 1-1.06 0l-1.06-1.06a.75.75 0 0 1 1.06-1.06l.66.66z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .form-control.is-invalid {
            border-color: #dc3545;
            padding-right: calc(1.5em + 0.75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6h.4l-.4 5.4h-.4z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 9999;
        }
        
        .loading-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            text-align: center;
        }
        
        .requirement-check {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .requirement-check .text-success {
            color: #198754 !important;
        }
        
        .requirement-check .text-danger {
            color: #dc3545 !important;
        }
        
        .requirement-check .text-muted {
            color: #6c757d !important;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h5>กำลังสมัครสมาชิก...</h5>
            <p class="text-muted mb-0">กรุณารอสักครู่</p>
        </div>
    </div>

    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-primary text-white text-center py-4">
                        <h2 class="mb-1"><i class="fas fa-user-plus me-2"></i>สมัครสมาชิก</h2>
                        <p class="mb-0 opacity-75">ระบบอัพโหลดไฟล์ SSJBox</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>เกิดข้อผิดพลาด:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>สำเร็จ!</strong> <?php echo htmlspecialchars($success); ?>
                                <hr>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="login.php" class="btn btn-success">
                                        <i class="fas fa-sign-in-alt me-1"></i>เข้าสู่ระบบ
                                    </a>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php else: ?>
                        
                        <form method="POST" id="registerForm" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            
                            <!-- Location Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-map-marker-alt me-2"></i>ข้อมูลหน่วยงาน
                                    </h5>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4 mb-3">
                                    <label for="province" class="form-label">จังหวัด <span class="text-danger">*</span></label>
                                    <select class="form-select" id="province" name="province" required>
                                        <option value="">เลือกจังหวัด</option>
                                        <?php foreach ($provinces as $province): ?>
                                            <option value="<?php echo htmlspecialchars($province['province_code']); ?>" 
                                                    <?php echo ($formData['province'] == $province['province_code']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($province['province_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">กรุณาเลือกจังหวัด</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="amphur" class="form-label">อำเภอ <span class="text-danger">*</span></label>
                                    <select class="form-select" id="amphur" name="amphur" required <?php echo empty($formData['province']) ? 'disabled' : ''; ?>>
                                        <option value="">เลือกอำเภอ</option>
                                        <?php foreach ($amphurs as $amphur): ?>
                                            <option value="<?php echo htmlspecialchars($amphur['amphur_code']); ?>"
                                                    <?php echo ($formData['amphur'] == $amphur['amphur_code']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($amphur['amphur_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">กรุณาเลือกอำเภอ</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="hospital" class="form-label">หน่วยงาน <span class="text-danger">*</span></label>
                                    <select class="form-select" id="hospital" name="hospital" required <?php echo empty($formData['amphur']) ? 'disabled' : ''; ?>>
                                        <option value="">เลือกหน่วยงาน</option>
                                        <?php foreach ($hospitals as $hospital): ?>
                                            <option value="<?php echo htmlspecialchars($hospital['hosp_code']); ?>"
                                                    <?php echo ($formData['hospital'] == $hospital['hosp_code']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($hospital['hosp_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">กรุณาเลือกหน่วยงาน</div>
                                </div>
                            </div>
                            
                            <!-- Personal Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-user me-2"></i>ข้อมูลส่วนตัว
                                    </h5>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-3 mb-3">
                                    <label for="prefix" class="form-label">คำนำหน้า <span class="text-danger">*</span></label>
                                    <select class="form-select" id="prefix" name="prefix" required>
                                        <option value="">เลือกคำนำหน้า</option>
                                        <option value="นาย" <?php echo ($formData['prefix'] == 'นาย') ? 'selected' : ''; ?>>นาย</option>
                                        <option value="นาง" <?php echo ($formData['prefix'] == 'นาง') ? 'selected' : ''; ?>>นาง</option>
                                        <option value="นางสาว" <?php echo ($formData['prefix'] == 'นางสาว') ? 'selected' : ''; ?>>นางสาว</option>
                                    </select>
                                    <div class="invalid-feedback">กรุณาเลือกคำนำหน้า</div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="firstname" class="form-label">ชื่อ <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="firstname" name="firstname" 
                                           value="<?php echo htmlspecialchars($formData['firstname']); ?>" 
                                           required maxlength="100" pattern="[ก-๙a-zA-Z\s]+">
                                    <div class="invalid-feedback">กรุณากรอกชื่อ (ภาษาไทยหรือภาษาอังกฤษเท่านั้น)</div>
                                </div>
                                
                                <div class="col-md-5 mb-3">
                                    <label for="lastname" class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="lastname" name="lastname" 
                                           value="<?php echo htmlspecialchars($formData['lastname']); ?>" 
                                           required maxlength="100" pattern="[ก-๙a-zA-Z\s]+">
                                    <div class="invalid-feedback">กรุณากรอกนามสกุล (ภาษาไทยหรือภาษาอังกฤษเท่านั้น)</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label for="position" class="form-label">ตำแหน่ง <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="position" name="position" 
                                           value="<?php echo htmlspecialchars($formData['position']); ?>" 
                                           required maxlength="100">
                                    <div class="invalid-feedback">กรุณากรอกตำแหน่ง</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="cid" class="form-label">เลขประจำตัวประชาชน <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="cid" name="cid" maxlength="13" 
                                           pattern="[0-9]{13}" value="<?php echo htmlspecialchars($formData['cid']); ?>" 
                                           required inputmode="numeric" placeholder="1234567890123">
                                    <div class="form-text">กรอกเลข 13 หลัก</div>
                                    <div class="invalid-feedback">กรุณากรอกเลขประจำตัวประชาชน 13 หลัก</div>
                                    <div id="cidValidation" class="mt-1"></div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">อีเมล <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($formData['email']); ?>" 
                                           required maxlength="255" placeholder="example@domain.com">
                                    <div class="invalid-feedback">กรุณากรอกอีเมลที่ถูกต้อง</div>
                                    <div id="emailValidation" class="mt-1"></div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="phone" name="phone" maxlength="10" 
                                           pattern="[0-9]{10}" value="<?php echo htmlspecialchars($formData['phone']); ?>" 
                                           required inputmode="numeric" placeholder="0812345678">
                                    <div class="form-text">กรอกเบอร์ 10 หลัก (เช่น 0812345678)</div>
                                    <div class="invalid-feedback">กรุณากรอกเบอร์โทรศัพท์ 10 หลัก</div>
                                </div>
                            </div>
                            
                            <!-- Account Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-key me-2"></i>ข้อมูลบัญชีผู้ใช้
                                    </h5>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">ชื่อผู้ใช้ <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($formData['username']); ?>" 
                                           required minlength="4" maxlength="20" pattern="[a-zA-Z0-9_]+" 
                                           autocomplete="username" placeholder="username123">
                                    <div class="form-text">4-20 ตัวอักษร (a-z, A-Z, 0-9, _)</div>
                                    <div class="invalid-feedback">ชื่อผู้ใช้ต้องมี 4-20 ตัวอักษร และประกอบด้วย a-z, A-Z, 0-9, _ เท่านั้น</div>
                                    <div id="usernameCheck" class="mt-1"></div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" 
                                               required minlength="12" autocomplete="new-password" 
                                               placeholder="รหัสผ่านอย่างน้อย 12 ตัวอักษร">
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" tabindex="-1">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-primary" type="button" id="generatePassword" tabindex="-1">
                                            <i class="fas fa-random"></i> สุ่ม
                                        </button>
                                    </div>
                                    <div class="password-strength mt-2" id="passwordStrengthBar"></div>
                                    <div id="passwordStrength" class="mt-2"></div>
                                    <div class="requirement-check mt-2">
                                        <div class="row">
                                            <div class="col-6">
                                                <div>- ตัวอักษรใหญ่ (A-Z) <span id="check-upper" class="text-muted">✗</span></div>
                                                <div>- ตัวอักษรเล็ก (a-z) <span id="check-lower" class="text-muted">✗</span></div>
                                            </div>
                                            <div class="col-6">
                                                <div>- ตัวเลข (0-9) <span id="check-number" class="text-muted">✗</span></div>
                                                <div>- สัญลักษณ์พิเศษ <span id="check-symbol" class="text-muted">✗</span></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="invalid-feedback">รหัสผ่านไม่ตรงตามเงื่อนไข</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           required autocomplete="new-password" placeholder="พิมพ์รหัสผ่านอีกครั้ง">
                                    <div id="passwordMatch" class="mt-2"></div>
                                    <div class="invalid-feedback">รหัสผ่านไม่ตรงกัน</div>
                                </div>
                            </div>
                            
                            <!-- Turnstile -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="d-flex justify-content-center">
                                        <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars(TURNSTILE_SITE_KEY); ?>"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-lg w-100" id="submitBtn" disabled>
                                        <i class="fas fa-user-plus me-2"></i>สมัครสมาชิก
                                    </button>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <p class="mb-0">มีบัญชีอยู่แล้ว? <a href="login.php" class="text-decoration-none fw-bold">เข้าสู่ระบบ</a></p>
                            </div>
                        </form>
                        
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-footer text-center text-muted py-3">
                        <small>
                            <i class="fas fa-shield-alt me-1"></i>
                            ข้อมูลของคุณจะถูกเข้ารหัสและปกป้องอย่างปลอดภัย
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
    $(document).ready(function() {
        let formValid = false;
        let usernameValid = false;
        let cidValid = false;
        let emailValid = false;
        let passwordValid = false;
        let passwordMatch = false;
        
        // Check form validity
        function checkFormValidity() {
            const requiredFields = $('#registerForm [required]');
            let allFieldsValid = true;
            
            requiredFields.each(function() {
                if (!$(this).val() || $(this).hasClass('is-invalid')) {
                    allFieldsValid = false;
                    return false;
                }
            });
            
            // Check Turnstile
            const turnstileResponse = document.querySelector('[name="cf-turnstile-response"]');
            const turnstileValid = turnstileResponse && turnstileResponse.value;
            
            formValid = allFieldsValid && usernameValid && cidValid && emailValid && passwordValid && passwordMatch && turnstileValid;
            $('#submitBtn').prop('disabled', !formValid);
            
            if (formValid) {
                $('#submitBtn').removeClass('btn-secondary').addClass('btn-primary');
            } else {
                $('#submitBtn').removeClass('btn-primary').addClass('btn-secondary');
            }
        }
        
        // Province change event
        $('#province').change(function() {
            const provinceCode = $(this).val();
            $('#amphur').prop('disabled', true).html('<option value="">เลือกอำเภอ</option>').removeClass('is-valid is-invalid');
            $('#hospital').prop('disabled', true).html('<option value="">เลือกหน่วยงาน</option>').removeClass('is-valid is-invalid');
            
            if (provinceCode) {
                $(this).addClass('is-valid').removeClass('is-invalid');
                
                $.get('ajax/get_amphur.php', {province_code: provinceCode}, function(data) {
                    if (data.success) {
                        $('#amphur').prop('disabled', false);
                        data.amphurs.forEach(function(amphur) {
                            $('#amphur').append(
                                `<option value="${amphur.amphur_code}">${amphur.amphur_name}</option>`
                            );
                        });
                    } else {
                        showToast('error', 'ไม่สามารถโหลดข้อมูลอำเภอได้');
                    }
                }, 'json').fail(function() {
                    showToast('error', 'ไม่สามารถโหลดข้อมูลอำเภอได้ กรุณาลองใหม่');
                });
            } else {
                $(this).removeClass('is-valid is-invalid');
            }
            checkFormValidity();
        });
        
        // Amphur change event
        $('#amphur').change(function() {
            const amphurCode = $(this).val();
            $('#hospital').prop('disabled', true).html('<option value="">เลือกหน่วยงาน</option>').removeClass('is-valid is-invalid');
            
            if (amphurCode) {
                $(this).addClass('is-valid').removeClass('is-invalid');
                
                $.get('ajax/get_hospital.php', {amphur_code: amphurCode}, function(data) {
                    if (data.success) {
                        $('#hospital').prop('disabled', false);
                        data.hospitals.forEach(function(hospital) {
                            $('#hospital').append(
                                `<option value="${hospital.hosp_code}">${hospital.hosp_name}</option>`
                            );
                        });
                    } else {
                        showToast('error', 'ไม่สามารถโหลดข้อมูลหน่วยงานได้');
                    }
                }, 'json').fail(function() {
                    showToast('error', 'ไม่สามารถโหลดข้อมูลหน่วยงานได้ กรุณาลองใหม่');
                });
            } else {
                $(this).removeClass('is-valid is-invalid');
            }
            checkFormValidity();
        });
        
        // Hospital change event
        $('#hospital').change(function() {
            if ($(this).val()) {
                $(this).addClass('is-valid').removeClass('is-invalid');
            } else {
                $(this).removeClass('is-valid is-invalid');
            }
            checkFormValidity();
        });
        
        // Username validation
        let usernameTimeout;
        $('#username').on('input', function() {
            const username = $(this).val();
            clearTimeout(usernameTimeout);
            
            // Reset validation state
            usernameValid = false;
            $('#usernameCheck').html('');
            $(this).removeClass('is-valid is-invalid');
            
            if (username.length >= 4 && /^[a-zA-Z0-9_]+$/.test(username)) {
                usernameTimeout = setTimeout(function() {
                    $.get('ajax/check_duplicate.php', {username: username}, function(data) {
                        if (data.exists) {
                            $('#usernameCheck').html('<small class="text-danger"><i class="fas fa-times"></i> ชื่อผู้ใช้นี้ถูกใช้แล้ว</small>');
                            $('#username').addClass('is-invalid').removeClass('is-valid');
                            usernameValid = false;
                        } else {
                            $('#usernameCheck').html('<small class="text-success"><i class="fas fa-check"></i> ชื่อผู้ใช้ว่าง</small>');
                            $('#username').addClass('is-valid').removeClass('is-invalid');
                            usernameValid = true;
                        }
                        checkFormValidity();
                    }, 'json').fail(function() {
                        $('#usernameCheck').html('<small class="text-warning"><i class="fas fa-exclamation-triangle"></i> ไม่สามารถตรวจสอบชื่อผู้ใช้ได้</small>');
                        usernameValid = false;
                        checkFormValidity();
                    });
                }, 500);
            } else if (username.length > 0) {
                $('#usernameCheck').html('<small class="text-muted">ชื่อผู้ใช้ต้องมี 4-20 ตัวอักษร (a-z, A-Z, 0-9, _)</small>');
            }
            checkFormValidity();
        });
        
        // CID validation
        $('#cid').on('input', function() {
            let cid = $(this).val().replace(/\D/g, ''); // Remove non-digits
            if (cid.length > 13) {
                cid = cid.substring(0, 13);
            }
            $(this).val(cid);
            
            cidValid = false;
            $('#cidValidation').html('');
            $(this).removeClass('is-valid is-invalid');
            
            if (cid.length === 13) {
                if (validateThaiCID(cid)) {
                    $('#cidValidation').html('<small class="text-success"><i class="fas fa-check"></i> เลขประจำตัวประชาชนถูกต้อง</small>');
                    $(this).addClass('is-valid').removeClass('is-invalid');
                    cidValid = true;
                } else {
                    $('#cidValidation').html('<small class="text-danger"><i class="fas fa-times"></i> เลขประจำตัวประชาชนไม่ถูกต้อง</small>');
                    $(this).addClass('is-invalid').removeClass('is-valid');
                }
            } else if (cid.length > 0) {
                $('#cidValidation').html('<small class="text-muted">กรอกเลข 13 หลัก</small>');
            }
            checkFormValidity();
        });
        
        // Thai CID validation function
        function validateThaiCID(cid) {
            if (cid.length !== 13) return false;
            
            let sum = 0;
            for (let i = 0; i < 12; i++) {
                sum += parseInt(cid.charAt(i)) * (13 - i);
            }
            
            let checkDigit = (11 - (sum % 11)) % 10;
            return checkDigit === parseInt(cid.charAt(12));
        }
        
        // Email validation
        $('#email').on('input', function() {
            const email = $(this).val();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            emailValid = false;
            $('#emailValidation').html('');
            $(this).removeClass('is-valid is-invalid');
            
            if (email && emailRegex.test(email)) {
                // Check for duplicate email
                $.get('ajax/check_duplicate.php', {email: email}, function(data) {
                    if (data.exists) {
                        $('#emailValidation').html('<small class="text-danger"><i class="fas fa-times"></i> อีเมลนี้ถูกใช้แล้ว</small>');
                        $('#email').addClass('is-invalid').removeClass('is-valid');
                        emailValid = false;
                    } else {
                        $('#emailValidation').html('<small class="text-success"><i class="fas fa-check"></i> อีเมลพร้อมใช้งาน</small>');
                        $('#email').addClass('is-valid').removeClass('is-invalid');
                        emailValid = true;
                    }
                    checkFormValidity();
                }, 'json').fail(function() {
                    // If check fails, assume email is valid if format is correct
                    $('#email').addClass('is-valid');
                    emailValid = true;
                    checkFormValidity();
                });
            } else if (email.length > 0) {
                $('#emailValidation').html('<small class="text-muted">รูปแบบอีเมลไม่ถูกต้อง</small>');
                $(this).addClass('is-invalid');
            }
        });
        
        // Phone number formatting
        $('#phone').on('input', function() {
            let phone = $(this).val().replace(/\D/g, '');
            if (phone.length > 10) {
                phone = phone.substring(0, 10);
            }
            $(this).val(phone);
            
            if (phone.length === 10 && phone.startsWith('0')) {
                $(this).addClass('is-valid').removeClass('is-invalid');
            } else if (phone.length > 0) {
                $(this).addClass('is-invalid').removeClass('is-valid');
            } else {
                $(this).removeClass('is-valid is-invalid');
            }
            checkFormValidity();
        });
        
        // Password strength check
        $('#password').on('input', function() {
            const password = $(this).val();
            const strength = checkPasswordStrength(password);
            
            // Update visual indicators
            updatePasswordChecks(password);
            
            let strengthText = '';
            let strengthClass = '';
            let barClass = '';
            
            if (password.length === 0) {
                strengthText = '';
                barClass = '';
                passwordValid = false;
            } else if (strength.score < 2) {
                strengthText = '<small class="text-danger"><i class="fas fa-times"></i> รหัสผ่านอ่อน</small>';
                strengthClass = 'is-invalid';
                barClass = 'strength-weak';
                passwordValid = false;
            } else if (strength.score < 4) {
                strengthText = '<small class="text-warning"><i class="fas fa-exclamation-triangle"></i> รหัสผ่านปานกลาง</small>';
                strengthClass = 'is-invalid';
                barClass = 'strength-medium';
                passwordValid = false;
            } else {
                strengthText = '<small class="text-success"><i class="fas fa-check"></i> รหัสผ่านแข็งแรง</small>';
                strengthClass = 'is-valid';
                barClass = 'strength-strong';
                passwordValid = true;
            }
            
            $('#passwordStrength').html(strengthText);
            $('#passwordStrengthBar').removeClass('strength-weak strength-medium strength-strong').addClass(barClass);
            $(this).removeClass('is-valid is-invalid').addClass(strengthClass);
            
            // Re-check password match
            $('#confirm_password').trigger('input');
            checkFormValidity();
        });
        
        // Password match check
        $('#confirm_password').on('input', function() {
            const password = $('#password').val();
            const confirmPassword = $(this).val();
            
            passwordMatch = false;
            $('#passwordMatch').html('');
            $(this).removeClass('is-valid is-invalid');
            
            if (confirmPassword.length === 0) {
                // Empty, no validation
            } else if (password === confirmPassword && passwordValid) {
                $('#passwordMatch').html('<small class="text-success"><i class="fas fa-check"></i> รหัสผ่านตรงกัน</small>');
                $(this).addClass('is-valid');
                passwordMatch = true;
            } else {
                $('#passwordMatch').html('<small class="text-danger"><i class="fas fa-times"></i> รหัสผ่านไม่ตรงกัน</small>');
                $(this).addClass('is-invalid');
            }
            checkFormValidity();
        });
        
        // Toggle password visibility
        $('#togglePassword').click(function() {
            const passwordField = $('#password');
            const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
            passwordField.attr('type', type);
            $(this).find('i').toggleClass('fa-eye fa-eye-slash');
        });
        
        // Generate password
        $('#generatePassword').click(function() {
            const password = generateSecurePassword(16);
            $('#password').val(password).trigger('input');
            $('#confirm_password').val(password).trigger('input');
            
            // Show password temporarily
            $('#password').attr('type', 'text');
            $('#togglePassword').find('i').removeClass('fa-eye').addClass('fa-eye-slash');
            
            // Copy to clipboard and alert user
            if (navigator.clipboard) {
                navigator.clipboard.writeText(password).then(function() {
                    showToast('success', 'รหัสผ่านถูกสร้างและคัดลอกไปยังคลิปบอร์ดแล้ว');
                }).catch(function() {
                    showToast('info', 'รหัสผ่านถูกสร้างแล้ว กรุณาจดจำหรือบันทึกไว้');
                });
            } else {
                showToast('info', 'รหัสผ่านถูกสร้างแล้ว: ' + password);
            }
        });
        
        // Form field validation on change
        $('#registerForm input, #registerForm select').on('change', function() {
            if ($(this).is(':required')) {
                if ($(this).val() && !$(this).hasClass('is-invalid')) {
                    if (!$(this).hasClass('is-valid')) {
                        $(this).addClass('is-valid');
                    }
                } else if (!$(this).val()) {
                    $(this).removeClass('is-valid is-invalid');
                }
            }
            checkFormValidity();
        });
        
        // Form validation on submit
        $('#registerForm').on('submit', function(e) {
            e.preventDefault();
            
            let isValid = true;
            const form = this;
            
            // Check all required fields
            $(this).find('[required]').each(function() {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            
            // Specific validations
            if (!usernameValid) {
                isValid = false;
                $('#username').addClass('is-invalid');
                showToast('error', 'ชื่อผู้ใช้ไม่ถูกต้องหรือถูกใช้แล้ว');
            }
            
            if (!cidValid) {
                isValid = false;
                $('#cid').addClass('is-invalid');
                showToast('error', 'เลขประจำตัวประชาชนไม่ถูกต้อง');
            }
            
            if (!emailValid) {
                isValid = false;
                $('#email').addClass('is-invalid');
                showToast('error', 'อีเมลไม่ถูกต้องหรือถูกใช้แล้ว');
            }
            
            if (!passwordValid) {
                isValid = false;
                $('#password').addClass('is-invalid');
                showToast('error', 'รหัสผ่านไม่ตรงตามเงื่อนไข');
            }
            
            if (!passwordMatch) {
                isValid = false;
                $('#confirm_password').addClass('is-invalid');
                showToast('error', 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน');
            }
            
            // Check Turnstile
            const turnstileResponse = document.querySelector('[name="cf-turnstile-response"]');
            if (!turnstileResponse || !turnstileResponse.value) {
                isValid = false;
                showToast('error', 'กรุณายืนยันการป้องกันบอท');
            }
            
            if (!isValid) {
                // Scroll to first invalid field
                const firstInvalid = $(this).find('.is-invalid').first();
                if (firstInvalid.length) {
                    firstInvalid[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
                return false;
            }
            
            // Show loading overlay
            $('#loadingOverlay').show();
            $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>กำลังสมัครสมาชิก...');
            
            // Submit form
            form.submit();
        });
        
        // Utility functions
        function checkPasswordStrength(password) {
            let score = 0;
            const checks = {
                length: password.length >= 12,
                lowercase: /[a-z]/.test(password),
                uppercase: /[A-Z]/.test(password),
                numbers: /[0-9]/.test(password),
                symbols: /[^A-Za-z0-9]/.test(password)
            };
            
            Object.values(checks).forEach(check => {
                if (check) score++;
            });
            
            return { score, checks };
        }
        
        function updatePasswordChecks(password) {
            const checks = {
                upper: /[A-Z]/.test(password),
                lower: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                symbol: /[^A-Za-z0-9]/.test(password)
            };
            
            $('#check-upper').html(checks.upper ? '<span class="text-success">✓</span>' : '<span class="text-muted">✗</span>');
            $('#check-lower').html(checks.lower ? '<span class="text-success">✓</span>' : '<span class="text-muted">✗</span>');
            $('#check-number').html(checks.number ? '<span class="text-success">✓</span>' : '<span class="text-muted">✗</span>');
            $('#check-symbol').html(checks.symbol ? '<span class="text-success">✓</span>' : '<span class="text-muted">✗</span>');
        }
        
        function generateSecurePassword(length = 16) {
            const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            const lowercase = 'abcdefghijklmnopqrstuvwxyz';
            const numbers = '0123456789';
            const symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
            
            let password = '';
            
            // Ensure at least one character from each category
            password += uppercase[Math.floor(Math.random() * uppercase.length)];
            password += lowercase[Math.floor(Math.random() * lowercase.length)];
            password += numbers[Math.floor(Math.random() * numbers.length)];
            password += symbols[Math.floor(Math.random() * symbols.length)];
            
            // Fill the rest randomly
            const allChars = uppercase + lowercase + numbers + symbols;
            for (let i = 4; i < length; i++) {
                password += allChars[Math.floor(Math.random() * allChars.length)];
            }
            
            // Shuffle the password
            return password.split('').sort(() => Math.random() - 0.5).join('');
        }
        
        function showToast(type, message) {
            // Create toast element
            const toastId = 'toast_' + Date.now();
            const iconClass = {
                'success': 'fa-check-circle',
                'error': 'fa-exclamation-triangle',
                'warning': 'fa-exclamation-triangle',
                'info': 'fa-info-circle'
            }[type] || 'fa-info-circle';
            
            const bgClass = {
                'success': 'bg-success',
                'error': 'bg-danger',
                'warning': 'bg-warning',
                'info': 'bg-info'
            }[type] || 'bg-info';
            
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas ${iconClass} me-2"></i>${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            // Add to page if container doesn't exist
            if (!$('#toastContainer').length) {
                $('body').append('<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 11;"></div>');
            }
            
            $('#toastContainer').append(toastHtml);
            
            // Show toast
            const toastElement = new bootstrap.Toast(document.getElementById(toastId));
            toastElement.show();
            
            // Remove from DOM after hidden
            document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
                $(this).remove();
            });
        }
        
        // Initialize form state
        checkFormValidity();
        
        // Auto-hide alerts after 10 seconds
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 10000);
        
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Pre-validate form if there are values (for reload/back scenarios)
        $('#registerForm input, #registerForm select').each(function() {
            if ($(this).val()) {
                $(this).trigger('input').trigger('change');
            }
        });
    });
    </script>
</body>
</html>