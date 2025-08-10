<?php
// login.php - แก้ไขใหม่ทั้งหมด
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';
require_once 'classes/Encryption.php';
require_once 'classes/Session.php';
require_once 'classes/Logger.php';

// Initialize objects
$session = new Session();
$user = new User();
$logger = new Logger();

// Redirect if already logged in
if ($session->isLoggedIn()) {
    try {
        $userId = $session->getUserId();
        $userProfile = $user->getUserProfile($userId);
        
        if ($userProfile) {
            // Log the access attempt
            $logger->logUserActivity($userId, 'LOGIN_PAGE_ACCESS', 'User tried to access login page while logged in');
            
            // Check user status
            if ($userProfile['user_status'] !== 'active') {
                $session->destroySession();
                $error = 'บัญชีของคุณยังไม่ได้รับการอนุมัติ';
            } else {
                // Redirect based on role
                $redirectUrl = ($userProfile['user_role'] === 'admin') ? 'admin/dashboard.php' : 'user/dashboard.php';
                header('Location: ' . $redirectUrl);
                exit;
            }
        } else {
            // Profile not found, destroy session
            $session->destroySession();
            $logger->logUserActivity($userId, 'SESSION_INVALID', 'User profile not found during login redirect');
        }
    } catch (Exception $e) {
        // Error getting profile, destroy session
        $session->destroySession();
        $logger->error('Error getting user profile during login redirect: ' . $e->getMessage());
    }
}

$error = '';
$success = '';
$loginAttempts = 0;
$isLocked = false;
$lockoutTimeRemaining = 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$session->verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'ข้อมูลการรักษาความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        $logger->logUserActivity(null, 'LOGIN_CSRF_FAILED', "Username: $username", $clientIP);
    } elseif (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        // Check rate limiting
        $rateLimitKey = 'login_attempts_' . $clientIP;
        $attempts = $_SESSION[$rateLimitKey] ?? 0;
        $lastAttempt = $_SESSION[$rateLimitKey . '_time'] ?? 0;
        
        // Reset attempts if more than 1 hour has passed
        if ((time() - $lastAttempt) > 3600) {
            $attempts = 0;
        }
        
        // Check if IP is temporarily blocked
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $timeRemaining = 3600 - (time() - $lastAttempt);
            if ($timeRemaining > 0) {
                $error = "คุณพยายาม login ผิดมากเกินไป กรุณาลองใหม่ในอีก " . ceil($timeRemaining / 60) . " นาที";
                $logger->logUserActivity(null, 'LOGIN_RATE_LIMITED', "IP: $clientIP, Username: $username", $clientIP);
            } else {
                $attempts = 0; // Reset if cooldown period has passed
            }
        }
        
        if (empty($error)) {
            try {
                $result = $user->login($username, $password, $turnstileToken);
                
                if ($result['success']) {
                    $userProfile = $result['user'];
                    $sessionTime = $userProfile['user_session_time'] ?? SESSION_TIMEOUT;
                    
                    // Clear rate limiting on successful login
                    unset($_SESSION[$rateLimitKey]);
                    unset($_SESSION[$rateLimitKey . '_time']);
                    
                    // Start session
                    if ($session->startUserSession($userProfile['user_id'], $sessionTime, $rememberMe)) {
                        // Log successful login
                        $logger->logUserActivity($userProfile['user_id'], 'LOGIN_SUCCESS', 
                            "Role: {$userProfile['user_role']}", $clientIP);
                        
                        // Check for redirect parameter
                        $redirectUrl = $_GET['redirect'] ?? '';
                        if ($redirectUrl && filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
                            // Validate redirect URL is within our domain
                            $parsedUrl = parse_url($redirectUrl);
                            $allowedHosts = [parse_url(APP_URL, PHP_URL_HOST)];
                            
                            if (in_array($parsedUrl['host'], $allowedHosts)) {
                                header('Location: ' . $redirectUrl);
                                exit;
                            }
                        }
                        
                        // Default redirect based on role
                        if ($userProfile['user_role'] === 'admin') {
                            header('Location: admin/dashboard.php');
                        } else {
                            header('Location: user/dashboard.php');
                        }
                        exit;
                    } else {
                        $error = 'ไม่สามารถเริ่มเซสชันได้ กรุณาลองใหม่อีกครั้ง';
                        $logger->error('Failed to start session for user: ' . $userProfile['user_id']);
                    }
                } else {
                    $error = $result['message'];
                    
                    // Increment rate limiting counter
                    $_SESSION[$rateLimitKey] = $attempts + 1;
                    $_SESSION[$rateLimitKey . '_time'] = time();
                    
                    // Use data from login method response
                    $loginAttempts = $result['login_attempts'] ?? 0;
                    $isLocked = $result['is_locked'] ?? false;
                    
                    if (isset($result['lockout_time_remaining']) && $result['lockout_time_remaining'] > 0) {
                        $lockoutTimeRemaining = $result['lockout_time_remaining'];
                    }
                }
            } catch (Exception $e) {
                $error = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
                $logger->error('Login system error: ' . $e->getMessage());
            }
        }
    }
}

// Handle URL messages
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'registered':
            $success = 'สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ';
            break;
        case 'logout':
            $success = 'ออกจากระบบสำเร็จ';
            break;
        case 'password_reset':
            $success = 'รีเซ็ตรหัสผ่านสำเร็จ กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่';
            break;
        case 'session_expired':
            $error = 'เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่';
            break;
        case 'session_error':
            $error = 'เกิดข้อผิดพลาดกับเซสชัน กรุณาเข้าสู่ระบบใหม่';
            break;
        case 'account_not_active':
            $error = 'บัญชีของคุณยังไม่ได้รับการอนุมัติ';
            break;
        case 'access_denied':
            $error = 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้';
            break;
    }
}

// Generate CSRF token
$csrfToken = $session->generateCSRFToken();

// Get rate limiting info for display
$rateLimitKey = 'login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$currentAttempts = $_SESSION[$rateLimitKey] ?? 0;
$attemptsRemaining = max(0, MAX_LOGIN_ATTEMPTS - $currentAttempts);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
        /* .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #065205ff 100%);
        } */

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #48bb78 0%, #2f855a 100%); /* Green gradient */
        }

        .login-header {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); /* Lighter green to darker green */
            border-radius: 1rem 1rem 0 0;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 1rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        /* .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 1rem 1rem 0 0;
        } */
        
        /* .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            transform: translateY(-1px);
        } */
        
        .login-header {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); /* Lighter green to darker green */
            border-radius: 1rem 1rem 0 0;
        }
        
        .form-control:focus {
            border-color: #48bb78; /* Green focus border */
            box-shadow: 0 0 0 0.2rem rgba(72, 187, 120, 0.25); /* Green shadow on focus */
        }

        .btn-primary {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); /* Green gradient button */
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #38a169 0%, #2f855a 100%); /* Darker green on hover */
            transform: translateY(-1px);
        }

        .attempts-warning {
            background: linear-gradient(45deg, #ffeaa7, #fab1a0);
            border: none;
            border-radius: 0.5rem;
        }
        
        .lockout-warning {
            background: linear-gradient(45deg, #fd79a8, #fdcb6e);
            border: none;
            border-radius: 0.5rem;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5 col-xl-4">
                    <div class="card login-card shadow-lg">
                        <div class="card-header login-header text-white text-center py-4">
                            <div class="mb-3">
                                <i class="fas fa-shield-alt fa-3x"></i>
                            </div>
                            <h2 class="mb-1">เข้าสู่ระบบ</h2>
                            <p class="mb-0 opacity-75">ระบบอัพโหลดไฟล์ SSJBox</p>
                        </div>
                        
                        <div class="card-body p-4">
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo htmlspecialchars($error); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo htmlspecialchars($success); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($isLocked): ?>
                                <div class="alert lockout-warning text-dark" role="alert">
                                    <i class="fas fa-lock me-2"></i>
                                    <strong>บัญชีถูกล็อก</strong><br>
                                    บัญชีของคุณถูกล็อกชั่วคราว กรุณาลองใหม่ในอีก 
                                    <span id="lockoutTimer"><?php echo ceil($lockoutTimeRemaining / 60); ?></span> นาที
                                </div>
                            <?php elseif ($loginAttempts > 0): ?>
                                <div class="alert attempts-warning text-dark" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>คำเตือน:</strong> คุณพยายาม login ผิด <?php echo $loginAttempts; ?> ครั้ง
                                    (เหลืออีก <?php echo MAX_LOGIN_ATTEMPTS - $loginAttempts; ?> ครั้งก่อนถูกล็อก)
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($attemptsRemaining <= 2 && $attemptsRemaining > 0): ?>
                                <div class="alert alert-warning" role="alert">
                                    <i class="fas fa-clock me-2"></i>
                                    <strong>แจ้งเตือน:</strong> คุณสามารถพยายาม login ได้อีก <?php echo $attemptsRemaining; ?> ครั้ง
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" id="loginForm" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                
                                <div class="mb-3">
                                    <label for="username" class="form-label">ชื่อผู้ใช้</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                               required autofocus autocomplete="username"
                                               <?php echo $isLocked ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="invalid-feedback">กรุณากรอกชื่อผู้ใช้</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">รหัสผ่าน</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               required autocomplete="current-password"
                                               <?php echo $isLocked ? 'disabled' : ''; ?>>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" 
                                                <?php echo $isLocked ? 'disabled' : ''; ?>>
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">กรุณากรอกรหัสผ่าน</div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me"
                                               <?php echo $isLocked ? 'disabled' : ''; ?>>
                                        <label class="form-check-label" for="remember_me">
                                            จำฉันไว้ (7 วัน)
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Turnstile -->
                                <?php if (!$isLocked): ?>
                                <div class="mb-3 d-flex justify-content-center">
                                    <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars(TURNSTILE_SITE_KEY); ?>"></div>
                                </div>
                                <?php endif; ?>
                                
                                <button type="submit" class="btn btn-primary w-100 mb-3 <?php echo $isLocked ? 'disabled' : ''; ?>" 
                                        id="loginBtn" <?php echo $isLocked ? 'disabled' : ''; ?>>
                                    <i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบ
                                </button>
                            </form>
                            
                            <div class="text-center">
                                <div class="mb-2">
                                    <a href="forgot_password.php" class="text-decoration-none">
                                        <i class="fas fa-key me-1"></i>ลืมรหัสผ่าน?
                                    </a>
                                </div>
                                <hr class="my-3">
                                <div>
                                    ยังไม่มีบัญชี? 
                                    <a href="register.php" class="text-decoration-none fw-bold">
                                        <i class="fas fa-user-plus me-1"></i>สมัครสมาชิก
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-footer text-center text-muted py-3">
                            <small>
                                <i class="fas fa-shield-alt me-1"></i>
                                ระบบปลอดภัยด้วย SSL Encryption และ Multi-Factor Authentication
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
    $(document).ready(function() {
        // Toggle password visibility
        $('#togglePassword').click(function() {
            const passwordField = $('#password');
            const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
            passwordField.attr('type', type);
            $(this).find('i').toggleClass('fa-eye fa-eye-slash');
        });
        
        // Form validation
        $('#loginForm').on('submit', function(e) {
            let isValid = true;
            
            // Clear previous validation
            $('.form-control').removeClass('is-invalid');
            
            // Check username
            const username = $('#username').val().trim();
            if (!username) {
                $('#username').addClass('is-invalid');
                isValid = false;
            }
            
            // Check password
            const password = $('#password').val();
            if (!password) {
                $('#password').addClass('is-invalid');
                isValid = false;
            }
            
            // Check Turnstile (if not locked)
            <?php if (!$isLocked): ?>
            const turnstileResponse = document.querySelector('[name="cf-turnstile-response"]');
            if (!turnstileResponse || !turnstileResponse.value) {
                isValid = false;
                showToast('error', 'กรุณายืนยันการป้องกันบอท');
            }
            <?php endif; ?>
            
            if (!isValid) {
                e.preventDefault();
                // Focus on first invalid field
                $('.is-invalid').first().focus();
                return false;
            }
            
            // Show loading state
            $('#loginBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>กำลังเข้าสู่ระบบ...');
        });
        
        // Auto-focus on username field if empty
        if (!$('#username').val()) {
            $('#username').focus();
        } else {
            $('#password').focus();
        }
        
        // Clear error messages after 10 seconds
        setTimeout(function() {
            $('.alert-danger').fadeOut();
        }, 10000);
        
        // Clear success messages after 5 seconds
        setTimeout(function() {
            $('.alert-success').fadeOut();
        }, 5000);
        
        // Lockout timer countdown
        <?php if ($isLocked && $lockoutTimeRemaining > 0): ?>
        let timeRemaining = <?php echo $lockoutTimeRemaining; ?>;
        const timer = setInterval(function() {
            timeRemaining--;
            const minutes = Math.ceil(timeRemaining / 60);
            $('#lockoutTimer').text(minutes);
            
            if (timeRemaining <= 0) {
                clearInterval(timer);
                location.reload(); // Reload page when lockout expires
            }
        }, 1000);
        <?php endif; ?>
        
        // Show toast notification
        function showToast(type, message) {
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
        
        // Keyboard shortcuts
        $(document).keydown(function(e) {
            // Alt + L to focus on login form
            if (e.altKey && e.keyCode === 76) {
                e.preventDefault();
                $('#username').focus();
            }
        });
        
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
    </script>
</body>
</html>