<?php
// login.php
session_start();
require_once 'includes/db.php';
require_once 'includes/csrf.php';

// --- 1. เช็ก Cookie ก่อนเลย (สำหรับระบบ "จดจำฉัน 12 ชม.") ---
// Do not restore a login from role/id cookies: they can be forged.

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !csrf_verify($_POST['csrf_token'] ?? '')) {
    $error = "คำขอไม่ถูกต้อง (CSRF token ไม่ถูกต้อง) กรุณาลองใหม่อีกครั้ง";
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false; // เช็กว่าติ๊กถูกช่องจดจำฉันไหม
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // --- กันสุ่มรหัสผ่าน (brute-force): เช็กว่า username นี้กรอกผิดครบ 5 ครั้งภายใน 15 นาทีล่าสุดหรือยัง ---
    $fail_stmt = $conn->prepare("SELECT COUNT(*) AS fails, MAX(created_at) AS last_fail FROM login_attempts WHERE username = ? AND success = 0 AND created_at >= (NOW() - INTERVAL 15 MINUTE)");
    $fail_stmt->bind_param("s", $username);
    $fail_stmt->execute();
    $fail_row = $fail_stmt->get_result()->fetch_assoc();
    $recent_fails = intval($fail_row['fails']);

    if ($recent_fails >= 5) {
        $wait_minutes = max(1, (int) ceil((900 - (time() - strtotime($fail_row['last_fail']))) / 60));
        $error = "เข้าสู่ระบบผิดพลาดหลายครั้งเกินไป ระบบระงับการเข้าสู่ระบบชั่วคราว กรุณารออีกประมาณ $wait_minutes นาทีแล้วลองใหม่ครับ";
    } else {
        $login_ok = false;
        $count_as_fail = false; // นับเป็นความล้มเหลวสำหรับกันสุ่มรหัส เฉพาะกรอก username/password ผิดจริงเท่านั้น

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
                    $login_ok = true;

                    if ($legacy_password_matches) {
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $upgrade = $conn->prepare("UPDATE owner SET password = ? WHERE owner_id = ?");
                        $upgrade->bind_param('si', $new_hash, $user_data['owner_id']);
                        $upgrade->execute();
                    }

                    $log_stmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, 1)");
                    $log_stmt->bind_param("ss", $username, $client_ip);
                    $log_stmt->execute();

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
                $count_as_fail = true;
            }
        } else {
            $error = "ไม่พบชื่อผู้ใช้งานนี้ในระบบ";
            $count_as_fail = true;
        }

        if ($count_as_fail) {
            $log_stmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, 0)");
            $log_stmt->bind_param("ss", $username, $client_ip);
            $log_stmt->execute();

            $remaining = 5 - ($recent_fails + 1);
            if ($remaining > 0 && $remaining <= 2) {
                $error .= " (เหลืออีก $remaining ครั้ง ก่อนถูกระงับการเข้าสู่ระบบชั่วคราว 15 นาที)";
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
    <title>เข้าสู่ระบบ - ระบบจัดการร้านอาหาร</title>
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
        .login-card { 
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
        .form-control { border-radius: 15px; padding: 12px 15px; border: 1px solid #d7ccc8; background-color: #fcfaf9; font-size: 1rem; }
        .form-control:focus { border-color: #795548; box-shadow: none; background: #fff; }
        label { font-weight: bold; font-size: 0.9rem; color: #5d4037; margin-bottom: 5px; margin-left: 5px; }

        @media (max-width: 576px) {
            body { padding: 16px 12px; }
            .login-card { padding: 25px 20px; border-radius: 22px; }
            h2 { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card mx-auto">
            <div class="text-center mb-3">
                <i class="bi bi-person-circle" style="font-size: 3.5rem; color: #795548;"></i>
            </div>
            <h2>เข้าสู่ระบบจัดการร้าน</h2>
            <p class="text-center text-muted mb-4 small">สำหรับเจ้าของร้านและผู้ดูแลระบบ</p>
            
            <?php if($error): ?>
                <div class="alert alert-danger text-center rounded-4 border-0 mb-4 shadow-sm">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success text-center rounded-4 border-0 mb-4 shadow-sm">
                    <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($_SESSION['success_msg']) ?>
                </div>
                <?php unset($_SESSION['success_msg']); ?>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="mb-3">
                    <label>ชื่อผู้ใช้งาน (Username)</label>
                    <input type="text" name="username" class="form-control" placeholder="กรอก Username เจ้าของร้าน" required>
                </div>
                <div class="mb-3">
                    <label>รหัสผ่าน (Password)</label>
                    <input type="password" name="password" class="form-control" placeholder="กรอกรหัสผ่าน" required>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-4 px-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="rememberMe" style="cursor: pointer;">
                        <label class="form-check-label small text-muted" for="rememberMe" style="cursor: pointer;">
                            จดจำฉัน
                        </label>
                    </div>
                    <a href="forgot_password.php" class="small text-decoration-none" style="color: #795548;">ลืมรหัสผ่าน?</a>
                </div>

                <button type="submit" class="btn-brown shadow">เข้าสู่ระบบจัดการร้าน</button>
            </form>
        </div>
    </div>
</body>
</html>
