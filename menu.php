<?php
session_start();
require_once 'includes/db.php';

$_SESSION['order_type'] = 'takeaway';
// ล้างข้อมูลโต๊ะทิ้ง (เผื่อลูกค้าเคยสแกนโต๊ะมาก่อน แล้วกดเข้าหน้าออนไลน์)
unset($_SESSION['table_id']);
unset($_SESSION['table_number']);

// 🏪 ดึงสถานะร้าน
$store_res = $conn->query("SELECT is_online_open, restaurant_name FROM owner LIMIT 1");
$store = $store_res->fetch_assoc();

include 'includes/header_customer.php';
include 'includes/nav_customer.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/menu.css">

<div class="container py-4">
    <div class="member-badge-section d-flex justify-content-between align-items-center mt-5">
        <div>
            <h5 class="fw-bold mb-1">ยินดีต้อนรับครับ ✨</h5>
            <p class="mb-0 small opacity-75">สั่งกลับบ้านง่ายๆ ไม่ต้องสมัครสมาชิก</p>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark rounded-pill px-3 py-2 fw-bold shadow-sm">
                <i class="bi bi-shop text-warning"></i> <?= htmlspecialchars($store['restaurant_name'] ?? 'ร้านของเรา') ?>
            </span>
        </div>
    </div>

    <?php if($store['is_online_open'] == 0): ?>
        <div class="alert alert-warning text-center rounded-4 shadow-sm border-0 py-5">
            <h4 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill text-warning"></i> ร้านงดรับออเดอร์กลับบ้านชั่วคราว</h4>
            <p class="mb-0 text-muted">ขออภัยค่ะ ขณะนี้คิวหน้าร้านเต็ม หรือปิดปรับปรุงระบบ</p>
        </div>
    <?php else: ?>

    <div class="row g-3">
        <?php
        // 🟢 ดึงข้อมูลจากตาราง item ที่ตั้งค่า is_active = 1 (พร้อมขาย)
        $items = $conn->query("SELECT * FROM item WHERE is_active = 1 ORDER BY item_id DESC");
        
        while($m = $items->fetch_assoc()): 
            $m_id = $m['item_id'];
            $price = $m['price'];
            $img_path = !empty($m['image_url']) ? "assets/images/items/" . $m['image_url'] : "assets/images/items/default_food.jpg";
        ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="menu-card h-100 shadow-sm" data-bs-toggle="modal" data-bs-target="#itemModal<?= $m_id ?>">
                    <img src="<?= htmlspecialchars($img_path) ?>" class="w-100" style="height: 140px; object-fit: cover;" onerror="this.src='assets/images/items/default_food.jpg'">
                    <div class="p-3">
                        <div class="fw-bold small text-truncate mb-1"><?= htmlspecialchars($m['name']) ?></div>
                        <div class="d-flex align-items-center gap-1 flex-wrap mb-2">
                            <span class="price-normal">฿<?= number_format($price, 0) ?></span>
                        </div>
                        <button class="btn btn-dark btn-sm w-100 rounded-pill mt-2"><i class="bi bi-plus-circle"></i> สั่งอาหาร</button>
                    </div>
                </div>
            </div>

            <div class="modal fade text-start" id="itemModal<?= $m_id ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <form class="modal-content border-0 rounded-4 shadow" action="member/cart_action.php?action=add" method="POST">
                        <input type="hidden" name="item_id" value="<?= $m_id ?>">
                        
                        <div class="modal-header border-0 pb-0">
                            <h5 class="fw-bold m-0"><?= htmlspecialchars($m['name']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        
                        <div class="modal-body py-3">
                            <img src="<?= htmlspecialchars($img_path) ?>" class="w-100 rounded-4 mb-3" style="height:180px; object-fit:cover;" onerror="this.src='assets/images/items/default_food.jpg'">
                            
                            <?php 
                            // 🟢 ดึงท็อปปิ้งจากตาราง menu_toppings โยงไปหา topping และ topping_categories
                            $tops_stmt = $conn->prepare("SELECT t.*, tc.topping_cat_name 
                                FROM menu_toppings mt 
                                JOIN topping t ON mt.topping_id = t.topping_id 
                                JOIN topping_categories tc ON t.topping_cat_id = tc.topping_cat_id 
                                WHERE mt.item_id = ? AND t.is_active = 1
                                ORDER BY tc.topping_cat_id ASC, t.topping_id ASC");
                            $tops_stmt->bind_param("i", $m_id);
                            $tops_stmt->execute();
                            $tops_query = $tops_stmt->get_result();
                            
                            $current_cat = "";
                            if($tops_query && $tops_query->num_rows > 0): 
                                while($t = $tops_query->fetch_assoc()): 
                                    if ($current_cat != $t['topping_cat_name']): 
                                        $current_cat = $t['topping_cat_name'];
                                        echo "<div class='topping-group-title'>".htmlspecialchars($current_cat)."</div>";
                                    endif;
                            ?>
                                    <div class="topping-item d-flex justify-content-between align-items-center">
                                        <div class="form-check m-0">
                                            <input class="form-check-input" type="checkbox" name="toppings[]" value="<?= $t['topping_id'] ?>" id="tm<?= $m_id ?>_<?= $t['topping_id'] ?>" style="transform: scale(1.2);">
                                            <label class="form-check-label fw-bold ms-2" for="tm<?= $m_id ?>_<?= $t['topping_id'] ?>">
                                                <?= htmlspecialchars($t['topping_name']) ?>
                                            </label>
                                        </div>
                                        <span class="text-success fw-bold small">+฿<?= number_format($t['price'], 0) ?></span>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>

                            <div class="mt-4">
                                <label class="fw-bold small mb-2"><i class="bi bi-pencil-square me-1"></i>หมายเหตุเพิ่มเติม</label>
                                <textarea name="note" class="form-control rounded-3 border-0 bg-light" rows="2" placeholder="เช่น ไม่ใส่ผัก, เผ็ดน้อย..."></textarea>
                            </div>

                            <div class="d-flex align-items-center justify-content-between mt-3 bg-light p-3 rounded-3">
                                <span class="fw-bold">จำนวนจาน</span>
                                <div class="input-group" style="width: 110px;">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="this.nextElementSibling.stepDown()">-</button>
                                    <input type="number" name="quantity" class="form-control text-center fw-bold bg-transparent border-0" value="1" min="1" max="20" readonly>
                                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="this.previousElementSibling.stepUp()">+</button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow" style="background-color: var(--cafe-brown); border: none;">
                                <i class="bi bi-cart-plus me-2"></i> เพิ่มลงตะกร้า
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($_SESSION['cart'])): ?>
<div class="floating-cart-bar">
    <div class="text-white">
        <div class="fw-bold" style="line-height: 1.1;">ตะกร้าของคุณ</div>
        <small class="opacity-75"><?= count($_SESSION['cart']) ?> รายการอาหาร</small>
    </div>
    <a href="member/cart.php" class="btn btn-warning rounded-pill px-4 fw-bold shadow">
        สรุปใบสั่งซื้อ <i class="bi bi-arrow-right-short"></i>
    </a>
</div>
<?php endif; ?>

<?php include 'includes/footer_customer.php'; ?>