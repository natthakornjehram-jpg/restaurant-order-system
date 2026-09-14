<?php
$nav_table_no = $_SESSION['table_number'] ?? 'ไม่ระบุ';
$nav_order_type = $_SESSION['order_type'] ?? null;
$current_page = basename($_SERVER['PHP_SELF']);
$nav_cart_qty = $nav_cart_qty ?? 0; // ตั้งค่าจาก menu_dinein.php ก่อน include ไฟล์นี้
$nav_active_tab = $nav_active_tab ?? 'menu';
$nav_is_shop_open = isset($store['is_shop_open']) ? intval($store['is_shop_open']) : 1;
?>

<nav class="navbar sticky-top shadow-sm" style="background-color: var(--cafe-dark);">
    <div class="container d-flex justify-content-between align-items-center py-1">

        <div class="d-flex align-items-center">
            <?php if ($current_page !== 'menu_dinein.php'): ?>
            <a href="../qr_table/menu_dinein.php?table=<?= urlencode($nav_table_no) ?>" class="btn btn-outline-light btn-sm rounded-circle shadow-sm me-2 d-flex align-items-center justify-content-center" title="กลับหน้าหลัก" style="width: 38px; height: 38px;">
                <i class="bi bi-arrow-left"></i>
            </a>
            <?php endif; ?>
            <a class="navbar-brand fw-bold d-flex align-items-center text-white m-0" href="../qr_table/menu_dinein.php?table=<?= urlencode($nav_table_no) ?>">
                <?php if (!empty($store['logo_url']) && $store['logo_url'] !== 'default_logo.png'): ?>
                    <img src="<?= BASE_URL ?>assets/images/logos/<?= htmlspecialchars($store['logo_url']) ?>" alt="logo" style="width:24px;height:24px;object-fit:cover;border-radius:50%;" class="me-2">
                <?php else: ?>
                    <i class="bi bi-shop me-2" style="color: var(--cafe-gold);"></i>
                <?php endif; ?>
                <span class="fs-6 text-truncate" style="max-width: 150px;">
                    <?= htmlspecialchars($store['restaurant_name'] ?? 'ร้านของเรา') ?>
                </span>
            </a>
        </div>

        <div class="d-flex align-items-center gap-2">

            <span class="badge rounded-pill px-3 py-2 fw-bold shadow-sm dinein-status-badge <?= $nav_is_shop_open ? 'is-open' : 'is-closed' ?>">
                <span class="status-dot"></span> <?= $nav_is_shop_open ? 'เปิดรับออเดอร์' : 'ปิดรับออเดอร์' ?>
            </span>

            <a href="../qr_table/my_bill.php" class="btn btn-outline-light btn-sm rounded-circle shadow-sm d-flex align-items-center justify-content-center" title="ติดตามสถานะ" style="width: 38px; height: 38px;">
                <i class="bi bi-receipt"></i>
            </a>

        </div>
    </div>

    <?php if ($current_page === 'menu_dinein.php'): ?>
    <div class="container d-flex justify-content-between align-items-center dinein-subrow py-2">
        <span class="badge rounded-pill px-3 py-2 fw-bold shadow-sm" style="background-color: var(--cafe-gold); color: var(--cafe-dark); border: 2px solid #fff;">
            <?php if ($nav_order_type === 'takeaway'): ?>
                <i class="bi bi-bag-fill"></i> สั่งกลับบ้าน
            <?php else: ?>
                <i class="bi bi-shop"></i> โต๊ะ <?= htmlspecialchars($nav_table_no) ?>
            <?php endif; ?>
        </span>

        <div class="dinein-tabs" role="tablist">
            <button type="button" class="dinein-tab <?= $nav_active_tab === 'menu' ? 'active' : '' ?>" onclick="dineinSwitchTab('menu')">
                <i class="bi bi-egg-fried"></i> เมนูอาหาร
            </button>
            <button type="button" class="dinein-tab <?= $nav_active_tab === 'cart' ? 'active' : '' ?>" onclick="dineinSwitchTab('cart')">
                <i class="bi bi-cart3"></i> รายการที่สั่ง<?= $nav_cart_qty > 0 ? ' (' . $nav_cart_qty . ')' : '' ?>
            </button>
        </div>
    </div>
    <?php endif; ?>
</nav>