<?php
// pages/500.php
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เกิดข้อผิดพลาดของระบบ - SSJBox</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="text-center mt-5">
                    <div class="error-icon mb-4">
                        <i class="fas fa-server fa-5x text-danger"></i>
                    </div>
                    <h1 class="display-1 fw-bold text-danger">500</h1>
                    <h2 class="mb-4">เกิดข้อผิดพลาดของระบบ</h2>
                    <p class="lead mb-4">
                        ขออภัย เกิดข้อผิดพลาดทางเทคนิค กรุณาลองใหม่อีกครั้งในภายหลัง
                    </p>
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        หากปัญหายังคงอยู่ กรุณาติดต่อผู้ดูแลระบบ
                    </div>
                    <div class="d-grid gap-2 d-md-block">
                        <a href="/" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i>กลับหน้าหลัก
                        </a>
                        <button class="btn btn-secondary" onclick="history.back()">
                            <i class="fas fa-arrow-left me-2"></i>ย้อนกลับ
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>