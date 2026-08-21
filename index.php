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

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/index.css">
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
                เสิร์ฟความอร่อย สดใหม่ ทำด้วยใจทุกเมนู <br>เลือกได้เลยว่าทานที่ร้านหรือสั่งกลับบ้าน
            </p>
            <div class="d-grid gap-3" style="max-width: 340px; margin: 0 auto;">
                <a href="qr_table/menu_dinein.php" class="btn-order">
                    <i class="bi bi-shop me-2"></i> ทานที่ร้าน (สแกน QR ที่โต๊ะ)
                </a>
                <a href="menu.php" class="btn-order btn-order-outline">
                    <i class="bi bi-bag-check me-2"></i> สั่งกลับบ้าน
                </a>
            </div>
        <?php endif; ?>

        <a href="login.php" class="owner-link">
            <i class="bi bi-gear-fill me-1"></i> สำหรับเจ้าของร้าน (Owner Login)
        </a>
    </div>

</body>
</html>