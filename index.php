<?php

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row min-vh-100">
            <div class="col-lg-6 d-flex align-items-center justify-content-center bg-success bg-gradient">
                <div class="text-center text-white">
                    <h1 class="display-4 fw-bold mb-4">
                        <i class="bi bi-cloud-upload"></i>
                        SSJBox
                    </h1>
                    <p class="lead mb-4">ระบบอัพโหลดไฟล์ข้อมูล HosXP และ F43</p>
                    <div class="row text-center">
                        <div class="col-md-6">
                            <div class="card bg-transparent border-light">
                                <div class="card-body">
                                    <i class="bi bi-shield-check display-6 mb-3"></i>
                                    <h5>ปลอดภัย</h5>
                                    <p>เข้ารหัสข้อมูลด้วย AES-256</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-transparent border-light">
                                <div class="card-body">
                                    <i class="bi bi-speedometer2 display-6 mb-3"></i>
                                    <h5>รวดเร็ว</h5>
                                    <p>อัพโหลดไฟล์ขนาดใหญ่ได้ถึง 500MB</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 d-flex align-items-center justify-content-center">
                <div class="w-100" style="max-width: 400px;">
                    <div class="text-center mb-5">
                        <h2 class="fw-bold text-dark mb-2">เข้าสู่ระบบ</h2>
                        <p class="text-muted">กรุณาเข้าสู่ระบบเพื่อใช้งาน</p>
                    </div>
                    
                    <div class="d-grid gap-3">
                        <a href="login.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>
                            เข้าสู่ระบบ
                        </a>
                        <a href="register.php" class="btn btn-outline-primary btn-lg">
                            <i class="bi bi-person-plus me-2"></i>
                            สมัครสมาชิก
                        </a>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <small class="text-muted">
                            <!-- <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?><br> -->
                            สำหรับหน่วยงานในสังกัดกระทรวงสาธารณสุข
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>