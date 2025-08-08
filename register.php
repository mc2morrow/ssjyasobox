<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/config/config.php';

// preload จังหวัด
$provinces = $pdo->query("SELECT province_code, province_name FROM province WHERE province_code like 35")->fetchAll();
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>ลงทะเบียนผู้ใช้</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="./assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="./assets/fontawesome/css/all.min.css" rel="stylesheet">
  <script src="./assets/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY); ?>"></script>
</head>
<body class="bg-light">
<div class="container py-4">
  <h1 class="mb-3">ลงทะเบียนผู้ใช้</h1>
  <form id="regForm" class="card p-3" method="post" action="register_handler.php" novalidate>
    <div class="row g-3">
      <!-- จังหวัด / อำเภอ / หน่วยงาน -->
      <div class="col-md-4">
        <label class="form-label"><i class="fa-solid fa-building-circle-check me-1"></i>จังหวัด</label>
        <select class="form-select" name="province_code" id="province" required>
          <option value="">-- เลือกจังหวัด --</option>
          <?php foreach ($provinces as $p): ?>
            <option value="<?= htmlspecialchars($p['province_code']) ?>"><?= htmlspecialchars($p['province_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="invalid-feedback">กรุณาเลือกจังหวัด</div>
      </div>
      <div class="col-md-4">
        <label class="form-label"><i class="fa-solid fa-building me-1"></i>อำเภอ</label>
        <select class="form-select" name="amphur_code" id="amphur" required disabled>
          <option value="">-- เลือกอำเภอ --</option>
        </select>
        <div class="invalid-feedback">กรุณาเลือกอำเภอ</div>
      </div>
      <div class="col-md-4">
        <label class="form-label"><i class="fa-solid fa-hospital me-1"></i>หน่วยงาน</label>
        <select class="form-select" name="hosp_code" id="hospital" required disabled>
          <option value="">-- เลือกหน่วยงาน --</option>
        </select>
        <div class="invalid-feedback">กรุณาเลือกหน่วยงาน</div>
      </div>

      <!-- ข้อมูลส่วนบุคคล -->
      <div class="col-md-3">
        <label class="form-label">คำนำหน้า</label>
        <select class="form-select" name="prefix" required>
          <option value="">-- เลือก --</option>
          <option>นาย</option><option>นาง</option><option>นางสาว</option>
        </select>
        <div class="invalid-feedback">กรุณาเลือกคำนำหน้า</div>
      </div>
      <div class="col-md-3">
        <label class="form-label">ชื่อ</label>
        <input type="text" class="form-control" name="first_name" required>
        <div class="invalid-feedback">กรุณากรอกชื่อ</div>
      </div>
      <div class="col-md-3">
        <label class="form-label">นามสกุล</label>
        <input type="text" class="form-control" name="last_name" required>
        <div class="invalid-feedback">กรุณากรอกนามสกุล</div>
      </div>
      <div class="col-md-3">
        <label class="form-label">ตำแหน่ง</label>
        <input type="text" class="form-control" name="position" required>
        <div class="invalid-feedback">กรุณากรอกตำแหน่ง</div>
      </div>

      <div class="col-md-4">
        <label class="form-label">เลขประจำตัวประชาชน (13 หลัก)</label>
        <input type="text" class="form-control" name="idcard" minlength="13" maxlength="13" pattern="\d{13}" required>
        <div class="form-text">ต้องเป็นตัวเลข 13 หลัก</div>
        <div class="invalid-feedback">กรุณากรอกเลข 13 หลักให้ถูกต้อง</div>
      </div>
      <div class="col-md-4">
        <label class="form-label">อีเมล</label>
        <input type="email" class="form-control" name="email" required>
        <div class="invalid-feedback">กรุณากรอกอีเมลให้ถูกต้อง</div>
      </div>
      <div class="col-md-4">
        <label class="form-label">เบอร์โทรศัพท์</label>
        <input type="text" class="form-control" name="phone" pattern="\d{9,10}" required>
        <div class="form-text">ตัวเลข 9-10 หลัก</div>
        <div class="invalid-feedback">กรุณากรอกเบอร์โทรให้ถูกต้อง</div>
      </div>

      <div class="col-md-4">
        <label class="form-label">Username</label>
        <input type="text" class="form-control" name="username" autocomplete="username" required>
        <div class="invalid-feedback">กรุณากำหนด Username</div>
      </div>

      <div class="col-md-4">
        <label class="form-label">รหัสผ่าน</label>
        <div class="input-group">
          <input type="text" class="form-control" name="password" id="password" autocomplete="new-password" required>
          <button class="btn btn-outline-secondary" type="button" id="genPassBtn">Generate</button>
        </div>
        <div class="form-text">อย่างน้อย 12 ตัว มี A-Z, a-z, 0-9, อักขระพิเศษ</div>
        <div class="invalid-feedback">รหัสผ่านไม่ผ่านเงื่อนไข</div>
      </div>

      <div class="col-md-4">
        <label class="form-label">ยืนยันรหัสผ่าน</label>
        <input type="text" class="form-control" name="password_confirm" id="password_confirm" required>
        <div class="invalid-feedback">รหัสผ่านไม่ตรงกัน</div>
      </div>

      <input type="hidden" name="recaptcha_token" id="recaptcha_token">

      <div class="col-12">
        <button class="btn btn-primary" type="submit">สมัครสมาชิก</button>
      </div>
    </div>
  </form>
</div>

<script>
  // Client-side validation + reCAPTCHA v3
  (function(){
    const form = document.getElementById('regForm');
    form.addEventListener('submit', async function(e){
      e.preventDefault();
      const valid = form.checkValidity() && checkPassword();
      form.classList.add('was-validated');
      if (!valid) return;

      grecaptcha.ready(async function() {
        const token = await grecaptcha.execute('<?php echo RECAPTCHA_SITE_KEY; ?>', {action:'register'});
        document.getElementById('recaptcha_token').value = token;
        form.submit();
      });
    });
  })();

  // Dependent dropdowns
  const province = document.getElementById('province');
  const amphur   = document.getElementById('amphur');
  const hospital = document.getElementById('hospital');

  province.addEventListener('change', async () => {
    amphur.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
    hospital.innerHTML = '<option value="">-- เลือกหน่วยงาน --</option>';
    amphur.disabled = !province.value;
    hospital.disabled = true;
    if (!province.value) return;

    const res = await fetch('ajax/fetch_amphur.php?province_code='+encodeURIComponent(province.value));
    const data = await res.json();
    data.forEach(a=>{
      amphur.insertAdjacentHTML('beforeend', `<option value="${a.amphur_code}">${a.amphur_name}</option>`);
    });
  });

  amphur.addEventListener('change', async () => {
    hospital.innerHTML = '<option value="">-- เลือกหน่วยงาน --</option>';
    hospital.disabled = !amphur.value;
    if (!amphur.value) return;

    const res = await fetch('ajax/fetch_hospital.php?amphur_code='+encodeURIComponent(amphur.value));
    const data = await res.json();
    data.forEach(h=>{
      hospital.insertAdjacentHTML('beforeend', `<option value="${h.hosp_code}">${h.hosp_shortname}</option>`);
    });
  });

  // Password generator + policy check
  const genBtn = document.getElementById('genPassBtn');
  const pwd = document.getElementById('password');
  const pwd2 = document.getElementById('password_confirm');

  genBtn.addEventListener('click', () => {
    pwd.value = genPassword(16);
    pwd2.value = '';
  });

  function genPassword(length=16){
    const upper='ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const lower='abcdefghijklmnopqrstuvwxyz';
    const digits='0123456789';
    const special='!@#$%^&*()-_=+[]{};:,.<>?';
    const all = upper+lower+digits+special;
    let p = [
      upper[Math.floor(Math.random()*upper.length)],
      lower[Math.floor(Math.random()*lower.length)],
      digits[Math.floor(Math.random()*digits.length)],
      special[Math.floor(Math.random()*special.length)]
    ];
    while (p.length < length) p.push(all[Math.floor(Math.random()*all.length)]);
    for (let i=p.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[p[i],p[j]]=[p[j],p[i]];}
    return p.join('');
  }

  function checkPassword(){
    const v = pwd.value;
    const ok = v.length>=12 && /[A-Z]/.test(v) && /[a-z]/.test(v) && /\d/.test(v) && /[^A-Za-z0-9]/.test(v);
    if (!ok) return false;
    if (pwd2.value !== v) return false;
    return true;
  }
</script>
</body>
</html>
