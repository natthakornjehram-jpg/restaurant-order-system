<?php
$nav_table_no = $_SESSION['table_number'] ?? 'ไม่ระบุ';
$nav_order_type = $_SESSION['order_type'] ?? null;
$nav_order_type_label = $nav_order_type === 'takeaway' ? 'กลับบ้าน' : ($nav_order_type === 'dine_in' ? 'ทานที่ร้าน' : null);
?>

<nav class="navbar sticky-top shadow-sm" style="background-color: var(--cafe-dark);">
    <div class="container d-flex justify-content-between align-items-center py-1">

        <div class="d-flex align-items-center">
            <a href="../qr_table/menu_dinein.php?table=<?= urlencode($nav_table_no) ?>" class="btn btn-outline-light btn-sm rounded-circle shadow-sm me-2 d-flex align-items-center justify-content-center" title="กลับหน้าหลัก" style="width: 38px; height: 38px;">
                <i class="bi bi-arrow-left"></i>
            </a>
            <a class="navbar-brand fw-bold d-flex align-items-center text-white m-0" href="../qr_table/menu_dinein.php?table=<?= urlencode($nav_table_no) ?>">
                <i class="bi bi-shop me-2" style="color: var(--cafe-gold);"></i>
                <span class="fs-5 text-truncate" style="max-width: 150px;">
                    <?= htmlspecialchars($store['restaurant_name'] ?? 'ร้านของเรา') ?>
                </span>
            </a>
        </div>

        <div class="d-flex align-items-center gap-2">

            <span class="badge rounded-pill px-3 py-2 fw-bold shadow-sm" style="background-color: var(--cafe-gold); color: var(--cafe-dark); border: 2px solid #fff;">
                โต๊ะ <?= htmlspecialchars($nav_table_no) ?><?= $nav_order_type_label ? ' · ' . $nav_order_type_label : '' ?>
            </span>

            <a href="../qr_table/my_bill.php" class="btn btn-outline-light btn-sm rounded-circle shadow-sm d-flex align-items-center justify-content-center" title="การดำเนินการ" style="width: 38px; height: 38px;">
                <i class="bi bi-receipt"></i>
            </a>

        </div>
    </div>
</nav>