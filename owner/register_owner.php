<?php
// owner/register_owner.php
session_start();
require_once '../includes/db.php';
require_once '../includes/csrf.php';

// ระบบร้านเดียว มีเจ้าของร้านได้แค่คนเดียวเท่านั้น - ถ้ามีอยู่แล้วห้ามสมัครซ้ำ (กันคนนอกมาสร้างบัญชีแอบแฝง)
$owner_exists = $conn->query("SELECT COUNT(*) AS c FROM owner")->fetch_assoc()['c'] > 0;
if ($owner_exists) {
    header("Location: ../login.php");
    exit;
}

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !csrf_verify($_POST['csrf_token'] ?? '')) {
    $error_message = "คำขอไม่ถูกต้อง (CSRF token ไม่ถูกต้อง) กรุณาลองใหม่อีกครั้ง";
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password_input = $_POST['password'];
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $restaurant_name = trim($_POST['restaurant_name']);
    $address = trim($_POST['address']);

    if (empty($username) || empty($password_input) || empty($fullname) || empty($phone) || empty($restaurant_name)) {
        $error_message = "กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน";
    } else {
        // ตรวจสอบชื่อผู้ใช้ซ้ำในตาราง owner (ตารางเดียวกับที่ login.php ใช้จริง)
        $check_stmt = $conn->prepare("SELECT owner_id FROM owner WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            $error_message = "ชื่อผู้ใช้งานนี้มีในระบบแล้ว";
        } else {
            $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO owner (username, password, name, phone, restaurant_name, address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $username, $hashed_password, $fullname, $phone, $restaurant_name, $address);

            if ($stmt->execute()) {
                $new_owner_id = $conn->insert_id;

                // สมัครเสร็จแล้ว ล็อกอินให้เลยอัตโนมัติ (ระบบร้านเดียว ไม่ต้องรออนุมัติ)
                session_regenerate_id(true);
                $_SESSION['role'] = 'owner';
                $_SESSION['owner_id'] = $new_owner_id;

                header("Location: dashboard.php");
                exit;
            } else {
                $error_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง";
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
    <title>ลงทะเบียนเปิดร้าน - Raauaibaan</title>
    <link href="https://fonts.googleapis.com/css2?family=Mitr&family=Sarabun&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/register-owner.css">
</head>
<body>
    <div class="container">
        <div class="reg-card">
            <div class="text-center mb-3">
                <i class="bi bi-shop-window" style="font-size: 3rem; color: #795548;"></i>
            </div>
            <h2>เปิดร้านอาหารกับเรา ☕</h2>
            <p class="text-center text-muted mb-4 small">สมัครแล้วเข้าสู่ระบบจัดการร้านได้ทันที</p>

            <?php if($error_message): ?>
                <div class="alert alert-danger text-center rounded-4 border-0 mb-4 shadow-sm"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>ชื่อผู้ใช้งาน (Username)</label>
                        <input type="text" name="username" class="form-control" placeholder="ตั้งชื่อผู้ใช้" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>รหัสผ่าน (Password)</label>
                        <div class="position-relative">
                            <input type="password" name="password" id="password" class="form-control pe-5" placeholder="ระบุรหัสผ่าน" required>
                            <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-muted p-0 me-3 password-toggle-btn" style="z-index: 5;" tabindex="-1" onclick="togglePasswordField('password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label>ชื่อ-นามสกุลเจ้าของร้าน</label>
                    <input type="text" name="fullname" class="form-control" placeholder="ระบุชื่อจริงของคุณ" required>
                </div>
                <div class="mb-3">
                    <label>เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" class="form-control" placeholder="08x-xxxxxxx" required>
                </div>
                <hr class="my-4 opacity-25">
                <div class="mb-3">
                    <label>ชื่อร้านอาหารของคุณ</label>
                    <input type="text" name="restaurant_name" class="form-control" placeholder="ตั้งชื่อร้านให้น่าจำ" required>
                </div>
                <div class="mb-4">
                    <label>ที่อยู่ร้าน / คำอธิบายสั้นๆ</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="ที่ตั้งร้านของคุณ..." required></textarea>
                </div>
                <button type="submit" class="btn-brown shadow">ลงทะเบียนเข้าสู่ระบบ</button>

                <div class="text-center mt-3">
                    <small class="text-muted">มีบัญชีอยู่แล้ว? <a href="../login.php" class="text-decoration-none fw-bold" style="color:#795548;">เข้าสู่ระบบ</a></small>
                </div>
            </form>
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
