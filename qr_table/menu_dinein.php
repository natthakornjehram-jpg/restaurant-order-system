<?php
// qr_table/menu_dinein.php
session_start();
require_once '../includes/db.php';

// เช็กว่าสแกนโต๊ะมาจริงไหม
if (isset($_GET['table']) && !empty($_GET['table'])) {
    $table_no = htmlspecialchars($_GET['table']);
    $_SESSION['table_number'] = $table_no;
    $_SESSION['order_type'] = 'dine_in';
    
    $stmt = $conn->prepare("SELECT table_id FROM restauranttable WHERE table_number = ?");
    $stmt->bind_param("s", $table_no);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $_SESSION['table_id'] = $row['table_id'];
    }
} elseif (!isset($_SESSION['table_id'])) {
    echo "<script>alert('กรุณาสแกน QR Code ที่โต๊ะก่อนสั่งอาหารครับ'); window.location='../index.php';</script>";
    exit;
}

$table_display = $_SESSION['table_number'] ?? 'ไม่ทราบโต๊ะ';

// ดักจับหมวดหมู่
$cat_filter = "";
$current_cat_id = isset($_GET['cat_id']) ? intval($_GET['cat_id']) : 0;
if ($current_cat_id > 0) {
    $cat_filter = " AND category_id = $current_cat_id ";
}

$store_res = $conn->query("SELECT is_shop_open, restaurant_name FROM owner LIMIT 1");
$store = $store_res->fetch_assoc();

include '../includes/header_dinein.php'; 
include '../includes/nav_dinein.php'; 
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/menu-dinein.css">

<div class="container py-4">
    <div class="member-badge-section d-flex justify-content-between align-items-center mt-3">
        <div>
            <h5 class="fw-bold mb-1">ยินดีต้อนรับครับ ✨</h5>
            <p class="mb-0 small opacity-75">สั่งอาหารผ่านมือถือได้เลย!</p>
        </div>
        <div class="text-end">
            <span class="badge bg-warning text-dark rounded-pill px-4 py-2 fw-bold shadow-sm" style="font-size: 1.1rem;">
                <i class="bi bi-shop"></i> โต๊ะ <?= htmlspecialchars($table_display) ?>
            </span>
        </div>
    </div>

    <?php if($store['is_shop_open'] == 0): ?>
        <div class="alert alert-danger text-center rounded-4 shadow-sm border-0 py-5">
            <h4 class="fw-bold mb-2"><i class="bi bi-door-closed-fill text-danger"></i> ขณะนี้ร้านปิดให้บริการ</h4>
            <p class="mb-0 text-muted">ขออภัยในความไม่สะดวกครับ</p>
        </div>
    <?php else: ?>

    <div class="scroll-horizontal mb-4">
        <a href="menu_dinein.php?table=<?= urlencode($table_display) ?>" class="btn <?= ($current_cat_id == 0) ? 'btn-dark' : 'btn-outline-dark bg-white' ?> rounded-pill px-4 flex-shrink-0 fw-bold shadow-sm">
            เมนูทั้งหมด
        </a>
        <?php
        $categories = $conn->query("SELECT * FROM category WHERE is_active = 1");
        if ($categories && $categories->num_rows > 0):
            while ($cat = $categories->fetch_assoc()):
                $is_active_cat = ($current_cat_id == $cat['category_id']) ? 'btn-dark' : 'btn-outline-dark bg-white';
        ?>
                <a href="menu_dinein.php?table=<?= urlencode($table_display) ?>&cat_id=<?= $cat['category_id'] ?>" class="btn <?= $is_active_cat ?> rounded-pill px-4 flex-shrink-0 fw-bold shadow-sm">
                    <?= htmlspecialchars($cat['category_name']) ?>
                </a>
        <?php 
            endwhile;
        endif; 
        ?>
    </div>

    <div class="row g-3">
        <?php
        $items = $conn->query("SELECT * FROM item WHERE is_active = 1 $cat_filter ORDER BY item_id DESC");
        if($items && $items->num_rows > 0):
            while($m = $items->fetch_assoc()): 
                $m_id = $m['item_id'];
                $price = $m['price'];
                $img_path = !empty($m['image_url']) ? "../assets/images/items/" . $m['image_url'] : "../assets/images/items/default_food.jpg";
        ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="menu-card h-100 shadow-sm" data-bs-toggle="modal" data-bs-target="#itemModal<?= $m_id ?>">
                    <img src="<?= htmlspecialchars($img_path) ?>" class="w-100" style="height: 140px; object-fit: cover;" onerror="this.src='../assets/images/items/default_food.jpg'">
                    <div class="p-3">
                        <div class="fw-bold small text-truncate mb-1"><?= htmlspecialchars($m['name']) ?></div>
                        <div class="d-flex align-items-center gap-1 flex-wrap mb-2">
                            <span class="price-normal">฿<?= number_format($price, 0) ?></span>
                        </div>
                        <button class="btn btn-dark btn-sm w-100 rounded-pill mt-2"><i class="bi bi-plus-circle"></i> เลือก</button>
                    </div>
                </div>
            </div>

            <div class="modal fade text-start" id="itemModal<?= $m_id ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <form class="modal-content border-0 rounded-4 shadow" action="../member/cart_action.php?action=add" method="POST">
                        <input type="hidden" name="item_id" value="<?= $m_id ?>">
                        <input type="hidden" name="return_url" value="../qr_table/menu_dinein.php?table=<?= urlencode($table_display) ?><?= ($current_cat_id > 0) ? '&cat_id='.$current_cat_id : '' ?>">
                        
                        <div class="modal-header border-0 pb-0">
                            <h5 class="fw-bold m-0"><?= htmlspecialchars($m['name']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        
                        <div class="modal-body py-3">
                            <img src="<?= htmlspecialchars($img_path) ?>" class="w-100 rounded-4 mb-3" style="height:180px; object-fit:cover;" onerror="this.src='../assets/images/items/default_food.jpg'">
                            
                            <?php 
                            $tops_stmt = $conn->prepare("SELECT t.*, tc.topping_cat_name FROM menu_toppings mt JOIN topping t ON mt.topping_id = t.topping_id JOIN topping_categories tc ON t.topping_cat_id = tc.topping_cat_id WHERE mt.item_id = ? AND t.is_active = 1 ORDER BY tc.topping_cat_id ASC, t.topping_id ASC");
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
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-egg-fried display-1 text-muted opacity-25"></i>
                <p class="mt-3 text-muted">ยังไม่มีรายการอาหารในหมวดหมู่นี้</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php 
$total_qty = 0;
$total_price = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $total_qty += $item['quantity'];
        $total_price += ($item['price'] * $item['quantity']);
    }
}
?>
<?php if ($total_qty > 0): ?>
<div class="floating-cart-bar">
    <div class="text-white d-flex align-items-center">
        <div style="position: relative; display: inline-block;" class="me-3">
            <i class="bi bi-cart3 fs-3 text-warning"></i>
            <span class="cart-badge"><?= $total_qty ?></span>
        </div>
        <div style="line-height: 1.2;">
            <div class="small opacity-75">ออเดอร์โต๊ะ <?= htmlspecialchars($table_display) ?></div>
            <div class="fw-bold text-warning">฿<?= number_format($total_price, 0) ?></div>
        </div>
    </div>
    <a href="cart_dinein.php" class="btn btn-warning rounded-pill px-4 py-2 fw-bold shadow-sm d-flex align-items-center">
        สั่งเลย <i class="bi bi-chevron-right ms-1"></i>
    </a>
</div>
<?php endif; ?> 

<?php if (isset($_GET['order_success']) && $_GET['order_success'] == 1): ?>
<div class="modal fade" id="successOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 rounded-4 shadow text-center p-4">
            <div class="modal-body p-0">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                <h4 class="fw-bold mt-3 mb-2">ส่งออเดอร์แล้ว!</h4>
                <p class="text-muted small mb-4">รายการอาหารของคุณถูกส่งเข้าครัวเรียบร้อยแล้ว นั่งรอรับความอร่อยได้เลยครับ 👨‍🍳</p>
                <button type="button" class="btn btn-success rounded-pill px-4 fw-bold" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var successModal = new bootstrap.Modal(document.getElementById('successOrderModal'));
        successModal.show();
        window.history.replaceState(null, null, window.location.pathname + "?table=<?= urlencode($table_display) ?>");
    });
</script>
<?php endif; ?>

<?php include '../includes/footer_dinein.php'; ?>