<?php
// customer/forgot_password.php
session_start();
require_once 'includes/db.php'; // ตั้งเขตเวลา Asia/Bangkok ให้แล้วจากตรงนี้
require_once 'includes/csrf.php';
require_once 'includes/send_email_otp.php';

$step = isset($_SESSION['reset_step']) ? $_SESSION['reset_step'] : 1;
$error = "";
$success = "";

// กรณีต้องการยกเลิกและกลับไปเริ่มใหม่
if (isset($_GET['cancel'])) {
    unset($_SESSION['reset_step']);
    unset($_SESSION['reset_phone']);
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_id']);
    unset($_SESSION['mock_otp']);
    unset($_SESSION['otp_attempts']);
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !csrf_verify($_POST['csrf_token'] ?? '')) {
    $error = "คำขอไม่ถูกต้อง (CSRF token ไม่ถูกต้อง) กรุณาลองใหม่อีกครั้ง";
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // --- สเต็ปที่ 1: กรอกเบอร์โทรศัพท์เพื่อขอ OTP ---
    if (isset($_POST['request_otp'])) {
        $phone = trim($_POST['phone']);
        
        // เช็กว่าเบอร์นี้เป็นเจ้าของร้านที่ลงทะเบียนไว้ไหม (ระบบร้านเดี่ยว ไม่มีบัญชีลูกค้าแล้ว)
        $stmt_check_owner = $conn->prepare("SELECT owner_id, name, email FROM owner WHERE phone = ? LIMIT 1");
        $stmt_check_owner->bind_param("s", $phone);
        $stmt_check_owner->execute();
        $owner_row = $stmt_check_owner->get_result()->fetch_assoc();

        if (!$owner_row) {
            $error = "ไม่พบเบอร์โทรศัพท์นี้ในระบบครับ";
        } elseif (empty($owner_row['email']) && !$is_localhost) {
            $error = "บัญชีนี้ยังไม่ได้ตั้งอีเมลไว้รับ OTP กรุณาให้ผู้ดูแลระบบตั้งอีเมลในหน้าตั้งค่าร้านก่อนครับ";
        } else {
            $matched_owner_id = (int) $owner_row['owner_id'];

            // จำกัดจำนวนครั้งที่ขอ OTP: ไม่เกิน 3 ครั้งต่อเบอร์ ภายใน 15 นาที กันสแปม/บรูทฟอร์ซ
            $rate_stmt = $conn->prepare("SELECT COUNT(*) AS request_count FROM password_reset WHERE phone = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)");
            $rate_stmt->bind_param("s", $phone);
            $rate_stmt->execute();
            $request_count = intval($rate_stmt->get_result()->fetch_assoc()['request_count']);

            if ($request_count >= 3) {
                $error = "ขอรหัส OTP บ่อยเกินไป กรุณารอ 15 นาทีแล้วลองใหม่ครับ";
            } else {
                // สร้าง OTP 6 หลัก ด้วย random_int (ปลอดภัยกว่า rand ซึ่งเดาได้)
                $otp = random_int(100000, 999999);
                $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes')); // หมดอายุใน 5 นาที

                // บันทึกลงตาราง password_reset
                $stmt = $conn->prepare("INSERT INTO password_reset (owner_id, phone, otp, expires_at) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $matched_owner_id, $phone, $otp, $expires_at);
                if ($stmt->execute()) {
                    // พยายามส่งอีเมลจริงก่อน (ถ้ามีอีเมลให้ลอง) แต่ยังไม่ได้ตั้งค่า Gmail SMTP เสร็จก็ไม่บล็อกการทำงาน
                    // บน localhost ยังโชว์รหัส OTP บนหน้าเว็บได้เหมือนเดิมเผื่อไว้ใช้เดโม/ทดสอบก่อน
                    $send_result = !empty($owner_row['email'])
                        ? send_email_otp($owner_row['email'], $owner_row['name'] ?? '', (string) $otp)
                        : ['success' => false, 'error' => 'ยังไม่ได้ตั้งอีเมลไว้'];

                    if ($send_result['success']) {
                        $_SESSION['reset_email'] = $owner_row['email'];
                    } elseif (!$is_localhost) {
                        // ไม่โชว์รายละเอียด error ดิบจาก Gmail SMTP ให้ผู้ใช้เห็นบนโฮสต์จริง (อาจมีรายละเอียดภายในระบบหลุดไป)
                        // เก็บรายละเอียดไว้ใน error_log ฝั่งเซิร์ฟเวอร์แทนสำหรับดีบั๊ก
                        error_log('send_email_otp failed: ' . $send_result['error']);
                        $error = "ส่งอีเมล OTP ไม่สำเร็จ กรุณาลองใหม่อีกครั้งภายหลัง หรือติดต่อผู้ดูแลระบบ";
                    }

                    if ($send_result['success'] || $is_localhost) {
                        $_SESSION['reset_step'] = 2;
                        $_SESSION['reset_phone'] = $phone;
                        $_SESSION['otp_attempts'] = 0;

                        // ยังไม่ได้ตั้งค่าอีเมลจริงเสร็จ (หรืออยู่บน localhost) โชว์ผ่าน alert() ไปพลางก่อน
                        if ($is_localhost) {
                            $_SESSION['mock_otp'] = $otp;
                        }

                        header("Location: forgot_password.php");
                        exit;
                    }
                }
            }
        }
    }

    // --- สเต็ปที่ 2: กรอกรหัส OTP ---
    if (isset($_POST['verify_otp'])) {
        $otp_input = trim($_POST['otp']);
        $phone = $_SESSION['reset_phone'];

        // จำกัดจำนวนครั้งที่กรอกผิด: ไม่เกิน 5 ครั้งภายใน 15 นาที กันบรูทฟอร์ซเดา OTP 6 หลัก
        // เดิมนับจาก $_SESSION['otp_attempts'] อย่างเดียว ซึ่งลบคุกกี้/เปิด session ใหม่แล้วเริ่มนับใหม่ได้
        // เปลี่ยนมานับจากตาราง otp_verify_attempts ที่ผูกกับเบอร์โทรแทน ทำให้รีเซ็ตตัวนับด้วยการล้าง session ไม่ได้อีก
        $fail_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM otp_verify_attempts WHERE phone = ? AND success = 0 AND created_at >= (NOW() - INTERVAL 15 MINUTE)");
        $fail_stmt->bind_param("s", $phone);
        $fail_stmt->execute();
        $attempts = intval($fail_stmt->get_result()->fetch_assoc()['cnt']);

        if ($attempts >= 5) {
            unset($_SESSION['reset_step']);
            unset($_SESSION['reset_phone']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['mock_otp']);
            unset($_SESSION['otp_attempts']);
            $error = "กรอกรหัส OTP ผิดเกินจำนวนที่กำหนด กรุณารอสักครู่แล้วขอรหัสใหม่อีกครั้งครับ";
        } else {
            // ค้นหา OTP ในระบบที่ยังไม่ถูกใช้และยังไม่หมดอายุ
            $stmt = $conn->prepare("SELECT reset_id FROM password_reset WHERE phone = ? AND otp = ? AND used = 0 AND expires_at >= NOW() ORDER BY created_at DESC LIMIT 1");
            $stmt->bind_param("ss", $phone, $otp_input);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                $reset_data = $res->fetch_assoc();
                // อัปเดตว่าใช้งาน OTP นี้ไปแล้ว
                $stmt_used = $conn->prepare("UPDATE password_reset SET used = 1 WHERE reset_id = ?");
                $stmt_used->bind_param("i", $reset_data['reset_id']);
                $stmt_used->execute();

                $log_stmt = $conn->prepare("INSERT INTO otp_verify_attempts (phone, success) VALUES (?, 1)");
                $log_stmt->bind_param("s", $phone);
                $log_stmt->execute();

                $_SESSION['reset_step'] = 3;
                $_SESSION['reset_id'] = (int) $reset_data['reset_id'];
                unset($_SESSION['otp_attempts']);
                header("Location: forgot_password.php");
                exit;
            } else {
                $log_stmt = $conn->prepare("INSERT INTO otp_verify_attempts (phone, success) VALUES (?, 0)");
                $log_stmt->bind_param("s", $phone);
                $log_stmt->execute();
                $error = "รหัส OTP ไม่ถูกต้อง หรือหมดอายุแล้วครับ (อายุ 5 นาที)";
            }
        }
    }

    // --- สเต็ปที่ 3: ตั้งรหัสผ่านใหม่ ---
    if (isset($_POST['reset_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $phone = $_SESSION['reset_phone'] ?? '';
        $reset_id = $_SESSION['reset_id'] ?? 0;

        // ยืนยันอีกครั้งว่า reset_id นี้ผูกกับเบอร์นี้จริง และผ่านการยืนยัน OTP มาแล้ว (used = 1)
        // ป้องกันไม่ให้พึ่งพา $_SESSION['reset_step'] เพียงอย่างเดียว
        $stmt_check = $conn->prepare("SELECT reset_id FROM password_reset WHERE reset_id = ? AND phone = ? AND used = 1 LIMIT 1");
        $stmt_check->bind_param("is", $reset_id, $phone);
        $stmt_check->execute();
        $valid_reset = $stmt_check->get_result()->num_rows > 0;

        if (!$valid_reset) {
            unset($_SESSION['reset_step']);
            unset($_SESSION['reset_phone']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_id']);
            $error = "เซสชันการรีเซ็ตรหัสผ่านไม่ถูกต้องหรือหมดอายุ กรุณาเริ่มใหม่ครับ";
        } elseif ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // อัปเดตรหัสผ่านใหม่ของเจ้าของร้าน (Prepared Statement ปลอดภัยจากการแฮก)
            $stmt_owner = $conn->prepare("UPDATE owner SET password = ? WHERE phone = ?");
            $stmt_owner->bind_param("ss", $hashed_password, $phone);
            $stmt_owner->execute();

            // ล้าง Session การรีเซ็ตรหัสผ่าน
            unset($_SESSION['reset_step']);
            unset($_SESSION['reset_phone']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_id']);
            unset($_SESSION['mock_otp']);
            unset($_SESSION['otp_attempts']);

            // 💡 สร้าง Session แจ้งเตือนสีเขียวเพื่อไปโชว์หน้า login.php
            $_SESSION['success_msg'] = "เปลี่ยนรหัสผ่านสำเร็จ! กรุณาล็อกอินด้วยรหัสผ่านใหม่ครับ";

            header("Location: login.php");
            exit;
        } else {
            $error = "รหัสผ่านใหม่ทั้งสองช่องไม่ตรงกันครับ";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - ระบบร้านอาหาร</title>
    <link href="https://fonts.googleapis.com/css2?family=Mitr&family=Sarabun&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { 
            background-color: #fdfaf5; 
            font-family: 'Sarabun', sans-serif; 
            color: #3e2723; 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 20px 12px;
            margin: 0;
        }
        .reset-card { 
            width: 100%; 
            max-width: 450px; 
            background: white; 
            padding: 35px 30px; 
            border-radius: 28px; 
            box-shadow: 0 15px 35px rgba(62,39,35,0.1); 
            border: 1px solid #efebe9; 
        }
        .btn-brown { 
            background: #795548; 
            color: white; 
            border-radius: 50px; 
            padding: 12px; 
            border: none; 
            width: 100%; 
            font-weight: bold; 
            font-size: 1.05rem;
            transition: 0.3s; 
        }
        .btn-brown:hover { background: #3e2723; transform: translateY(-2px); color: white; }
        h2 { font-family: 'Mitr', sans-serif; color: #3e2723; text-align: center; font-size: 1.6rem; }
        .form-control { border-radius: 15px; padding: 12px 15px; border: 1px solid #d7ccc8; background-color: #fcfaf9; text-align: center; font-size: 1.1rem; }
        .form-control:focus { border-color: #795548; box-shadow: none; background: #fff; }

        /* แอนิเมชันไอคอนลูกตาโชว์/ซ่อนรหัสผ่าน - เด้งเล็กน้อยตอนสลับ ให้รู้สึกว่ากดแล้วมีอะไรเกิดขึ้นจริง */
        .password-toggle-btn { transition: transform 0.15s ease; }
        .password-toggle-btn:active { transform: translateY(-50%) scale(0.85) !important; }
        @keyframes eyeIconPop {
            0% { transform: scale(0.4); opacity: 0; }
            60% { transform: scale(1.25); opacity: 1; }
            100% { transform: scale(1); }
        }
        .password-toggle-btn i.icon-pop { display: inline-block; animation: eyeIconPop 0.28s ease; }

        @media (max-width: 576px) {
            body { padding: 16px 12px; }
            .reset-card { padding: 25px 20px; border-radius: 22px; }
            h2 { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-card mx-auto">
            <div class="text-center mb-3">
                <i class="bi bi-shield-lock" style="font-size: 3.5rem; color: #795548;"></i>
            </div>
            <h2>ลืมรหัสผ่าน</h2>
            
            <?php if($error): ?>
                <div class="alert alert-danger text-center rounded-4 border-0 mb-4 shadow-sm py-2"><small><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></small></div>
            <?php endif; ?>

            <?php if($step == 1): ?>
                <p class="text-center text-muted mb-4 small">กรุณากรอกเบอร์โทรศัพท์ที่ลงทะเบียนไว้<br>เพื่อรับรหัส OTP รีเซ็ตรหัสผ่าน</p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-4">
                        <input type="text" name="phone" class="form-control" placeholder="เบอร์โทรศัพท์ (เช่น 0812345678)" required>
                    </div>
                    <button type="submit" name="request_otp" class="btn-brown shadow">รับรหัส OTP</button>
                    <div class="text-center mt-3"><a href="?cancel=1" class="text-muted small text-decoration-none">ยกเลิก / กลับไปหน้าล็อกอิน</a></div>
                </form>
            <?php endif; ?>

            <?php if($step == 2): ?>
                <?php if (!empty($_SESSION['reset_email'])): ?>
                    <p class="text-center text-muted mb-4 small">รหัส OTP 6 หลัก ถูกส่งไปที่อีเมล<br><strong class="text-dark"><?= htmlspecialchars($_SESSION['reset_email']) ?></strong></p>
                <?php else: ?>
                    <p class="text-center text-muted mb-4 small">รหัส OTP 6 หลัก สำหรับเบอร์<br><strong class="text-dark"><?= htmlspecialchars($_SESSION['reset_phone'] ?? '') ?></strong></p>
                <?php endif; ?>

                <?php if(isset($_SESSION['mock_otp'])): ?>
                    <!-- โหมดทดสอบ (localhost เท่านั้น): โชว์รหัส OTP ลงบนหน้าเว็บตรงๆ แทนการพึ่ง alert() อย่างเดียว
                         เพราะเบราว์เซอร์/เว็บวิวหลายตัวบล็อก alert() ได้ (เช่น เปิดผ่านแอปในเครือข่ายสังคม, ตั้งค่า
                         "block additional dialogs" ของ Chrome) ทำให้บางทีไม่เห็นรหัสเลยแม้ระบบจะสร้างให้แล้วก็ตาม -->
                    <div class="alert alert-warning text-center rounded-4 border-0 mb-4 shadow-sm py-3">
                        <div class="small fw-bold mb-1">📲 โหมดทดสอบ (localhost) — รหัส OTP ของคุณคือ</div>
                        <div class="fw-bold" style="font-size: 1.8rem; letter-spacing: 4px;"><?= htmlspecialchars((string) $_SESSION['mock_otp']) ?></div>
                        <div class="small text-muted mt-1">รหัสมีอายุ 5 นาที (โหมดนี้จะไม่โชว์บนโฮสต์จริงที่ตั้งค่าส่งอีเมลไว้แล้ว)</div>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-4">
                        <input type="text" name="otp" class="form-control fw-bold" placeholder="X X X X X X" maxlength="6" style="letter-spacing: 5px;" required>
                    </div>
                    <button type="submit" name="verify_otp" class="btn-brown shadow">ยืนยันรหัส OTP</button>
                    <div class="text-center mt-3"><a href="?cancel=1" class="text-muted small text-decoration-none">ยกเลิกการทำรายการ</a></div>
                </form>
            <?php endif; ?>

            <?php if($step == 3): ?>
                <p class="text-center text-success fw-bold mb-4 small"><i class="bi bi-check-circle-fill"></i> ยืนยันตัวตนสำเร็จ!<br>กรุณาตั้งรหัสผ่านใหม่ของคุณ</p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <div class="position-relative">
                            <input type="password" name="new_password" id="new_password" class="form-control pe-5" placeholder="รหัสผ่านใหม่" required>
                            <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-muted p-0 me-3 password-toggle-btn" style="z-index: 5;" tabindex="-1" onclick="togglePasswordField('new_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="position-relative">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control pe-5" placeholder="ยืนยันรหัสผ่านใหม่อีกครั้ง" required>
                            <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-muted p-0 me-3 password-toggle-btn" style="z-index: 5;" tabindex="-1" onclick="togglePasswordField('confirm_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" name="reset_password" class="btn-brown shadow">บันทึกรหัสผ่านใหม่</button>
                </form>
            <?php endif; ?>

        </div>
    </div>
    <script>
        // สลับโชว์/ซ่อนรหัสผ่าน (ปุ่มรูปลูกตา) - สลับ type ระหว่าง password กับ text แล้วเปลี่ยนไอคอนตาม
        // พร้อมแอนิเมชันเด้งเล็กน้อย (icon-pop) ทุกครั้งที่สลับ ให้รู้สึกตอบสนองมากขึ้น
        function togglePasswordField(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            icon.classList.toggle('bi-eye', showing);
            icon.classList.toggle('bi-eye-slash', !showing);

            icon.classList.remove('icon-pop');
            void icon.offsetWidth; // บังคับ reflow ให้เล่นแอนิเมชันซ้ำได้ทุกครั้งแม้กดรัวๆ
            icon.classList.add('icon-pop');
        }
    </script>
</body>
</html>