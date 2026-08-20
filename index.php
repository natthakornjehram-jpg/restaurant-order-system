<?php
// index.php (หน้าแรกสุดของเว็บไซต์)
session_start();
require_once 'includes/db.php';

// ดึงข้อมูลร้านจากตาราง owner
$store_res = $conn->query("SELECT * FROM owner LIMIT 1");
$store = $store_res->fetch_assoc();

$restaurant_name = !empty($store['restaurant_name']) ? $store['restaurant_name'] : 'ยินดีต้อนรับสู่ร้านของเรา';

// ดึงสถานะเปิด-ปิดจากฐานข้อมูล
$is_shop_open = $store['is_shop_open'] ?? 0;
$is_online_open = $store['is_online_open'] ?? 0;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($restaurant_name) ?> - ยินดีต้อนรับ</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Mitr:wght@400;500;600&family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Sarabun', sans-serif;
            background-color: #3e2723;
        }
        
        h1, h2, h3, .font-mitr {
            font-family: 'Mitr', sans-serif;
        }

        .hero-section {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, rgba(62, 39, 35, 0.9), rgba(121, 85, 72, 0.8));
            color: white;
            text-align: center;
            padding: 20px;
            position: relative;
        }

        .store-icon {
            font-size: 5rem;
            color: #ffb300;
            margin-bottom: 20px;
            text-shadow: 0 4px 15px rgba(255, 179, 0, 0.3);
        }

        .btn-order {
            background-color: #ff9800;
            color: white;
            font-size: 1.3rem;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            margin-top: 20px;
            box-shadow: 0 8px 25px rgba(255, 152, 0, 0.4);
            border: 2px solid transparent;
        }
        
        .btn-order:hover {
            background-color: #e68a00;
            color: white;
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(255, 152, 0, 0.5);
        }

        .owner-link {
            position: absolute;
            bottom: 30px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
            text-decoration: none;
            transition: 0.3s;
        }
        
        .owner-link:hover {
            color: white;
            text-decoration: underline;
        }

        .status-alert {
            max-width: 400px;
            width: 100%;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>

    <div class="hero-section">
        <i class="bi bi-shop store-icon"></i>
        <h1 class="display-3 font-mitr fw-bold mb-3"><?= htmlspecialchars($restaurant_name) ?></h1>
        
        <?php if ($is_shop_open == 0): ?>
            <div class="alert alert-danger status-alert mt-3 border-0 text-white" style="background-color: rgba(220, 53, 69, 0.85);">
                <h5 class="font-mitr m-0"><i class="bi bi-door-closed-fill me-2"></i> วันนี้ร้านปิดชั่วคราวครับ</h5>
                <small class="d-block mt-2 opacity-75">ไว้มาอุดหนุนใหม่โอกาสหน้านะครับ ขออภัยในความไม่สะดวก 🙏</small>
            </div>
            
        <?php elseif ($is_online_open == 0): ?>
            <div class="alert alert-warning status-alert mt-3 border-0" style="background-color: rgba(255, 193, 7, 0.9); color: #3e2723;">
                <h5 class="font-mitr m-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> งดรับออเดอร์ออนไลน์ชั่วคราว</h5>
                <small class="d-block mt-2">ขณะนี้คิวหน้าร้านเต็ม หรือติดธุระด่วน รบกวนสั่งใหม่ภายหลังนะครับ</small>
            </div>

        <?php else: ?>
            <p class="lead mb-4 fw-light" style="max-width: 500px;">
                เสิร์ฟความอร่อย สดใหม่ ทำด้วยใจทุกเมนู <br>สั่งอาหารง่ายๆ ผ่านระบบออนไลน์ได้เลยทันที!
            </p>
            <a href="menu.php" class="btn-order">
                <i class="bi bi-phone-vibrate me-2"></i> สั่งอาหาร / ดูเมนู
            </a>
        <?php endif; ?>

        <a href="login.php" class="owner-link">
            <i class="bi bi-gear-fill me-1"></i> สำหรับเจ้าของร้าน (Owner Login)
        </a>
    </div>

</body>
</html>