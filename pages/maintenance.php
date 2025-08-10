<?php
// pages/maintenance.php
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ปรับปรุงระบบ - SSJBox</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="text-center mt-5">
                    <div class="maintenance-icon mb-4">
                        <i class="fas fa-tools fa-5x text-warning"></i>
                    </div>
                    <h1 class="display-4 fw-bold text-primary">กำลังปรับปรุงระบบ</h1>
                    <p class="lead mb-4">
                        ขณะนี้ระบบอยู่ระหว่างการปรับปรุง เพื่อให้บริการที่ดีขึ้น
                    </p>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-clock me-2"></i>
                        คาดว่าจะเสร็จสิ้นภายใน 2-3 ชั่วโมง
                    </div>
                    <p class="text-muted">
                        ขออภัยในความไม่สะดวก กรุณาลองเข้าใช้งานใหม่ภายหลัง
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Auto refresh every 5 minutes
    setTimeout(function() {
        location.reload();
    }, 300000);
    </script>
</body>
</html>