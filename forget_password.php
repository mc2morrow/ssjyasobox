<?php
// forgot_password.php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';
require_once 'classes/Session.php';

$session = new Session();
$user = new User();

// Redirect if already logged in
if ($session->isLoggedIn()) {
    header('Location: user/dashboard.php');
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
    
    if (empty($username) || empty($reason)) {
        $message = 'กรุณากรอกชื่อผู้ใช้และเหตุผลในการรีเซ็ตรหัสผ่าน';
        $messageType = 'danger';
    } else {
        $result = $user->requestPasswordReset($username, $reason);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-lg border-0 my-5">
                    <div class="card-header bg-warning text-dark text-center py-4">
                        <h2><i class="fas fa-key me-2"></i>ลืมรหัสผ่าน</h2>
                        <p class="mb-0">ส่งคำขอรีเซ็ตรหัสผ่าน</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?>">
                                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                                <?php if ($messageType === 'success'): ?>
                                    <hr>
                                    <a href="login.php" class="btn btn-success btn-sm">
                                        <i class="fas fa-sign-in-alt me-1"></i>กลับไปเข้าสู่ระบบ
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>คำแนะนำ:</strong> กรอกชื่อผู้ใช้และเหตุผลในการรีเซ็ตรหัสผ่าน 
                            ผู้ดูแลระบบจะตรวจสอบและอนุมัติคำขอของคุณ
                        </div>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">ชื่อผู้ใช้ <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="reason" class="form-label">เหตุผลในการรีเซ็ตรหัสผ่าน <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="reason" name="reason" rows="4" 
                                          placeholder="กรุณาระบุเหตุผล เช่น ลืมรหัสผ่าน, ต้องการเปลี่ยนรหัสผ่าน เป็นต้น" required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Turnstile -->
                            <div class="mb-3">
                                <div class="cf-turnstile" data-sitekey="<?php echo TURNSTILE_SITE_KEY; ?>"></div>
                            </div>
                            
                            <button type="submit" class="btn btn-warning w-100 mb-3">
                                <i class="fas fa-paper-plane me-2"></i>ส่งคำขอ
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <a href="login.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>กลับไปเข้าสู่ระบบ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>