<?php
// login_customer.php (วางไว้ข้างนอกโฟลเดอร์หลัก)
session_start();
require_once 'includes/db.php'; // 💡 แก้ path ให้ดึงจากโฟลเดอร์ includes โดยตรง

// ถ้าล็อกอินอยู่แล้ว ให้เด้งกลับไปหน้าตะกร้าหรือหน้าแรก
if (isset($_SESSION['customer_id'])) {
        // ดึงค่าหน้าที่ลูกค้าอยากไป
        $target_page = $_SESSION['redirect_to'] ?? '';
        // ✅ Whitelist หน้าที่อนุญาตให้เด้งกลับไปได้ (ป้องกัน Open Redirect)
        $allowed_pages = ['menu.php', 'cart.php', 'member/history.php', 'member/checkout.php'];
        // ถ้าหน้าที่ขอมา อยู่ในรายชื่อที่อนุญาต ให้ไปได้ ถ้าไม่ใช่ให้บังคับไป menu.php
        $redirect = in_array($target_page, $allowed_pages) ? $target_page : 'menu.php';
        // เคลียร์ค่าทิ้งหลังใช้งานเสร็จ
        unset($_SESSION['redirect_to']);
        // ทำการเด้งไปหน้าที่ตรวจแล้ว
        header("Location: " . $redirect);
        exit;
}

$error = "";
$success = $_SESSION['success_msg'] ?? ""; // รับข้อความสำเร็จ
unset($_SESSION['success_msg']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // เช็กเฉพาะตาราง customer
    $stmt = $conn->prepare("SELECT customer_id, password, is_active, name FROM customer WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user_data = $res->fetch_assoc();

        if (password_verify($password, $user_data['password']) || hash_equals($user_data['password'], $password)) {
            if ($user_data['is_active'] == 0) {
                $error = "❌ บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อร้านค้าครับ";
            } else {
                session_regenerate_id(true);
                $_SESSION['role'] = 'customer';
                $_SESSION['customer_id'] = $user_data['customer_id'];
                // ✅ จุดที่ 2: ย้ายมาอยู่ตรงนี้ หลังดึง $user_data ได้แล้ว
                $_SESSION['customer_name'] = $user_data['name'];
                
                $target_page = $_SESSION['redirect_to'] ?? '';
                $allowed_pages = ['menu.php', 'cart.php', 'history.php', 'checkout.php'];
                $redirect = in_array($target_page, $allowed_pages) ? $target_page : 'menu.php';
                unset($_SESSION['redirect_to']);
                header("Location: " . $redirect);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบลูกค้า - RANNAIBAAN</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --theme-color: #d35400; /* สีส้มน้ำตาล (Burnt Orange) */
            --theme-hover: #a04000; /* สีส้มน้ำตาลเข้มตอนเอาเมาส์ชี้ */
            --theme-light: #fef5ec; /* สีพื้นหลังไอคอนอ่อนๆ */
        }
        
        body { 
            background-color: #fcfaf8; 
            font-family: 'Sarabun', sans-serif; 
            min-height: 100vh; /* ใช้ min-height เพื่อให้ยืดหยุ่น */
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 40px 15px; /* ดันไม่ให้ชิดขอบบน-ล่างเกินไป */
        }
        
        .login-card { 
            width: 100%; 
            max-width: 420px; 
            background: white; 
            padding: 40px 30px; 
            border-radius: 25px; 
            box-shadow: 0 20px 40px rgba(211, 84, 0, 0.08); /* เงาสีส้มน้ำตาลบางๆ ให้ดูสวยและลอยขึ้น */
            border: 1px solid #f9f1ea;
        }
        
        .form-control { 
            border-radius: 12px; 
            padding: 12px; 
            border: 1px solid #e2e8f0; 
            background-color: #fcfcfc; 
        }
        
        .form-control:focus { 
            border-color: var(--theme-color); 
            box-shadow: 0 0 0 3px rgba(211, 84, 0, 0.15); 
            background: #fff; 
        }
        
        .btn-theme { 
            background: var(--theme-color); 
            color: white; 
            border: none; 
            border-radius: 50px; 
            padding: 12px; 
            font-weight: bold; 
            width: 100%; 
            transition: 0.3s; 
        }
        
        .btn-theme:hover { 
            background: var(--theme-hover); 
            color: white; 
            transform: translateY(-3px); /* ปุ่มลอยขึ้นตอนเมาส์ชี้ */
            box-shadow: 0 8px 20px rgba(211, 84, 0, 0.2);
        }

        .text-theme { color: var(--theme-color) !important; }
        .bg-theme-light { background-color: var(--theme-light) !important; }
    </style>
</head>
<body>
    <div class="container px-3">
        <div class="login-card mx-auto text-center">
            
            <div class="mb-4">
                <div class="bg-theme-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                    <i class="bi bi-person-fill text-theme" style="font-size: 2.5rem;"></i>
                </div>
            </div>
            
            <h4 class="fw-bold mb-1" style="color: #4a3b32;">เข้าสู่ระบบเพื่อสั่งอาหาร</h4>
            <p class="text-muted small mb-4">ยินดีต้อนรับกลับมา รสชาติความอร่อยรอคุณอยู่!</p>

            <?php if($error): ?>
                <div class="alert alert-danger small rounded-3 border-0 py-2 mb-4 shadow-sm">
                    <i class="bi bi-exclamation-circle-fill me-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success small rounded-3 border-0 py-2 mb-4 shadow-sm">
                    <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="text-start">
                <div class="mb-3">
                    <label class="small fw-bold text-muted mb-1 ms-1">ชื่อผู้ใช้งาน</label>
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                </div>
                <div class="mb-2">
                    <label class="small fw-bold text-muted mb-1 ms-1">รหัสผ่าน</label>
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>
                
                <div class="text-end mb-4">
                    <a href="forgot_password.php" class="small fw-bold text-theme text-decoration-none">ลืมรหัสผ่าน?</a>
                </div>
                
                <button type="submit" class="btn btn-theme shadow-sm mb-4">ล็อกอินเข้าสู่ระบบ</button>
            </form>

            <div class="text-center small border-top pt-3">
                <span class="text-muted">ยังไม่มีบัญชีใช่ไหม?</span> 
                <a href="register_customer.php" class="fw-bold text-theme text-decoration-none">สมัครสมาชิกใหม่</a>
            </div>
            
            <div class="mt-4">
                <a href="menu.php" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> กลับไปหน้าเมนู</a>
            </div>
        </div>
    </div>
</body>
</html>