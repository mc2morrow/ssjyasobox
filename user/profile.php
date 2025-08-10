<?php
// user/profile.php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Encryption.php';
require_once '../classes/Logger.php';
require_once '../classes/Session.php';

$session = new Session();
$user = new User();

if (!$session->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$userId = $session->getUserId();
$userProfile = $user->getUserProfile($userId);

$message = '';
$messageType = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $result = $user->updateProfile($userId, $_POST);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'danger';
        
        if ($result['success']) {
            $userProfile = $user->getUserProfile($userId); // Refresh data
        }
    } elseif (isset($_POST['change_password'])) {
        $result = $user->changePassword($userId, $_POST['current_password'], $_POST['new_password']);
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
    <title>แก้ไขโปรไฟล์ - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Include navigation here -->
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Profile Information -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-user-edit me-2"></i>แก้ไขข้อมูลส่วนตัว
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="prefix" class="form-label">คำนำหน้า</label>
                                    <select class="form-select" id="prefix" name="prefix">
                                        <option value="นาย" <?php echo $userProfile['reg_prefix'] === 'นาย' ? 'selected' : ''; ?>>นาย</option>
                                        <option value="นาง" <?php echo $userProfile['reg_prefix'] === 'นาง' ? 'selected' : ''; ?>>นาง</option>
                                        <option value="นางสาว" <?php echo $userProfile['reg_prefix'] === 'นางสาว' ? 'selected' : ''; ?>>นางสาว</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="firstname" class="form-label">ชื่อ</label>
                                    <input type="text" class="form-control" id="firstname" name="firstname" 
                                           value="<?php echo htmlspecialchars($userProfile['reg_firstname']); ?>" required>
                                </div>
                                <div class="col-md-5">
                                    <label for="lastname" class="form-label">นามสกุล</label>
                                    <input type="text" class="form-control" id="lastname" name="lastname" 
                                           value="<?php echo htmlspecialchars($userProfile['reg_lastname']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="position" class="form-label">ตำแหน่ง</label>
                                    <input type="text" class="form-control" id="position" name="position" 
                                           value="<?php echo htmlspecialchars($userProfile['reg_position']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($userProfile['reg_phone']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="session_time" class="form-label">เวลาอยู่ในระบบ (ชั่วโมง)</label>
                                <select class="form-select" id="session_time" name="session_time">
                                    <?php
                                    $currentSessionTime = $userProfile['user_session_time'] / 3600;
                                    for ($i = 1; $i <= 8; $i++) {
                                        $selected = ($currentSessionTime == $i) ? 'selected' : '';
                                        echo "<option value='" . ($i * 3600) . "' $selected>$i ชั่วโมง</option>";
                                    }
                                    ?>
                                </select>
                                <div class="form-text">กำหนดระยะเวลาที่ต้องการอยู่ในระบบโดยไม่ต้อง login ใหม่</div>
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>บันทึกการเปลี่ยนแปลง
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Change Password -->
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-key me-2"></i>เปลี่ยนรหัสผ่าน
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="passwordForm">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">รหัสผ่านปัจจุบัน</label>
                                <input type="text" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">รหัสผ่านใหม่</label>
                                <input type="text" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text">
                                    รหัสผ่านต้องมีความยาวอย่างน้อย 12 ตัวอักษร ประกอบด้วยตัวอักษรใหญ่ เล็ก ตัวเลข และสัญลักษณ์
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_new_password" class="form-label">ยืนยันรหัสผ่านใหม่</label>
                                <input type="text" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>เปลี่ยนรหัสผ่าน
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Password validation
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_new_password').value;
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน');
            return;
        }
        
        // Check password strength
        if (newPassword.length < 12) {
            e.preventDefault();
            alert('รหัสผ่านต้องมีความยาวอย่างน้อย 12 ตัวอักษร');
            return;
        }
        
        const hasUpper = /[A-Z]/.test(newPassword);
        const hasLower = /[a-z]/.test(newPassword);
        const hasNumber = /[0-9]/.test(newPassword);
        const hasSymbol = /[^A-Za-z0-9]/.test(newPassword);
        
        if (!hasUpper || !hasLower || !hasNumber || !hasSymbol) {
            e.preventDefault();
            alert('รหัสผ่านต้องประกอบด้วยตัวอักษรใหญ่ เล็ก ตัวเลข และสัญลักษณ์');
            return;
        }
    });
    </script>
</body>
</html>