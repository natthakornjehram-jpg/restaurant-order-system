<?php
// customer/forgot_password.php
session_start();
require_once 'includes/db.php';
date_default_timezone_set('Asia/Bangkok');

$step = isset($_SESSION['reset_step']) ? $_SESSION['reset_step'] : 1;
$error = "";
$success = "";

// กรณีต้องการยกเลิกและกลับไปเริ่มใหม่
if (isset($_GET['cancel'])) {
    unset($_SESSION['reset_step']);
    unset($_SESSION['reset_phone']);
    unset($_SESSION['reset_id']);
    unset($_SESSION['mock_otp']);
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // --- สเต็ปที่ 1: กรอกเบอร์โทรศัพท์เพื่อขอ OTP ---
    if (isset($_POST['request_otp'])) {
        $phone = trim($_POST['phone']);
        
        // เช็กว่าเบอร์นี้เป็นเจ้าของร้านที่ลงทะเบียนไว้ไหม (ระบบร้านเดี่ยว ไม่มีบัญชีลูกค้าแล้ว)
        $stmt_check_owner = $conn->prepare("SELECT owner_id FROM owner WHERE phone = ? LIMIT 1");
        $stmt_check_owner->bind_param("s", $phone);
        $stmt_check_owner->execute();
        $owner_row = $stmt_check_owner->get_result()->fetch_assoc();

        if ($owner_row) {
            $matched_owner_id = (int) $owner_row['owner_id'];

            // สร้าง OTP 6 หลัก
            $otp = rand(100000, 999999);
            $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes')); // หมดอายุใน 5 นาที

            // บันทึกลงตาราง password_reset (customer_id เป็น NULL ได้ เพราะไม่มีระบบบัญชีลูกค้าแล้ว)
            $stmt = $conn->prepare("INSERT INTO password_reset (owner_id, phone, otp, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $matched_owner_id, $phone, $otp, $expires_at);
            if ($stmt->execute()) {
                $_SESSION['reset_step'] = 2;
                $_SESSION['reset_phone'] = $phone;
                $_SESSION['mock_otp'] = $otp; // จำลองเก็บไว้โชว์ Alert

                header("Location: forgot_password.php");
                exit;
            }
        } else {
            $error = "ไม่พบเบอร์โทรศัพท์นี้ในระบบครับ";
        }
    }

    // --- สเต็ปที่ 2: กรอกรหัส OTP ---
    if (isset($_POST['verify_otp'])) {
        $otp_input = trim($_POST['otp']);
        $phone = $_SESSION['reset_phone'];

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

            $_SESSION['reset_step'] = 3;
            $_SESSION['reset_id'] = (int) $reset_data['reset_id'];
            header("Location: forgot_password.php");
            exit;
        } else {
            $error = "รหัส OTP ไม่ถูกต้อง หรือหมดอายุแล้วครับ (อายุ 5 นาที)";
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
            unset($_SESSION['reset_id']);
            unset($_SESSION['mock_otp']);

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
    <title>ลืมรหัสผ่าน - ระบบร้านอาหาร</title>
    <link href="https://fonts.googleapis.com/css2?family=Mitr&family=Sarabun&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #fdfaf5; font-family: 'Sarabun', sans-serif; color: #3e2723; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .reset-card { width: 100%; max-width: 450px; background: white; padding: 40px; border-radius: 35px; box-shadow: 0 15px 35px rgba(62,39,35,0.1); border: 1px solid #efebe9; }
        .btn-brown { background: #795548; color: white; border-radius: 50px; padding: 12px; border: none; width: 100%; font-weight: bold; transition: 0.3s; }
        .btn-brown:hover { background: #3e2723; transform: translateY(-2px); color: white; }
        h2 { font-family: 'Mitr', sans-serif; color: #3e2723; text-align: center; }
        .form-control { border-radius: 15px; padding: 12px; border: 1px solid #d7ccc8; background-color: #fcfaf9; text-align: center; font-size: 1.1rem; }
        .form-control:focus { border-color: #795548; box-shadow: none; background: #fff; }
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
                <div class="alert alert-danger text-center rounded-4 border-0 mb-4 shadow-sm py-2"><small><i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?></small></div>
            <?php endif; ?>

            <?php if($step == 1): ?>
                <p class="text-center text-muted mb-4 small">กรุณากรอกเบอร์โทรศัพท์ที่ลงทะเบียนไว้<br>เพื่อรับรหัส OTP รีเซ็ตรหัสผ่าน</p>
                <form method="POST">
                    <div class="mb-4">
                        <input type="text" name="phone" class="form-control" placeholder="เบอร์โทรศัพท์ (เช่น 0812345678)" required>
                    </div>
                    <button type="submit" name="request_otp" class="btn-brown shadow">รับรหัส OTP</button>
                    <div class="text-center mt-3"><a href="?cancel=1" class="text-muted small text-decoration-none">ยกเลิก / กลับไปหน้าล็อกอิน</a></div>
                </form>
            <?php endif; ?>

            <?php if($step == 2): ?>
                <p class="text-center text-muted mb-4 small">รหัส OTP 6 หลัก ถูกส่งไปที่เบอร์<br><strong class="text-dark"><?= $_SESSION['reset_phone'] ?></strong></p>
                <form method="POST">
                    <div class="mb-4">
                        <input type="text" name="otp" class="form-control fw-bold" placeholder="X X X X X X" maxlength="6" style="letter-spacing: 5px;" required>
                    </div>
                    <button type="submit" name="verify_otp" class="btn-brown shadow">ยืนยันรหัส OTP</button>
                    <div class="text-center mt-3"><a href="?cancel=1" class="text-muted small text-decoration-none">ยกเลิกการทำรายการ</a></div>
                </form>

                <?php if(isset($_SESSION['mock_otp'])): ?>
                    <script>
                        setTimeout(function() {
                            alert("📲 จำลอง SMS เข้ามือถือ:\n\nรหัส OTP สำหรับรีเซ็ตรหัสผ่านของคุณคือ: [ <?= $_SESSION['mock_otp'] ?> ]\nรหัสมีอายุ 5 นาที");
                        }, 500);
                    </script>
                <?php endif; ?>
            <?php endif; ?>

            <?php if($step == 3): ?>
                <p class="text-center text-success fw-bold mb-4 small"><i class="bi bi-check-circle-fill"></i> ยืนยันตัวตนสำเร็จ!<br>กรุณาตั้งรหัสผ่านใหม่ของคุณ</p>
                <form method="POST">
                    <div class="mb-3">
                        <input type="password" name="new_password" class="form-control" placeholder="รหัสผ่านใหม่" required>
                    </div>
                    <div class="mb-4">
                        <input type="password" name="confirm_password" class="form-control" placeholder="ยืนยันรหัสผ่านใหม่อีกครั้ง" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn-brown shadow">บันทึกรหัสผ่านใหม่</button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>