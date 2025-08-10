<?php
// user/edit.php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Session.php';
require_once '../classes/FileUpload.php';

$session = new Session();
$fileUpload = new FileUpload();

if (!$session->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$userId = $session->getUserId();
$fileId = $_GET['id'] ?? null;

if (!$fileId || !is_numeric($fileId)) {
    header('Location: dashboard.php?msg=invalid_file');
    exit;
}

$file = $fileUpload->getFileById($fileId, $userId);

if (!$file) {
    header('Location: dashboard.php?msg=file_not_found');
    exit;
}

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $fileUpload->updateFile($fileId, $userId, $_POST);
    
    if ($result['success']) {
        header('Location: dashboard.php?msg=file_updated');
        exit;
    } else {
        $message = $result['message'];
        $messageType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขไฟล์ - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-edit me-2"></i>แก้ไขข้อมูลไฟล์
                        </h5>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?>">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-muted">ข้อมูลไฟล์ปัจจุบัน</h6>
                                <div class="bg-light p-3 rounded">
                                    <p class="mb-1"><strong>ชื่อไฟล์:</strong> <?php echo htmlspecialchars($file['original_filename']); ?></p>
                                    <p class="mb-1"><strong>ประเภท:</strong> 
                                        <span class="badge bg-<?php echo $file['file_category'] === 'HIS' ? 'success' : 'info'; ?>">
                                            <?php echo $file['file_category']; ?>
                                        </span>
                                    </p>
                                    <p class="mb-1"><strong>ขนาด:</strong> <?php echo FileUpload::formatFileSize($file['file_size']); ?></p>
                                    <p class="mb-0"><strong>วันที่อัพโหลด:</strong> <?php echo date('d/m/Y H:i', strtotime($file['file_uploaded_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="upload_date" class="form-label">วันที่ส่งข้อมูล <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="upload_date" name="upload_date" 
                                       value="<?php echo $file['upload_date']; ?>" required>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    วันที่ปัจจุบัน: <?php echo date('d/m/Y', strtotime($file['upload_date'])); ?>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-save me-2"></i>บันทึกการแก้ไข
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>ยกเลิก
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Set max date to today
    document.getElementById('upload_date').max = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>