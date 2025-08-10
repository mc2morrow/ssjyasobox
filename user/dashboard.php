<?php
// user/dashboard.php - แก้ไขใหม่ทั้งหมด
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Encryption.php';
require_once '../classes/Session.php';
require_once '../classes/FileUpload.php';
require_once '../classes/Logger.php';

// Initialize objects
$session = new Session();
$user = new User();
$fileUpload = new FileUpload();
$logger = new Logger();

// Check if user is logged in
if (!$session->isLoggedIn()) {
    header('Location: ../login.php?msg=session_expired');
    exit;
}

$userId = $session->getUserId();

// Get user profile with error handling
try {
    $userProfile = $user->getUserProfile($userId);
    
    if (!$userProfile) {
        $session->destroySession();
        $logger->logUserActivity($userId, 'PROFILE_NOT_FOUND', 'User profile not found, destroying session');
        header('Location: ../login.php?msg=session_error');
        exit;
    }
    
    // Check user status
    if ($userProfile['user_status'] !== 'active') {
        $session->destroySession();
        $logger->logUserActivity($userId, 'INACTIVE_USER_ACCESS', 'Inactive user tried to access dashboard');
        header('Location: ../login.php?msg=account_not_active');
        exit;
    }
    
    // Check if user is admin (redirect to admin dashboard)
    if ($userProfile['user_role'] === 'admin') {
        $logger->logUserActivity($userId, 'ADMIN_REDIRECT', 'Admin redirected to admin dashboard');
        header('Location: ../admin/dashboard.php');
        exit;
    }
    
} catch (Exception $e) {
    $session->destroySession();
    $logger->error('Error getting user profile in dashboard: ' . $e->getMessage());
    header('Location: ../login.php?msg=session_error');
    exit;
}

$message = '';
$messageType = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !$session->verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'ข้อมูลการรักษาความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        $messageType = 'danger';
        $logger->logUserActivity($userId, 'CSRF_VALIDATION_FAILED', 'Invalid CSRF token in file upload');
    } else {
        $category = $_POST['file_category'] ?? '';
        $uploadDate = $_POST['upload_date'] ?? '';
        
        // Validate inputs
        if (empty($category) || empty($uploadDate)) {
            $message = 'กรุณาเลือกประเภทไฟล์และวันที่ส่งข้อมูล';
            $messageType = 'danger';
        } elseif (!in_array($category, ['HIS', 'F43'])) {
            $message = 'ประเภทไฟล์ไม่ถูกต้อง';
            $messageType = 'danger';
        } elseif (!isset($_FILES['upload_file']) || $_FILES['upload_file']['error'] !== UPLOAD_ERR_OK) {
            $message = 'กรุณาเลือกไฟล์ที่ต้องการอัพโหลด';
            $messageType = 'danger';
        } elseif (strtotime($uploadDate) > time()) {
            $message = 'วันที่ส่งข้อมูลไม่สามารถเป็นวันในอนาคตได้';
            $messageType = 'danger';
        } else {
            try {
                $result = $fileUpload->uploadFile($userId, $_FILES['upload_file'], $category, $uploadDate);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                
                if ($result['success']) {
                    // Clear form data on success
                    $_POST = [];
                }
            } catch (Exception $e) {
                $message = 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์';
                $messageType = 'danger';
                $logger->error('File upload error in dashboard: ' . $e->getMessage());
            }
        }
    }
}

// Handle file deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    // Validate CSRF token for GET request
    if (!isset($_GET['token']) || !$session->verifyCSRFToken($_GET['token'])) {
        $message = 'ข้อมูลการรักษาความปลอดภัยไม่ถูกต้อง';
        $messageType = 'danger';
    } else {
        try {
            $result = $fileUpload->deleteFile($_GET['delete'], $userId);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
        } catch (Exception $e) {
            $message = 'เกิดข้อผิดพลาดในการลบไฟล์';
            $messageType = 'danger';
            $logger->error('File delete error in dashboard: ' . $e->getMessage());
        }
    }
}

// Get user files with error handling
try {
    $userFiles = $fileUpload->getUserFiles($userId, null, 10);
} catch (Exception $e) {
    $userFiles = [];
    $logger->error('Error getting user files: ' . $e->getMessage());
}

// Get file statistics with error handling
try {
    $fileStats = $fileUpload->getFileStatistics($userId);
} catch (Exception $e) {
    $fileStats = ['HIS' => ['count' => 0, 'size' => 0], 'F43' => ['count' => 0, 'size' => 0]];
    $logger->error('Error getting file statistics: ' . $e->getMessage());
}

// Handle URL messages
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'file_updated':
            $message = 'อัพเดทข้อมูลไฟล์สำเร็จ';
            $messageType = 'success';
            break;
        case 'profile_updated':
            $message = 'อัพเดทโปรไฟล์สำเร็จ';
            $messageType = 'success';
            break;
        case 'file_not_found':
            $message = 'ไม่พบไฟล์ที่ต้องการ';
            $messageType = 'danger';
            break;
        case 'download_error':
            $message = 'ไม่สามารถดาวน์โหลดไฟล์ได้';
            $messageType = 'danger';
            break;
        case 'invalid_file':
            $message = 'ไฟล์ไม่ถูกต้อง';
            $messageType = 'danger';
            break;
    }
}

// Generate CSRF token
$csrfToken = $session->generateCSRFToken();

// Log dashboard access
$logger->logUserActivity($userId, 'DASHBOARD_ACCESS', 'User accessed dashboard');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลัก - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 1rem;
            color: white;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
            border-radius: 1rem;
            color: white;
        }
        
        .stats-card.his {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stats-card.f43 {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        .upload-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .file-card {
            transition: transform 0.2s ease;
        }
        
        .file-card:hover {
            transform: translateY(-5px);
        }
        
        .progress-custom {
            height: 8px;
            border-radius: 4px;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        
        .btn-gradient:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            color: white;
            transform: translateY(-1px);
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
            border-radius: 1rem;
            text-align: center;
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
            <h5>กำลังอัพโหลดไฟล์...</h5>
            <div class="progress progress-custom mt-3">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" style="width: 0%" id="uploadProgressBar"></div>
            </div>
            <small class="text-muted mt-2" id="uploadStatus">กำลังเตรียมความพร้อม...</small>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-cloud-upload-alt me-2"></i>SSJBox
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-home me-1"></i>หน้าหลัก
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="files.php">
                            <i class="fas fa-folder me-1"></i>จัดการไฟล์
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i>
                            <?php echo htmlspecialchars($userProfile['reg_firstname'] . ' ' . $userProfile['reg_lastname']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user-edit me-2"></i>แก้ไขโปรไฟล์
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- User Info Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body dashboard-header p-4">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1">
                                    <i class="fas fa-hospital me-2"></i>
                                    <?php echo htmlspecialchars($userProfile['hosp_name'] ?? 'ไม่พบข้อมูลหน่วยงาน'); ?>
                                </h4>
                                <p class="mb-0">
                                    <i class="fas fa-user me-2"></i>
                                    <?php echo htmlspecialchars($userProfile['reg_prefix'] . $userProfile['reg_firstname'] . ' ' . $userProfile['reg_lastname']); ?>
                                    <span class="mx-2">|</span>
                                    <i class="fas fa-briefcase me-1"></i>
                                    <?php echo htmlspecialchars($userProfile['reg_position']); ?>
                                </p>
                                <small class="opacity-75">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars(($userProfile['amphur_name'] ?? '') . ', ' . ($userProfile['province_name'] ?? '')); ?>
                                </small>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <p class="mb-1">
                                    <i class="fas fa-clock me-1"></i>
                                    เข้าสู่ระบบครั้งล่าสุด
                                </p>
                                <small class="opacity-75">
                                    <?php 
                                    if ($userProfile['user_last_login']) {
                                        echo date('d/m/Y H:i', strtotime($userProfile['user_last_login']));
                                    } else {
                                        echo 'ไม่มีข้อมูล';
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- File Upload Section -->
            <div class="col-lg-8">
                <div class="card upload-card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-cloud-upload-alt me-2"></i>อัพโหลดไฟล์
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- <form method="POST" enctype="multipart/form-data" id="uploadForm"> -->
                        <form method="POST" enctype="multipart/form-data" id="uploadForm" data-ajax-upload="true">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="file_category" class="form-label">ประเภทไฟล์ <span class="text-danger">*</span></label>
                                    <select class="form-select" id="file_category" name="file_category" required>
                                        <option value="">เลือกประเภทไฟล์</option>
                                        <option value="HIS" <?php echo (($_POST['file_category'] ?? '') === 'HIS') ? 'selected' : ''; ?>>HIS</option>
                                        <option value="F43" <?php echo (($_POST['file_category'] ?? '') === 'F43') ? 'selected' : ''; ?>>F43</option>
                                    </select>
                                    <div class="invalid-feedback">กรุณาเลือกประเภทไฟล์</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="upload_date" class="form-label">วันที่ส่งข้อมูล <span class="text-danger">*</span></label>
                                    <input type="date" class="form-select" id="upload_date" name="upload_date" 
                                           value="<?php echo htmlspecialchars($_POST['upload_date'] ?? date('Y-m-d')); ?>" 
                                           max="<?php echo date('Y-m-d'); ?>" required>
                                    <div class="invalid-feedback">กรุณาเลือกวันที่ส่งข้อมูล</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="upload_file" class="form-label">เลือกไฟล์ <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="upload_file" name="upload_file" 
                                       accept=".zip,.7z,.rar" required>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    อนุญาตเฉพาะไฟล์ .zip, .7z, .rar ขนาดไม่เกิน 512MB
                                </div>
                                <div class="invalid-feedback">กรุณาเลือกไฟล์ที่ต้องการอัพโหลด</div>
                            </div>
                            
                            <div class="mb-3" id="uploadProgress" style="display: none;">
                                <div class="progress progress-custom">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                         role="progressbar" style="width: 0%" id="progressBar"></div>
                                </div>
                                <small class="text-muted mt-1" id="progressStatus">กำลังอัพโหลด...</small>
                            </div>
                            
                            <button type="submit" name="upload_file" class="btn btn-gradient" id="uploadBtn">
                                <i class="fas fa-upload me-2"></i>อัพโหลดไฟล์
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Recent Files -->
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-file-archive me-2"></i>ไฟล์ล่าสุด
                        </h5>
                        <a href="files.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list me-1"></i>ดูทั้งหมด
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($userFiles)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-folder-open fa-3x mb-3 opacity-50"></i>
                                <h5>ยังไม่มีไฟล์ที่อัพโหลด</h5>
                                <p>เริ่มต้นด้วยการอัพโหลดไฟล์แรกของคุณ</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ชื่อไฟล์</th>
                                            <th>ประเภท</th>
                                            <th>ขนาด</th>
                                            <th>วันที่ส่งข้อมูล</th>
                                            <th>วันที่อัพโหลด</th>
                                            <th class="text-center">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($userFiles as $file): ?>
                                            <tr class="file-card">
                                                <td>
                                                    <i class="fas fa-file-archive text-primary me-2"></i>
                                                    <span title="<?php echo htmlspecialchars($file['original_filename']); ?>">
                                                        <?php 
                                                        $filename = $file['original_filename'];
                                                        echo htmlspecialchars(strlen($filename) > 30 ? substr($filename, 0, 30) . '...' : $filename);
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $file['file_category'] === 'HIS' ? 'success' : 'info'; ?>">
                                                        <?php echo htmlspecialchars($file['file_category']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo FileUpload::formatFileSize($file['file_size']); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($file['upload_date'])); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($file['file_uploaded_at'])); ?></td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="edit.php?id=<?php echo $file['file_id']; ?>" 
                                                           class="btn btn-outline-warning" title="แก้ไข">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="download.php?id=<?php echo $file['file_id']; ?>" 
                                                           class="btn btn-outline-success" title="ดาวน์โหลด">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <a href="?delete=<?php echo $file['file_id']; ?>&token=<?php echo urlencode($csrfToken); ?>" 
                                                           class="btn btn-outline-danger" title="ลบ"
                                                           onclick="return confirm('ต้องการลบไฟล์ \'<?php echo htmlspecialchars($file['original_filename']); ?>\' หรือไม่?\n\nการดำเนินการนี้ไม่สามารถย้อนกลับได้')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistics Sidebar -->
            <div class="col-lg-4">
                <div class="row mb-4">
                    <div class="col-12 mb-3">
                        <div class="card stats-card his text-white shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-database fa-2x mb-2"></i>
                                <h3 class="mb-1"><?php echo number_format($fileStats['HIS']['count']); ?></h3>
                                <p class="mb-1">ไฟล์ HIS</p>
                                <small class="opacity-75">
                                    <?php echo FileUpload::formatFileSize($fileStats['HIS']['size']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 mb-3">
                        <div class="card stats-card f43 text-white shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-file-medical fa-2x mb-2"></i>
                                <h3 class="mb-1"><?php echo number_format($fileStats['F43']['count']); ?></h3>
                                <p class="mb-1">ไฟล์ F43</p>
                                <small class="opacity-75">
                                    <?php echo FileUpload::formatFileSize($fileStats['F43']['size']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>สรุปการใช้งาน
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>ไฟล์ทั้งหมด:</span>
                            <strong><?php echo number_format($fileStats['HIS']['count'] + $fileStats['F43']['count']); ?> ไฟล์</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>ขนาดรวม:</span>
                            <strong><?php echo FileUpload::formatFileSize($fileStats['HIS']['size'] + $fileStats['F43']['size']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>ความจุที่เหลือ:</span>
                            <strong class="text-success">ไม่จำกัด</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span>สถานะบัญชี:</span>
                            <span class="badge bg-success">ใช้งานได้</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>เมนูด่วน
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="files.php" class="btn btn-outline-primary">
                                <i class="fas fa-folder-open me-2"></i>จัดการไฟล์ทั้งหมด
                            </a>
                            <a href="profile.php" class="btn btn-outline-secondary">
                                <i class="fas fa-user-edit me-2"></i>แก้ไขโปรไฟล์
                            </a>
                            <a href="../logout.php" class="btn btn-outline-danger" 
                               onclick="return confirm('ต้องการออกจากระบบหรือไม่?')">
                                <i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/upload-progress.js"></script>
    <script>
    $(document).ready(function() {
        // File size and type validation
        $('#upload_file').change(function() {
            const file = this.files[0];
            if (file) {
                const maxSize = 536870912; // 512MB
                const allowedTypes = ['.zip', '.7z', '.rar'];
                const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
                
                // Reset validation state
                $(this).removeClass('is-valid is-invalid');
                
                if (file.size > maxSize) {
                    $(this).addClass('is-invalid');
                    $(this).siblings('.invalid-feedback').text('ไฟล์มีขนาดใหญ่เกิน 512MB');
                    $(this).val('');
                    showToast('error', 'ไฟล์มีขนาดใหญ่เกิน 512MB');
                    return;
                }
                
                if (!allowedTypes.includes(fileExtension)) {
                    $(this).addClass('is-invalid');
                    $(this).siblings('.invalid-feedback').text('นามสกุลไฟล์ไม่ได้รับอนุญาต');
                    $(this).val('');
                    showToast('error', 'นามสกุลไฟล์ไม่ได้รับอนุญาต (อนุญาตเฉพาะ .zip, .7z, .rar)');
                    return;
                }
                
                // File is valid
                $(this).addClass('is-valid');
                const fileSize = (file.size / (1024 * 1024)).toFixed(2);
                $(this).siblings('.form-text').html(
                    `<i class="fas fa-check-circle text-success me-1"></i>` +
                    `ไฟล์: ${file.name} (${fileSize} MB)`
                );
            }
        });
        
        // Form validation
        $('#uploadForm').on('submit', function(e) {
            let isValid = true;
            
            // Clear previous validation
            $('.form-control, .form-select').removeClass('is-invalid');
            
            // Check file category
            const category = $('#file_category').val();
            if (!category) {
                $('#file_category').addClass('is-invalid');
                isValid = false;
            }
            
            // Check upload date
            const uploadDate = $('#upload_date').val();
            if (!uploadDate) {
                $('#upload_date').addClass('is-invalid');
                isValid = false;
            } else {
                const selectedDate = new Date(uploadDate);
                const today = new Date();
                today.setHours(23, 59, 59, 999); // End of today
                
                if (selectedDate > today) {
                    $('#upload_date').addClass('is-invalid');
                    $('#upload_date').siblings('.invalid-feedback').text('วันที่ส่งข้อมูลไม่สามารถเป็นวันในอนาคตได้');
                    isValid = false;
                }
            }
            
            // Check file
            const file = $('#upload_file')[0].files[0];
            if (!file) {
                $('#upload_file').addClass('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                showToast('error', 'กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง');
                
                // Focus on first invalid field
                $('.is-invalid').first().focus();
                return false;
            }
            
            // Show upload progress
            showUploadProgress();
        });
        
        // Show upload progress
        function showUploadProgress() {
            $('#loadingOverlay').show();
            $('#uploadBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>กำลังอัพโหลด...');
            
            // Simulate progress for visual feedback
            let progress = 0;
            const progressInterval = setInterval(function() {
                progress += Math.random() * 15;
                if (progress >= 95) {
                    clearInterval(progressInterval);
                    progress = 95;
                }
                
                $('#uploadProgressBar').css('width', progress + '%');
                $('#uploadStatus').text(`กำลังอัพโหลด... ${Math.round(progress)}%`);
            }, 200);
            
            // Hide progress after form submission
            setTimeout(function() {
                clearInterval(progressInterval);
                $('#uploadProgressBar').css('width', '100%');
                $('#uploadStatus').text('กำลังประมวลผล...');
            }, 3000);
        }
        
        // Auto-hide alerts
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 8000);
        
        // Confirm delete with enhanced dialog
        $('a[href*="delete"]').click(function(e) {
            const fileName = $(this).closest('tr').find('td:first span').attr('title') || 'ไฟล์นี้';
            const confirmMessage = `ต้องการลบไฟล์ "${fileName}" หรือไม่?\n\nการดำเนินการนี้ไม่สามารถย้อนกลับได้`;
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });
        
        // Enhanced tooltips
        $('[title]').tooltip({
            placement: 'top',
            trigger: 'hover'
        });
        
        // Auto-refresh file list every 5 minutes
        setInterval(function() {
            if (!$('#uploadForm').find('input[type="file"]').val()) {
                // Only refresh if no file is selected
                window.location.reload();
            }
        }, 300000); // 5 minutes
        
        // Keyboard shortcuts
        $(document).keydown(function(e) {
            // Ctrl + U for upload focus
            if (e.ctrlKey && e.keyCode === 85) {
                e.preventDefault();
                $('#upload_file').click();
            }
            
            // Ctrl + F for files page
            if (e.ctrlKey && e.keyCode === 70) {
                e.preventDefault();
                window.location.href = 'files.php';
            }
        });
        
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
        
        // Initialize dashboard features
        initializeDashboard();
        
        function initializeDashboard() {
            // Check if there are any files to show welcome message
            const fileCount = <?php echo $fileStats['HIS']['count'] + $fileStats['F43']['count']; ?>;
            
            if (fileCount === 0 && !sessionStorage.getItem('welcomeShown')) {
                setTimeout(function() {
                    showToast('info', 'ยินดีต้อนรับสู่ระบบ SSJBox! เริ่มต้นด้วยการอัพโหลดไฟล์แรกของคุณ');
                    sessionStorage.setItem('welcomeShown', 'true');
                }, 1000);
            }
            
            // Update last activity timestamp
            setInterval(function() {
                $.post('../ajax/update_activity.php').fail(function() {
                    console.log('Failed to update activity');
                });
            }, 60000); // Every minute
        }
        
        // File statistics animation
        function animateStats() {
            $('.stats-card h3').each(function() {
                const $this = $(this);
                const countTo = parseInt($this.text().replace(/,/g, ''));
                
                $({ countNum: 0 }).animate({
                    countNum: countTo
                }, {
                    duration: 2000,
                    easing: 'swing',
                    step: function() {
                        $this.text(Math.floor(this.countNum).toLocaleString());
                    },
                    complete: function() {
                        $this.text(countTo.toLocaleString());
                    }
                });
            });
        }
        
        // Run stats animation on page load
        setTimeout(animateStats, 500);
        
        // Drag and drop file upload
        const uploadArea = $('#upload_file');
        const uploadForm = $('#uploadForm');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadForm[0].addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadForm[0].addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadForm[0].addEventListener(eventName, unhighlight, false);
        });
        
        uploadForm[0].addEventListener('drop', handleDrop, false);
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        function highlight(e) {
            uploadForm.addClass('border-primary bg-light');
        }
        
        function unhighlight(e) {
            uploadForm.removeClass('border-primary bg-light');
        }
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                uploadArea[0].files = files;
                uploadArea.trigger('change');
                showToast('success', 'ไฟล์ถูกเลือกแล้ว กรุณาตรวจสอบข้อมูลและคลิกอัพโหลด');
            }
        }
        
        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = window.performance.timing.loadEventEnd - window.performance.timing.navigationStart;
            if (loadTime > 3000) {
                console.warn('Dashboard loaded slowly:', loadTime + 'ms');
            }
        });
    });
    </script>
</body>
</html>