<?php
// login.php
session_start();
require_once 'includes/db.php'; 

// --- 1. เช็ก Cookie ก่อนเลย (สำหรับระบบ "จดจำฉัน 12 ชม.") ---
// Do not restore a login from role/id cookies: they can be forged.

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false; // เช็กว่าติ๊กถูกช่องจดจำฉันไหม

    // --- ระบบร้านเดี่ยว: มีแต่บัญชีเจ้าของร้านเท่านั้น (ลูกค้าไม่ต้องสมัคร/ล็อกอิน) ---
    $stmt_owner = $conn->prepare("SELECT owner_id, password, is_active FROM owner WHERE username = ? LIMIT 1");
    $stmt_owner->bind_param("s", $username);
    $stmt_owner->execute();
    $res_owner = $stmt_owner->get_result();

    if ($res_owner->num_rows > 0) {
        $user_data = $res_owner->fetch_assoc();
        $stored_password = $user_data['password'];
        $legacy_password_matches = !password_get_info($stored_password)['algo']
            && hash_equals($stored_password, $password);

        if (password_verify($password, $stored_password) || $legacy_password_matches) {

            if ($user_data['is_active'] == 0) {
                $error = "⌛ บัญชีเจ้าของร้านของคุณถูกระงับ หรืออยู่ระหว่างรออนุมัติครับ";
            } else {
                // ✅ เข้าสู่ระบบสำเร็จ
                if ($legacy_password_matches) {
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $upgrade = $conn->prepare("UPDATE owner SET password = ? WHERE owner_id = ?");
                    $upgrade->bind_param('si', $new_hash, $user_data['owner_id']);
                    $upgrade->execute();
                }

                session_regenerate_id(true);
                $_SESSION['role'] = 'owner';
                $_SESSION['owner_id'] = $user_data['owner_id'];

                // ถ้าติ๊ก "จดจำฉัน 12 ชม." ให้สร้าง Cookie อายุ 12 ชั่วโมง (43,200 วินาที)
                // The checkbox is retained in the UI but no longer creates an unsafe login cookie.

                header("Location: owner/dashboard.php");
                exit;
            }
        } else {
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ไม่พบชื่อผู้ใช้งานนี้ในระบบ";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบ - ระบบจัดการร้านอาหาร</title>
    <link href="https://fonts.googleapis.com/css2?family=Mitr&family=Sarabun&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #fdfaf5; font-family: 'Sarabun', sans-serif; color: #3e2723; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { width: 100%; max-width: 450px; background: white; padding: 40px; border-radius: 35px; box-shadow: 0 15px 35px rgba(62,39,35,0.1); border: 1px solid #efebe9; }
        .btn-brown { background: #795548; color: white; border-radius: 50px; padding: 12px; border: none; width: 100%; font-weight: bold; transition: 0.3s; }
        .btn-brown:hover { background: #3e2723; transform: translateY(-2px); color: white; }
        h2 { font-family: 'Mitr', sans-serif; color: #3e2723; text-align: center; }
        .form-control { border-radius: 15px; padding: 12px; border: 1px solid #d7ccc8; background-color: #fcfaf9; }
        .form-control:focus { border-color: #795548; box-shadow: none; background: #fff; }
        label { font-weight: bold; font-size: 0.9rem; color: #5d4037; margin-bottom: 5px; margin-left: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card mx-auto">
            <div class="text-center mb-3">
                <i class="bi bi-person-circle" style="font-size: 3.5rem; color: #795548;"></i>
            </div>
            <h2>เข้าสู่ระบบ</h2>
            <p class="text-center text-muted mb-4 small">ยินดีต้อนรับ! กรุณาล็อกอินเพื่อดำเนินการต่อ</p>
            
            <?php if($error): ?>
                <div class="alert alert-danger text-center rounded-4 border-0 mb-4 shadow-sm">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success text-center rounded-4 border-0 mb-4 shadow-sm">
                    <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($_SESSION['success_msg']) ?>
                </div>
                <?php unset($_SESSION['success_msg']); ?>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label>ชื่อผู้ใช้งาน (Username)</label>
                    <input type="text" name="username" class="form-control" placeholder="กรอก Username ของคุณ" required>
                </div>
                <div class="mb-3">
                    <label>รหัสผ่าน (Password)</label>
                    <input type="password" name="password" class="form-control" placeholder="กรอกรหัสผ่าน" required>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-4 px-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="rememberMe" style="cursor: pointer;">
                        <label class="form-check-label small text-muted" for="rememberMe" style="cursor: pointer;">
                            จดจำฉัน 12 ชม.
                        </label>
                    </div>
                    <a href="forgot_password.php" class="small text-decoration-none fw-bold" style="color: #795548;">ลืมรหัสผ่าน?</a>
                </div>
                
                <button type="submit" class="btn-brown shadow">ล็อกอินเข้าสู่ระบบ</button>
                
                <div class="text-center mt-4">
                    <hr class="opacity-25 my-3">
                    <small class="text-muted d-block mb-2">ต้องการเปิดร้านอาหารกับเรา?</small>
                    <a href="owner/register_owner.php" class="btn btn-sm btn-outline-secondary rounded-pill px-4">สมัครเปิดร้านใหม่</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
