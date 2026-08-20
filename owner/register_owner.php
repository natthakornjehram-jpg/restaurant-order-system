<?php
// owner/register_owner.php
session_start();
require_once '../includes/db.php'; 

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password_input = $_POST['password']; 
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $restaurant_name = trim($_POST['restaurant_name']);
    $address = trim($_POST['address']);

    // 1. ตรวจสอบชื่อซ้ำ
    $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $error_message = "ชื่อผู้ใช้งานนี้มีในระบบแล้ว";
    } else {
        // --- 🌟 ส่วนตัดสินใจ Role ---
        $check_first = $conn->query("SELECT COUNT(*) as total FROM users");
        $row_count = $check_first->fetch_assoc();
        
        if ($row_count['total'] == 0) {
            $role = 'admin'; // คนแรกของทั้งระบบจะเป็น Admin
        } else {
            $role = 'owner'; // คนต่อๆ ไปเป็นเจ้าของร้านปกติ
        }
        // ------------------------------------------

        $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);
        $password_raw = $password_input; 
        
        $conn->begin_transaction();

        try {
            // บันทึกข้อมูลผู้ใช้
            $stmt1 = $conn->prepare("INSERT INTO users (username, password, password_raw, fullname, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt1->bind_param("ssssss", $username, $hashed_password, $password_raw, $fullname, $phone, $role);
            $stmt1->execute();

            $owner_id = $conn->insert_id;

            // สร้างร้านค้าให้ด้วย
            $stmt2 = $conn->prepare("INSERT INTO restaurants (owner_id, restaurant_name, address, is_online_open, status) VALUES (?, ?, ?, 1, 'approved')");
            $stmt2->bind_param("iss", $owner_id, $restaurant_name, $address);
            $stmt2->execute();

            $conn->commit();
            
            if ($role == 'admin') {
                $success_message = "คุณคือผู้ดูแลระบบ (Admin) คนแรก สมัครสำเร็จแล้ว!";
            } else {
                $success_message = "สมัครสมาชิกเจ้าของร้านสำเร็จ!";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ลงทะเบียนเปิดร้าน - Raauaibaan</title>
    <link href="https://fonts.googleapis.com/css2?family=Mitr&family=Sarabun&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #fdfaf5; font-family: 'Sarabun', sans-serif; color: #3e2723; }
        .reg-card { max-width: 550px; margin: 50px auto; background: white; padding: 40px; border-radius: 35px; box-shadow: 0 15px 35px rgba(62,39,35,0.1); border: 1px solid #efebe9; }
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
        <div class="reg-card">
            <div class="text-center mb-3">
                <i class="bi bi-shop-window" style="font-size: 3rem; color: #795548;"></i>
            </div>
            <h2>เปิดร้านอาหารกับเรา ☕</h2>
            <p class="text-center text-muted mb-4 small">สมัครสมาชิกคนแรกของระบบรับสิทธิ์ Admin ทันที</p>
            
            <?php if($success_message): ?>
                <div class="alert alert-success text-center rounded-4 border-0 mb-4 shadow-sm">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= $success_message ?>
                    <br><a href="../login.php" class="fw-bold text-success text-decoration-none">เข้าสู่ระบบได้ที่นี่</a>
                </div>
            <?php endif; ?>

            <?php if($error_message): ?>
                <div class="alert alert-danger text-center rounded-4 border-0 mb-4 shadow-sm"><?= $error_message ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>ชื่อผู้ใช้งาน (Username)</label>
                        <input type="text" name="username" class="form-control" placeholder="ตั้งชื่อผู้ใช้" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>รหัสผ่าน (Password)</label>
                        <input type="password" name="password" class="form-control" placeholder="ระบุรหัสผ่าน" required>
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
</body>
</html>