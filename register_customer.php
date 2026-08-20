<?php
// register_customer.php (วางไว้ข้างนอกโฟลเดอร์หลัก คู่กับ login_customer.php)
session_start();
require_once 'includes/db.php'; // 💡 แก้ path ให้ดึงจากโฟลเดอร์ includes โดยตรง

// ถ้าล็อกอินอยู่แล้ว ไม่ต้องสมัครใหม่ ให้เด้งกลับไปตาม Whitelist
if (isset($_SESSION['customer_id'])) {
    $target_page = $_SESSION['redirect_to'] ?? '';
    $allowed_pages = ['menu.php', 'cart.php', 'member/history.php', 'member/checkout.php'];
    $redirect = in_array($target_page, $allowed_pages) ? $target_page : 'menu.php';
    unset($_SESSION['redirect_to']);
    header("Location: " . $redirect);
    exit;
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. รับค่าและทำความสะอาดข้อมูล
    $username = trim($_POST['username']);
    $password = $_POST['password']; 
    $confirm_password = $_POST['confirm_password'];
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);

    // 2. ตรวจสอบความถูกต้องเบื้องต้น
    if (empty($username) || empty($password) || empty($name) || empty($phone)) {
        $error = "กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน";
    } elseif ($password !== $confirm_password) {
        $error = "รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน!";
    } else {
        // 3. เช็กว่า Username นี้มีคนใช้หรือยัง
        $stmt_check = $conn->prepare("SELECT customer_id FROM customer WHERE username = ? LIMIT 1");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows > 0) {
            $error = "ชื่อผู้ใช้งาน (Username) นี้มีในระบบแล้ว กรุณาใช้ชื่ออื่นครับ";
        } else {
            // 4. เข้ารหัสผ่านเพื่อความปลอดภัย
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // 5. บันทึกลงฐานข้อมูลตาราง customer
            $stmt_insert = $conn->prepare("INSERT INTO customer (username, password, name, phone, email) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("sssss", $username, $hashed_password, $name, $phone, $email);

            if ($stmt_insert->execute()) {
                
                // 🛡️ ป้องกัน Session Fixation แบบเดียวกับหน้า Login
                session_regenerate_id(true); 

                // ✅ สมัครเสร็จแล้ว ล็อกอินให้เลยอัตโนมัติ
                $new_customer_id = $conn->insert_id;
                $_SESSION['role'] = 'customer';
                $_SESSION['customer_id'] = $new_customer_id; 

                // ✅ ใช้ระบบ Whitelist ในการเด้งกลับไปหน้าต่างๆ
                $target_page = $_SESSION['redirect_to'] ?? '';
                $allowed_pages = ['menu.php', 'cart.php', 'member/history.php', 'member/checkout.php'];
                $redirect = in_array($target_page, $allowed_pages) ? $target_page : 'menu.php';
                unset($_SESSION['redirect_to']); 
                
                // ✅ ต้องใช้ json_encode() แทนทุกตัวที่อยู่ใน JS
                $safe_name = json_encode($name);
                $safe_redirect = json_encode($redirect);
                echo "<script>
                    alert('สมัครสมาชิกสำเร็จ! ยินดีต้อนรับคุณ ' + $safe_name);
                    window.location.href = $safe_redirect;
                </script>";
                exit;
            } else {
                error_log("Register error: " . $conn->error);
                $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - RANNAIBAAN</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth.css">
</head>
<body>
    <div class="container px-3">
        <div class="register-card mx-auto">
            
            <div class="text-center mb-4">
                <div class="bg-theme-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                    <i class="bi bi-person-plus-fill text-theme" style="font-size: 2rem;"></i>
                </div>
                <h4 class="fw-bold mb-1" style="color: #4a3b32;">สร้างบัญชีใหม่</h4>
                <p class="text-muted small">สมัครสมาชิกเพื่อความสะดวกในการสั่งอาหาร</p>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger small rounded-3 border-0 py-2 mb-4 text-center shadow-sm">
                    <i class="bi bi-exclamation-circle-fill me-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <h6 class="fw-bold mb-3 text-theme"><i class="bi bi-shield-lock me-2"></i>ข้อมูลการเข้าสู่ระบบ</h6>
                
                <div class="mb-3">
                    <input type="text" name="username" class="form-control" placeholder="ชื่อผู้ใช้งาน (Username)" required>
                </div>
                
                <div class="row g-2 mb-4">
                    <div class="col-6">
                        <input type="password" name="password" class="form-control" placeholder="รหัสผ่าน" required>
                    </div>
                    <div class="col-6">
                        <input type="password" name="confirm_password" class="form-control" placeholder="ยืนยันรหัสผ่าน" required>
                    </div>
                    <div class="col-12"><small class="text-muted" style="font-size: 0.75rem;">* ทดสอบระบบ พิมพ์ตัวเลขอย่างเดียวได้</small></div>
                </div>

                <h6 class="fw-bold mb-3 text-theme"><i class="bi bi-person-lines-fill me-2"></i>ข้อมูลส่วนตัว</h6>
                
                <div class="mb-3">
                    <input type="text" name="name" class="form-control" placeholder="ชื่อ - นามสกุล" required>
                </div>
                
                <div class="mb-3">
                    <input type="tel" name="phone" class="form-control" placeholder="เบอร์โทรศัพท์มือถือ" required>
                </div>
                
                <div class="mb-4">
                    <input type="email" name="email" class="form-control" placeholder="อีเมล (ถ้ามี)">
                </div>
                
                <button type="submit" class="btn btn-theme shadow-sm mb-4">สมัครสมาชิก และไปสั่งอาหาร</button>
            </form>

            <div class="text-center small border-top pt-3">
                <span class="text-muted">มีบัญชีอยู่แล้ว?</span> 
                <a href="login_customer.php" class="fw-bold text-theme text-decoration-none">เข้าสู่ระบบ</a>
            </div>
            
            <div class="mt-4 text-center">
                <a href="menu.php" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> กลับไปหน้าแรก</a>
            </div>
        </div>
    </div>
</body>
</html>