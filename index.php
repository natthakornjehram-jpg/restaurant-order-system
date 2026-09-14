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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($restaurant_name) ?> - ยินดีต้อนรับ</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Mitr:wght@400;500;600&family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/index.css">
</head>
<body>

    <div class="hero-section">
        <a href="login.php" class="owner-link">
            <i class="bi bi-gear-fill"></i> เจ้าของร้าน
        </a>

        <div class="store-icon-circle">
            <?php if (!empty($store['logo_url']) && $store['logo_url'] !== 'default_logo.png'): ?>
                <img src="<?= BASE_URL ?>assets/images/logos/<?= htmlspecialchars($store['logo_url']) ?>" alt="logo" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
            <?php else: ?>
                <i class="bi bi-shop store-icon"></i>
            <?php endif; ?>
        </div>
        <h1 class="display-4 font-mitr fw-bold mb-3 hero-title"><?= htmlspecialchars($restaurant_name) ?></h1>

        <?php if ($is_shop_open == 0): ?>
            <div class="alert alert-danger status-alert mt-3 border-0 text-white" style="background-color: rgba(220, 53, 69, 0.85);">
                <h5 class="font-mitr m-0"><i class="bi bi-door-closed-fill me-2"></i> ขณะนี้ร้านปิดให้บริการครับ</h5>
                <small class="d-block mt-2 opacity-75">ไว้มาอุดหนุนใหม่โอกาสหน้านะครับ ขออภัยในความไม่สะดวก 🙏</small>
            </div>
        <?php else: ?>
            <p class="lead mb-3 fw-light hero-lead" style="max-width: 500px;">
                ขอบคุณที่รับบริการร้านของเรา! <br> โต๊ะนี้ได้มีการปิดบิลเรียบร้อยแล้ว
            </p>
            <div class="alert alert-warning status-alert mt-3 border-0 text-center">
                <i class="bi bi-qr-code-scan me-2 fs-4"></i>
                <span class="fw-bold">หากต้องการสั่งเพิ่ม โปรดสแกน QR Code ใหม่<br>เพื่อเริ่มการสั่งอาหารอีกครั้งครับ</span>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>