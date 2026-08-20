<?php
// member/profile.php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['customer_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login_customer.php");
    exit;
}

$user_id = $_SESSION['customer_id'];
$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username']); // รับค่า Username ใหม่
    $new_name = trim($_POST['name']);
    $new_phone = trim($_POST['phone']);
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. ดึงข้อมูลปัจจุบันมาตรวจสอบ
    $stmt = $conn->prepare("SELECT password, username FROM customer WHERE customer_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();

    // 2. เช็กว่า Username ใหม่ไปซ้ำกับคนอื่นไหม (ถ้ามีการเปลี่ยน)
    if ($new_username !== $user_data['username']) {
        $check_user = $conn->prepare("SELECT customer_id FROM customer WHERE username = ? AND customer_id != ?");
        $check_user->bind_param("si", $new_username, $user_id);
        $check_user->execute();
        if ($check_user->get_result()->num_rows > 0) {
            $error_msg = "Username นี้มีผู้อื่นใช้แล้ว กรุณาใช้ชื่ออื่น";
        }
    }

    // 3. ตรวจสอบรหัสผ่านเดิมก่อนบันทึก
    if (empty($error_msg)) {
        if (!password_verify($old_password, $user_data['password'])) {
            $error_msg = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
        } else {
            if (!empty($new_password)) {
                // --- กรณีเปลี่ยนรหัสผ่าน (เด้งออกไปล็อกอินใหม่) ---
                if ($new_password !== $confirm_password) {
                    $error_msg = "รหัสผ่านใหม่ไม่ตรงกัน";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $upd = $conn->prepare("UPDATE customer SET username = ?, name = ?, phone = ?, password = ? WHERE customer_id = ?");
                    $upd->bind_param("ssssi", $new_username, $new_name, $new_phone, $hashed_password, $user_id);
                    if ($upd->execute()) {
                        session_destroy();
                        echo "<script>alert('เปลี่ยนข้อมูลและรหัสผ่านสำเร็จ กรุณาเข้าสู่ระบบใหม่'); window.location.href = '../login_customer.php';</script>";
                        exit;
                    }
                }
            } else {
                // --- กรณีอัปเดตแค่ข้อมูลทั่วไป (รวม Username) ---
                $upd = $conn->prepare("UPDATE customer SET username = ?, name = ?, phone = ? WHERE customer_id = ?");
                $upd->bind_param("sssi", $new_username, $new_name, $new_phone, $user_id);
                if ($upd->execute()) {
                    $_SESSION['customer_name'] = $new_name;
                    $success_msg = "บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว";
                }
            }
        }
    }
}

// ดึงข้อมูลล่าสุดมาโชว์
$stmt = $conn->prepare("SELECT * FROM customer WHERE customer_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

include '../includes/header_customer.php'; 
include '../includes/nav_customer.php'; 
?>

<style>
    :root { --cafe-brown: #795548; --cafe-dark: #3e2723; }
    body { background-color: #fdfaf5; font-family: 'Sarabun', sans-serif; }
    .profile-header { background: linear-gradient(135deg, #3e2723 0%, #795548 100%); border-radius: 0 0 40px 40px; padding: 50px 20px; color: white; text-align: center; }
    .profile-card { background: white; border-radius: 30px; padding: 35px; box-shadow: 0 15px 35px rgba(62,39,35,0.1); border: none; margin-top: -40px; }
    .btn-save { background: var(--cafe-brown); color: white; border-radius: 50px; padding: 12px; border: none; font-weight: bold; transition: 0.3s; width: 100%; }
    .btn-save:hover { background: var(--cafe-dark); transform: translateY(-2px); }

    .btn-back-custom { 
    background: transparent; 
    color: #888; 
    border: 1px solid #ddd; 
    border-radius: 50px; 
    padding: 12px; 
    font-weight: bold; 
    transition: 0.3s; 
    width: 100%;
    }
    
    .btn-back-custom:hover { 
        background: #f8f9fa; 
        color: #333; 
        border-color: #bbb;
    }
</style>

<div class="profile-header">
    <div class="mb-2"><i class="bi bi-person-circle" style="font-size: 4rem;"></i></div>
    <h3 class="fw-bold mb-0"><?= htmlspecialchars($user['name']) ?></h3>
    <p class="opacity-75 small">Username: <?= htmlspecialchars($user['username']) ?></p>
</div>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="profile-card">
                <?php if($success_msg): ?> <div class="alert alert-success border-0 rounded-4 text-center mb-4 small"><?= $success_msg ?></div> <?php endif; ?>
                <?php if($error_msg): ?> <div class="alert alert-danger border-0 rounded-4 text-center mb-4 small"><?= $error_msg ?></div> <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">ชื่อผู้ใช้งาน (Username)</label>
                        <input type="text" name="username" class="form-control rounded-3" value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">ชื่อ-นามสกุล</label>
                        <input type="text" name="name" class="form-control rounded-3" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">เบอร์โทรศัพท์</label>
                        <input type="text" name="phone" class="form-control rounded-3" value="<?= htmlspecialchars($user['phone']) ?>" required>
                    </div>

                    <div class="p-3 rounded-4 mb-4" style="background-color: #fff8f1; border: 1px dashed #ffe0b2;">
                        <h6 class="fw-bold small mb-3"><i class="bi bi-key me-1"></i> เปลี่ยนรหัสผ่านใหม่ (ไม่เปลี่ยนให้เว้นว่าง)</h6>
                        <input type="password" name="new_password" class="form-control form-control-sm mb-2" placeholder="รหัสผ่านใหม่">
                        <input type="password" name="confirm_password" class="form-control form-control-sm" placeholder="ยืนยันรหัสผ่านใหม่">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-danger">รหัสผ่านปัจจุบัน (เพื่อยืนยันการบันทึก) *</label>
                        <input type="password" name="old_password" class="form-control border-danger rounded-3" placeholder="กรอกรหัสผ่านปัจจุบัน" required>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <button type="button" class="btn-back-custom" onclick="window.location.href='../menu.php';">
                                ย้อนกลับ
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="submit" name="update_profile" class="btn-save shadow-sm">
                                บันทึกการแก้ไข
                            </button>
                        </div>
                    </div>
                    
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer_customer.php'; ?>