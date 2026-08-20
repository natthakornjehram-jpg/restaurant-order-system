<?php
// member/change_password.php
session_start();
require_once '../includes/db.php';

// 1. เช็กสิทธิ์สมาชิก (ต้องล็อกอินมาแล้ว)
if (!isset($_SESSION['customer_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login_customer.php");
    exit;
}

$user_id = $_SESSION['customer_id'];
$error = "";
$success = "";

// 2. เมื่อมีการกดปุ่มบันทึกการเปลี่ยนแปลง
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $conf_pass = $_POST['confirm_password'];

    // ดึงข้อมูลรหัสผ่านปัจจุบัน (เปลี่ยน users เป็น customer)
    $stmt = $conn->prepare("SELECT password FROM customer WHERE customer_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $error = "ไม่พบข้อมูลผู้ใช้งาน";
    } 
    // เช็กว่ารหัสผ่านเดิมถูกต้องไหม (ใช้ password_verify)
    elseif (!password_verify($old_pass, $user['password'])) {
        $error = "รหัสผ่านเดิมไม่ถูกต้องครับ";
    } 
    // เช็กว่ารหัสใหม่ตรงกันไหม
    elseif ($new_pass !== $conf_pass) {
        $error = "ยืนยันรหัสผ่านใหม่ไม่ตรงกันครับ";
    } 
    // เช็กความยาวรหัสผ่าน
    elseif (strlen($new_pass) < 6) {
        $error = "รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษรครับ";
    } 
    else {
        // ✅ ทุกอย่างผ่าน -> อัปเดตรหัสผ่านใหม่เป็น Hash
        $new_hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);
        
        $update_stmt = $conn->prepare("UPDATE customer SET password = ? WHERE customer_id = ?");
        $update_stmt->bind_param("si", $new_hashed_password, $user_id);
        
        if ($update_stmt->execute()) {
            // ✨ เปลี่ยนรหัสผ่านสำเร็จ -> ทำลาย Session และเด้งไปหน้า Login ทันทีตามที่ต้องการ
            session_destroy();
            echo "<script>
                alert('เปลี่ยนรหัสผ่านสำเร็จแล้ว กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
                window.location.href = '../login_customer.php';
            </script>";
            exit;
        } else {
            $error = "เกิดข้อผิดพลาดทางเทคนิค กรุณาลองใหม่ภายหลัง";
        }
    }
}

include '../includes/header_customer.php'; 
include '../includes/nav_customer.php'; 
?>

<style>
    :root { --cafe-brown: #795548; --cafe-dark: #3e2723; }
    body { background-color: #fdfaf5; font-family: 'Sarabun', sans-serif; }
    
    .password-card {
        background: white; border-radius: 30px; border: none;
        box-shadow: 0 15px 35px rgba(62,39,35,0.08);
        padding: 40px; margin-top: 50px; border: 1px solid #efebe9;
    }
    .form-control {
        border-radius: 15px; padding: 12px; border: 1px solid #d7ccc8;
        background: #fcfaf9; margin-bottom: 5px;
    }
    .form-control:focus { border-color: var(--cafe-brown); box-shadow: none; background: #fff; }
    
    .btn-save {
        background: var(--cafe-brown); color: white; border-radius: 50px;
        padding: 12px; border: none; width: 100%; font-weight: bold;
        transition: 0.3s; margin-top: 20px;
    }
    .btn-save:hover { background: var(--cafe-dark); transform: translateY(-2px); color: white; }
    
    .icon-box {
        width: 70px; height: 70px; background: #efebe9; color: var(--cafe-brown);
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px; font-size: 2rem;
    }
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5 mt-5">
            <div class="password-card">
                <div class="text-center">
                    <div class="icon-box">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h4 class="fw-bold mb-1" style="color: var(--cafe-dark);">เปลี่ยนรหัสผ่าน</h4>
                    <p class="text-muted small mb-4">เพื่อความปลอดภัยของบัญชีสมาชิก</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small text-center rounded-4 border-0 mb-4">
                        <i class="bi bi-exclamation-circle me-1"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="small fw-bold mb-1 ms-2" style="color: var(--cafe-brown);">รหัสผ่านเดิม</label>
                        <input type="password" name="old_password" class="form-control" placeholder="ระบุรหัสผ่านปัจจุบัน" required>
                    </div>

                    <hr class="my-4 opacity-10">

                    <div class="mb-3">
                        <label class="small fw-bold mb-1 ms-2" style="color: var(--cafe-brown);">รหัสผ่านใหม่</label>
                        <input type="password" name="new_password" class="form-control" placeholder="ระบุรหัสผ่านใหม่ (6 ตัวขึ้นไป)" required>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold mb-1 ms-2" style="color: var(--cafe-brown);">ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="พิมพ์รหัสผ่านใหม่อีกครั้ง" required>
                    </div>

                    <button type="submit" class="btn-save shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> ยืนยันการเปลี่ยนรหัสผ่าน
                    </button>
                    
                    <div class="text-center mt-4">
                        <a href="../menu.php" class="text-decoration-none text-muted small">
                            <i class="bi bi-arrow-left"></i> กลับสู่หน้าหลัก
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer_customer.php'; ?>