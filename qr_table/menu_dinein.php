<?php
// qr_table/menu_dinein.php
session_start();
require_once '../includes/db.php';
require_once '../includes/csrf.php';

/** @var array $store ข้อมูลร้าน ถูกดึงไว้แล้วใน includes/db.php (แจ้ง IDE ให้รู้จักตัวแปรนี้ กันขึ้นเตือนเฉยๆ ไม่กระทบการทำงาน) */
$store ??= [];

// กันหน้านี้โดนแคชไว้ในเบราว์เซอร์ (สำคัญเวลากดปุ่มย้อนกลับหลังปิดออเดอร์ไปแล้ว)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// เช็กก่อนว่า session นี้เพิ่งถูกปิดไปหรือเปล่า (บิลปิดไปแล้ว) - ถ้าใช่ ห้ามผูก session โต๊ะกลับคืนแม้ URL จะมี ?table= ติดมาด้วยก็ตาม
// (กันกดปุ่มย้อนกลับแล้วได้กลับเข้าไปสั่งอาหารต่อ เพราะ URL หน้านี้เหมือนกับตอนสแกน QR เป๊ะๆ server แยกไม่ออกว่าสแกนจริงหรือกดย้อน)
if (!empty($_SESSION['session_ended'])) {
    echo "<script>window.location='../index.php';</script>";
    exit;
}

// เช็กว่าสแกนโต๊ะมาจริงไหม
if (isset($_GET['table']) && !empty($_GET['table'])) {
    $table_no = htmlspecialchars($_GET['table']);
    // ลิงก์ภายในหน้านี้ (หมวดหมู่, เพิ่มลงตะกร้า, ปุ่ม +/- ในตะกร้า ฯลฯ) ก็แนบ ?table= มาด้วยเสมอ
    // เช็กก่อนว่าเป็นเลขโต๊ะเดิมหรือเปล่า ถ้าเดิมไม่ต้องรีเซ็ตประเภทออเดอร์ที่เลือกไว้แล้ว และไม่ต้องเช็ค
    // token ซ้ำด้วย (token มีไว้เช็คตอนสแกน QR ครั้งแรกที่ผูก session เท่านั้น ลิงก์ภายในแอประหว่างสั่งอาหาร
    // ที่ไม่มี &t= แนบมาด้วยจะได้ใช้งานต่อไปได้ตามปกติ)
    $is_new_table_scan = !isset($_SESSION['table_number']) || $_SESSION['table_number'] !== $table_no;

    $stmt = $conn->prepare("SELECT table_id, qr_token FROM restauranttable WHERE table_number = ?");
    $stmt->bind_param("s", $table_no);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();

    if (!$row) {
        // ไม่พบเลขโต๊ะนี้ในระบบ (พิมพ์ผิด/โต๊ะถูกลบไปแล้ว) - ไม่ผูก session ให้เลย กันสถานะค้างครึ่งๆ กลางๆ
        // (เดิมปล่อยผ่านแบบเงียบๆ ทำให้ session ผูก table_number ไว้ทั้งที่ table_id ไม่มีค่าจริง)
        echo "<script>alert('ไม่พบโต๊ะนี้ในระบบ กรุณาสแกน QR Code ที่โต๊ะใหม่อีกครั้งครับ'); window.location='../index.php';</script>";
        exit;
    }

    if ($is_new_table_scan) {
        // กันเดาเลขโต๊ะ (table_number เดาง่าย เช่น A1, A2, ...) แล้วยิง URL เข้าเมนู/บิลของโต๊ะอื่นตรงๆ
        // โดยไม่ได้สแกน QR จริง - ตอนสแกนใหม่จริงๆ ต้องมี token ลับที่ฝังอยู่ใน QR (พารามิเตอร์ t) ตรงกับ
        // ที่เก็บไว้ในฐานข้อมูลเท่านั้น ถึงจะยอมผูก session โต๊ะนี้ให้
        $provided_token = $_GET['t'] ?? '';
        if (empty($row['qr_token']) || !hash_equals((string) $row['qr_token'], (string) $provided_token)) {
            echo "<script>alert('QR Code ไม่ถูกต้อง กรุณาสแกน QR Code ที่โต๊ะจริงอีกครั้งครับ'); window.location='../index.php';</script>";
            exit;
        }
    }

    $_SESSION['table_number'] = $table_no;
    $_SESSION['table_id'] = $row['table_id'];

    if ($is_new_table_scan) {
        // สแกนใหม่จริง (โต๊ะเปลี่ยน) ให้เลือกประเภทออเดอร์ใหม่เสมอ (กันพลาดจากรอบก่อนหน้า)
        unset($_SESSION['order_type']);
        unset($_SESSION['has_ordered']);
    }
} elseif (!isset($_SESSION['table_id'])) {
    // ไม่มีเลขโต๊ะเลย (เข้าทางลิงก์ตรงๆ ไม่ผ่านการสแกน QR เช่น กดจากหน้าแรก/ลิงก์ที่แชร์ไว้)
    // เดิมบังคับสแกน QR เท่านั้นถึงจะเข้าได้ แต่ตอนนี้หน้าเมนูออนไลน์แบบเดิม (menu.php) ถูกยุบมารวมกับ
    // หน้านี้แล้ว จึงถือว่าเป็นลูกค้าสั่งกลับบ้านอย่างเดียว (ไม่มีตัวเลือก "ทานที่ร้าน" เพราะไม่มีโต๊ะให้ผูก)
    if (!isset($_SESSION['order_type'])) {
        $_SESSION['order_type'] = 'takeaway';
    }
}

// รับค่าประเภทออเดอร์จากหน้าเลือก (ทานที่ร้าน / กลับบ้าน)
if (isset($_GET['type']) && in_array($_GET['type'], ['dine_in', 'takeaway'], true)) {
    $_SESSION['order_type'] = $_GET['type'];
}

// ถ้ายังไม่ได้เลือกประเภทออเดอร์ ให้แสดงหน้าเลือกก่อน ยังไม่เข้าเมนู
if (!isset($_SESSION['order_type'])) {
    $table_no_for_choice = $_SESSION['table_number'] ?? '';
    include '../includes/header_dinein.php';
    ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/menu-dinein.css">
    <div class="container py-5 text-center" style="max-width: 460px;">
        <div class="choice-icon-circle mx-auto mb-3">
            <?php if (!empty($store['logo_url']) && $store['logo_url'] !== 'default_logo.png'): ?>
                <img src="<?= BASE_URL ?>assets/images/logos/<?= htmlspecialchars($store['logo_url']) ?>" alt="logo" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
            <?php else: ?>
                <i class="bi bi-shop"></i>
            <?php endif; ?>
        </div>
        <h4 class="fw-bold mb-1">ยินดีต้อนรับ!</h4>
        <p class="fw-bold mb-3" style="color: var(--cafe-brown);"><?= htmlspecialchars($store['restaurant_name'] ?? 'ร้านของเรา') ?></p>
        <span class="location-pill mb-3">
            <i class="bi bi-geo-alt-fill me-1"></i> คุณกำลังอยู่ที่ โต๊ะ <?= htmlspecialchars($table_no_for_choice) ?>
        </span>
        <p class="text-muted mb-4">กรุณาเลือกรูปแบบการสั่งอาหารเพื่อเริ่มต้น</p>

        <div class="row g-3">
            <div class="col-6">
                <a href="?table=<?= urlencode($table_no_for_choice) ?>&type=dine_in" class="order-type-card dine-in">
                    <i class="bi bi-cup-hot-fill"></i>
                    <div class="ot-title">ทานที่ร้าน</div>
                    <div class="ot-sub">นั่งทานที่ร้าน<br>รอเสิร์ฟที่โต๊ะ</div>
                    <span class="ot-pill">โต๊ะ <?= htmlspecialchars($table_no_for_choice) ?> &rarr;</span>
                </a>
            </div>
            <div class="col-6">
                <a href="?table=<?= urlencode($table_no_for_choice) ?>&type=takeaway" class="order-type-card takeaway">
                    <i class="bi bi-bag-fill"></i>
                    <div class="ot-title">สั่งกลับบ้าน</div>
                    <div class="ot-sub">สั่งใส่กล่อง<br>นำกลับบ้าน</div>
                    <span class="ot-pill">Takeaway &rarr;</span>
                </a>
            </div>
        </div>
    </div>
    <?php
    include '../includes/footer_dinein.php';
    exit;
}

// สั่งกลับบ้านแบบไม่ผ่าน QR ไม่มีเลขโต๊ะให้ผูก ปล่อยเป็นค่าว่างไว้ (เดิม fallback เป็น "ไม่ทราบโต๊ะ"
// ซึ่งจะหลุดไปติดอยู่ใน URL ?table=ไม่ทราบโต๊ะ ของทุกลิงก์ในหน้านี้โดยไม่ได้ตั้งใจ)
$table_display = $_SESSION['table_number'] ?? '';

// สร้าง query string ของหน้านี้ ใส่ table= ต่อท้ายเฉพาะตอนมีโต๊ะจริงเท่านั้น ใช้ซ้ำกับทุกลิงก์ภายในหน้านี้
function dinein_url($extra = '') {
    global $table_display;
    $parts = [];
    if ($table_display !== '') { $parts[] = 'table=' . urlencode($table_display); }
    if ($extra !== '') { $parts[] = $extra; }
    return empty($parts) ? '' : ('?' . implode('&', $parts));
}

// ดักจับหมวดหมู่
$current_cat_id = isset($_GET['cat_id']) ? intval($_GET['cat_id']) : 0;

// สรุปยอดตะกร้า (ต้องคำนวณก่อน include nav_dinein.php เพื่อโชว์จำนวนบนแท็บ "รายการที่สั่ง")
$total_qty = 0;
$total_price = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $total_qty += $item['quantity'];
        $total_price += ($item['price'] * $item['quantity']);
    }
}

$active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'cart') ? 'cart' : 'menu';
$cart_return_url = '../qr_table/menu_dinein.php' . dinein_url('tab=cart');

// รหัสร่วมโต๊ะมีความหมายเฉพาะตอน "ทานที่ร้าน" เท่านั้น เพราะเป็นการกันคนแปลกหน้ามาสั่งปนกับโต๊ะที่ทานอาหารร่วมกัน
// ส่วน "สั่งกลับบ้าน" ต่อให้สแกน QR จากโต๊ะเดียวกันมา ก็เป็นออเดอร์ส่วนตัวแยกจากคนอื่น ไม่ได้ทานร่วมโต๊ะ
// จึงไม่ต้องขอรหัสอะไรเลย แต่ละคนสั่ง/จ่าย/ดูใบเสร็จของตัวเองอิสระต่อกัน
$db_join_code = '';
$is_verified_join = true;
if (isset($_SESSION['table_id']) && $_SESSION['order_type'] === 'dine_in') {
    $tbl_stmt = $conn->prepare("SELECT join_code FROM restauranttable WHERE table_id = ?");
    $tbl_stmt->bind_param("i", $_SESSION['table_id']);
    $tbl_stmt->execute();
    $tbl_data = $tbl_stmt->get_result()->fetch_assoc();

    $db_join_code = $tbl_data['join_code'] ?? '';

    // ตรวจสอบว่าผู้ใช้มีสิทธิ์สั่งอาหารในรอบนี้หรือไม่
    $is_verified_join = (!empty($_SESSION['has_ordered']) || (isset($_SESSION['user_join_code']) && $_SESSION['user_join_code'] === $db_join_code));

    // โต๊ะนี้มีคนอื่นเปิดออเดอร์ไว้ก่อนแล้วและยังไม่ได้ยืนยันรหัสร่วมโต๊ะ -> ส่งไปหน้ากรอกรหัสแยกต่างหาก
    // เช็คจาก join_code อย่างเดียว ไม่เช็คสถานะโต๊ะ เพราะรหัสถูกสุ่มไว้ตั้งแต่ตอนสั่งออเดอร์แรก
    // ก่อนที่ร้านจะกดอนุมัติเปิดโต๊ะเสียอีก (สถานะตอนนั้นอาจยังเป็น "available" อยู่)
    if ($store['is_shop_open'] != 0 && !empty($db_join_code) && !$is_verified_join) {
        header("Location: join_table.php?table=" . urlencode($table_display));
        exit;
    }
}

$nav_cart_qty = $total_qty;
$nav_active_tab = $active_tab;

include '../includes/header_dinein.php';
include '../includes/nav_dinein.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/menu-dinein.css">

<div class="container py-4">
    <?php if($store['is_shop_open'] == 0): ?>
        <!-- ร้านปิด: จุดตัดเด็ดขาด แยกออกจาก logic อื่นทั้งหมด (รหัสร่วมโต๊ะ, เมนู, ตะกร้า) ไม่ให้โผล่มาปนกันตอนร้านปิด -->
        <div class="alert alert-danger text-center rounded-4 shadow-sm border-0 py-5">
            <h4 class="fw-bold mb-2"><i class="bi bi-door-closed-fill text-danger"></i> ขณะนี้ร้านปิดให้บริการ</h4>
            <p class="mb-0 text-muted">ขออภัยในความไม่สะดวกครับ</p>
        </div>
    <?php else: ?>

    <?php if(!empty($db_join_code) && $is_verified_join): ?>
        <div class="d-flex justify-content-end mb-3">
            <span class="badge bg-dark text-warning rounded-pill px-3 py-2 fw-bold shadow-sm">
                <i class="bi bi-key-fill text-warning me-1"></i> รหัสร่วมโต๊ะ: <?= htmlspecialchars($db_join_code) ?>
            </span>
        </div>
    <?php endif; ?>

    <div id="tab-menu" class="dinein-tab-pane" style="<?= $active_tab === 'cart' ? 'display:none;' : '' ?>">

    <div class="scroll-horizontal mb-4">
        <a href="menu_dinein.php<?= dinein_url() ?>" class="btn <?= ($current_cat_id == 0) ? 'btn-dark' : 'btn-outline-dark bg-white' ?> rounded-pill px-4 flex-shrink-0 fw-bold shadow-sm">
            เมนูทั้งหมด
        </a>
        <?php
        $categories = $conn->query("SELECT * FROM category WHERE is_active = 1");
        if ($categories && $categories->num_rows > 0):
            while ($cat = $categories->fetch_assoc()):
                $is_active_cat = ($current_cat_id == $cat['category_id']) ? 'btn-dark' : 'btn-outline-dark bg-white';
        ?>
                <a href="menu_dinein.php<?= dinein_url('cat_id=' . $cat['category_id']) ?>" class="btn <?= $is_active_cat ?> rounded-pill px-4 flex-shrink-0 fw-bold shadow-sm">
                    <?= htmlspecialchars($cat['category_name']) ?>
                </a>
        <?php
            endwhile;
        endif;
        ?>
    </div>

    <div class="row g-3">
        <?php
        $items_stmt = $conn->prepare("SELECT i.* FROM item i LEFT JOIN category c ON i.category_id = c.category_id WHERE i.is_active = 1 AND (c.is_active = 1 OR i.category_id IS NULL) AND (? = 0 OR i.category_id = ?) ORDER BY i.item_id DESC");
        $items_stmt->bind_param("ii", $current_cat_id, $current_cat_id);
        $items_stmt->execute();
        $items = $items_stmt->get_result();
        if($items && $items->num_rows > 0):
            while($m = $items->fetch_assoc()):
                $m_id = $m['item_id'];
                $price = $m['price'];
                $is_out_of_stock = (!empty($m['use_stock']) && intval($m['stock_qty']) <= 0);
                $img_path = !empty($m['image_url']) ? "../assets/images/items/" . $m['image_url'] : "../assets/images/items/default_food.jpg";
        ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="menu-card h-100 shadow-sm <?= $is_out_of_stock ? 'opacity-75' : '' ?>" <?= !$is_out_of_stock ? 'data-bs-toggle="modal" data-bs-target="#itemModal'.$m_id.'"' : '' ?>>
                    <?php if (!empty($m['is_featured'])): ?>
                        <span class="featured-badge"><i class="bi bi-star-fill"></i> แนะนำ</span>
                    <?php endif; ?>
                    <img src="<?= htmlspecialchars($img_path) ?>" class="w-100" style="height: 140px; object-fit: cover;" onerror="this.src='../assets/images/items/default_food.jpg'">
                    <div class="p-3">
                        <div class="fw-bold small text-truncate mb-1"><?= htmlspecialchars($m['name']) ?></div>
                        <div class="d-flex align-items-center gap-1 flex-wrap mb-2">
                            <span class="price-normal">฿<?= number_format($price, 0) ?></span>
                            <?php if($is_out_of_stock): ?>
                                <span class="badge bg-danger rounded-pill small ms-auto">หมด</span>
                            <?php endif; ?>
                        </div>
                        <?php if($is_out_of_stock): ?>
                            <button class="btn btn-secondary btn-sm w-100 rounded-pill mt-2" disabled><i class="bi bi-x-circle"></i> สินค้าหมด</button>
                        <?php else: ?>
                            <button class="btn btn-dark btn-sm w-100 rounded-pill mt-2"><i class="bi bi-plus-circle"></i> เลือก</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="modal fade text-start" id="itemModal<?= $m_id ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <form class="modal-content border-0 rounded-4 shadow" action="../member/cart_action.php?action=add" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="item_id" value="<?= $m_id ?>">
                        <input type="hidden" name="return_url" value="../qr_table/menu_dinein.php<?= dinein_url($current_cat_id > 0 ? 'cat_id='.$current_cat_id : '') ?>">

                        <div class="modal-header border-0 pb-0">
                            <h5 class="fw-bold m-0"><?= htmlspecialchars($m['name']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body py-3">
                            <img src="<?= htmlspecialchars($img_path) ?>" class="w-100 rounded-4 mb-3" style="height:180px; object-fit:cover;" onerror="this.src='../assets/images/items/default_food.jpg'">

                            <?php
                            $tops_stmt = $conn->prepare("SELECT t.*, tc.topping_cat_name FROM menu_toppings mt JOIN topping t ON mt.topping_id = t.topping_id JOIN topping_categories tc ON t.topping_cat_id = tc.topping_cat_id WHERE mt.item_id = ? ORDER BY tc.topping_cat_id ASC, t.topping_id ASC");
                            $tops_stmt->bind_param("i", $m_id);
                            $tops_stmt->execute();
                            $tops_query = $tops_stmt->get_result();

                            $current_cat = "";
                            if($tops_query && $tops_query->num_rows > 0):
                                while($t = $tops_query->fetch_assoc()):
                                    $t_out_of_stock = ($t['is_active'] == 0 || (!empty($t['use_stock']) && intval($t['stock_qty']) <= 0));
                                    if ($current_cat != $t['topping_cat_name']):
                                        $current_cat = $t['topping_cat_name'];
                                        echo "<div class='topping-group-title'>".htmlspecialchars($current_cat)."</div>";
                                    endif;
                            ?>
                                    <div class="topping-item d-flex justify-content-between align-items-center <?= $t_out_of_stock ? 'opacity-50' : '' ?>">
                                        <div class="form-check m-0">
                                            <input class="form-check-input" type="checkbox" name="toppings[]" value="<?= $t['topping_id'] ?>" id="tm<?= $m_id ?>_<?= $t['topping_id'] ?>" style="transform: scale(1.2);" <?= $t_out_of_stock ? 'disabled' : '' ?>>
                                            <label class="form-check-label fw-bold ms-2 <?= $t_out_of_stock ? 'text-decoration-line-through text-muted' : '' ?>" for="tm<?= $m_id ?>_<?= $t['topping_id'] ?>">
                                                <?= htmlspecialchars($t['topping_name']) ?>
                                                <?php if($t_out_of_stock): ?>
                                                    <span class="badge bg-danger ms-1" style="font-size: 0.7rem;">ของหมด</span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                        <span class="<?= $t_out_of_stock ? 'text-muted' : 'text-success' ?> fw-bold small">+฿<?= number_format($t['price'], 0) ?></span>
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

    </div><!-- /#tab-menu -->

    <div id="tab-cart" class="dinein-tab-pane" style="<?= $active_tab === 'cart' ? '' : 'display:none;' ?>">
        <?php if ($total_qty === 0): ?>
            <div class="text-center py-5">
                <i class="bi bi-basket2 display-1 text-muted opacity-25"></i>
                <p class="mt-3 text-muted fw-bold">ยังไม่มีอาหารในตะกร้า</p>
                <button type="button" class="btn btn-outline-primary rounded-pill px-4 mt-2" onclick="dineinSwitchTab('menu')">ไปเลือกเมนู</button>
            </div>
        <?php else: ?>
            <?php if (isset($_GET['reorder']) && $_GET['reorder'] == 1): ?>
                <div class="alert alert-success border-0 rounded-4 shadow-sm d-flex align-items-center mb-3">
                    <i class="bi bi-check-circle-fill fs-1 me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">เพิ่มรายการเดิมลงตะกร้าให้แล้ว</h6>
                        <span class="small">ตรวจสอบรายการด้านล่าง แล้วกด "ยืนยันส่งออเดอร์" เพื่อสั่งอีกครั้งได้เลยครับ
                        <?php if (!empty($_GET['skipped']) && intval($_GET['skipped']) > 0): ?>
                            (มี <?= intval($_GET['skipped']) ?> เมนูที่ตอนนี้ร้านงดขายแล้ว จึงไม่ได้เพิ่มให้)
                        <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            <div class="card cart-card p-3 mb-4">
                <?php foreach ($_SESSION['cart'] as $key => $item):
                    $img_path = !empty($item['image']) ? "../assets/images/items/" . $item['image'] : "../assets/images/items/default_food.jpg";
                ?>
                    <div class="item-row d-flex align-items-center">
                        <img src="<?= htmlspecialchars($img_path) ?>" class="rounded-3 me-3" style="width: 70px; height: 70px; object-fit: cover;" onerror="this.src='../assets/images/items/default_food.jpg'">
                        <div class="flex-grow-1 pe-2">
                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                            <div class="small text-muted mb-1"><?= !empty($item['topping_names']) ? htmlspecialchars($item['topping_names']) : 'ดั้งเดิม'; ?></div>
                            <?php if(!empty($item['note'])): ?>
                                <div class="small text-danger"><i class="bi bi-chat-text"></i> <?= htmlspecialchars($item['note']) ?></div>
                            <?php endif; ?>
                            <form method="POST" action="../member/cart_action.php" class="d-inline">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="id" value="<?= $key ?>">
                                <input type="hidden" name="return_url" value="<?= htmlspecialchars($cart_return_url) ?>">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <button type="submit" class="btn btn-link text-danger small text-decoration-none fw-bold p-0 border-0 align-baseline"><i class="bi bi-trash"></i> ลบ</button>
                            </form>
                        </div>
                        <div class="text-end" style="min-width: 110px;">
                            <div class="fw-bold text-dark mb-2">฿<?= number_format($item['price'] * $item['quantity'], 0) ?></div>
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <form method="POST" action="../member/cart_action.php" class="d-inline">
                                    <input type="hidden" name="action" value="decrease">
                                    <input type="hidden" name="id" value="<?= $key ?>">
                                    <input type="hidden" name="return_url" value="<?= htmlspecialchars($cart_return_url) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary rounded-circle qty-step-btn">−</button>
                                </form>
                                <span class="fw-bold"><?= $item['quantity'] ?></span>
                                <form method="POST" action="../member/cart_action.php" class="d-inline">
                                    <input type="hidden" name="action" value="increase">
                                    <input type="hidden" name="id" value="<?= $key ?>">
                                    <input type="hidden" name="return_url" value="<?= htmlspecialchars($cart_return_url) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary rounded-circle qty-step-btn">+</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php
            $queue_res = $conn->query("SELECT COUNT(*) as queue_count FROM orders WHERE order_status IN ('pending', 'cooking')");
            $queue_count = $queue_res->fetch_assoc()['queue_count'] ?? 0;
            if ($queue_count > 0):
            ?>
                <div class="alert <?= ($queue_count >= 5) ? 'alert-danger border-danger' : 'alert-warning border-warning' ?> border text-dark rounded-4 mb-4 shadow-sm d-flex align-items-center">
                    <i class="bi <?= ($queue_count >= 5) ? 'bi-exclamation-octagon-fill text-danger' : 'bi-info-circle-fill text-warning' ?> fs-1 me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">คิวปัจจุบัน: <?= $queue_count ?> คิว</h6>
                        <?php if ($queue_count >= 5): ?>
                            <span class="small">ขออภัยค่ะ ขณะนี้ออเดอร์ค่อนข้างเยอะ อาหารอาจจะล่าช้ากว่าปกตินิดหน่อยนะคะ 🙏</span>
                        <?php else: ?>
                            <span class="small">ครัวกำลังเตรียมอาหารให้ตามคิวค่ะ นั่งรอรับความอร่อยได้เลย 👨‍🍳</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form action="../member/submit_order.php" method="POST" enctype="multipart/form-data" id="dineinOrderForm">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="order_type" value="<?= htmlspecialchars($_SESSION['order_type']) ?>">
                <input type="hidden" name="total_amount" value="<?= $total_price ?>">

                <?php if ($_SESSION['order_type'] === 'takeaway'): ?>
                <div class="card cart-card p-3 mb-4 customer-info-card">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-fill me-1"></i> ข้อมูลผู้สั่งอาหาร (สำหรับแจ้งรับอาหาร)</h6>

                    <?php if (!empty($_SESSION['dinein_last_name'])): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill mb-3" onclick="dineinFillLastCustomer()">
                            <i class="bi bi-clock-history"></i> ใช้ชื่อล่าสุด: <?= htmlspecialchars($_SESSION['dinein_last_name']) ?>
                        </button>
                    <?php endif; ?>

                    <div class="mb-2">
                        <label class="small fw-bold text-muted">ชื่อของคุณ *</label>
                        <input type="text" id="dineinCustomerName" name="customer_name_online" class="form-control rounded-3" placeholder="เช่น คุณเอ" required>
                    </div>
                    <div>
                        <label class="small fw-bold text-muted">เบอร์โทรศัพท์ *</label>
                        <input type="tel" id="dineinCustomerPhone" name="customer_phone_online" class="form-control rounded-3" placeholder="08xxxxxxxx" required>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($_SESSION['order_type'] === 'takeaway'): ?>
                <div class="card cart-card p-3 mb-4 customer-info-card">
                    <label class="small fw-bold text-muted d-block mb-2"><i class="bi bi-wallet2 me-1"></i> วิธีชำระเงิน</label>
                    <div class="d-flex flex-column gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="dineinPayQr" value="qr_counter" checked onchange="dineinToggleTransferProof()">
                            <label class="form-check-label fw-bold" for="dineinPayQr"><i class="bi bi-qr-code me-1"></i>สแกน QR หน้าเคาน์เตอร์ (จ่ายตอนมารับ)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="dineinPayCash" value="cash" onchange="dineinToggleTransferProof()">
                            <label class="form-check-label fw-bold" for="dineinPayCash"><i class="bi bi-cash-coin me-1"></i>เงินสดหน้าเคาน์เตอร์ (จ่ายตอนมารับ)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="dineinPayTransfer" value="transfer" onchange="dineinToggleTransferProof()">
                            <label class="form-check-label fw-bold" for="dineinPayTransfer"><i class="bi bi-bank me-1"></i>โอนเงินเอง แนบสลิปตอนนี้เลย</label>
                        </div>
                    </div>

                    <!-- โชว์ QR พร้อมเพย์/เลขบัญชีของร้าน + ช่องแนบสลิป เฉพาะตอนเลือก "โอนเงินเอง" เท่านั้น
                         ต้องแนบสลิปมาพร้อมตอนสั่งเลย (ไม่ใช่โชว์ตอนมารับเหมือนเดิม) กันลูกค้าสั่งทิ้งไว้ไม่มารับ/ไม่จ่ายจริง
                         ออเดอร์ยังเข้าครัวทันทีหลังแนบสลิป ไม่ต้องรอร้านกดยืนยันก่อน แต่ร้านตรวจสลิปย้อนหลังได้ที่หน้าจัดการชำระเงิน -->
                    <div id="dineinTransferProofBox" class="mt-3 pt-3 border-top" style="display: none;">
                        <?php if (!empty($store['promptpay_qr'])): ?>
                            <div class="text-center mb-3">
                                <img src="../assets/images/logos/<?= htmlspecialchars($store['promptpay_qr']) ?>" alt="QR พร้อมเพย์" class="rounded-3 shadow-sm" style="max-width: 220px; width: 100%;">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($store['bank_info'])): ?>
                            <div class="small text-muted mb-3" style="white-space: pre-line;"><?= htmlspecialchars($store['bank_info']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($store['promptpay_qr']) && empty($store['bank_info'])): ?>
                            <div class="small text-danger mb-3">ร้านยังไม่ได้ตั้งค่าช่องทางรับเงิน กรุณาเลือกวิธีชำระเงินแบบอื่นแทนครับ</div>
                        <?php endif; ?>
                        <label class="small fw-bold text-muted">แนบสลิปการโอนเงิน *</label>
                        <input type="file" name="payment_slip" id="dineinPaymentSlip" class="form-control rounded-3" accept="image/*">
                        <div class="form-text">รองรับไฟล์ภาพ (JPG, PNG, WEBP)</div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card cart-card p-4 border-top border-4 border-success text-center">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="h6 mb-0 fw-bold">ยอดสุทธิรวม</span>
                        <span class="h3 mb-0 fw-bold text-success">฿<?= number_format($total_price, 0) ?></span>
                    </div>

                    <button type="submit" id="dineinSubmitOrderBtn" class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow-sm fs-5">
                        <i class="bi bi-send-fill me-2"></i> ยืนยันส่งออเดอร์
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div><!-- /#tab-cart -->

    <?php endif; // is_shop_open ?>
</div>

<?php if (isset($_GET['order_success']) && $_GET['order_success'] == 1): ?>
<div class="modal fade" id="successOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 rounded-4 shadow text-center p-4">
            <div class="modal-body p-0">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                <h4 class="fw-bold mt-3 mb-2">ส่งออเดอร์แล้ว!</h4>
                <?php if (!empty($_GET['queue_no'])): ?>
                    <div class="fw-bold mb-2" style="font-size: 1.6rem; color: var(--cafe-brown);">
                        คิวที่ #<?= str_pad(intval($_GET['queue_no']), 3, '0', STR_PAD_LEFT) ?>
                    </div>
                <?php endif; ?>
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
        window.history.replaceState(null, null, window.location.pathname + <?= json_encode(dinein_url()) ?>);
    });
</script>
<?php endif; ?>

<script>
function dineinSwitchTab(tab) {
    var menuPane = document.getElementById('tab-menu');
    var cartPane = document.getElementById('tab-cart');
    if (!menuPane || !cartPane) return;

    menuPane.style.display = (tab === 'cart') ? 'none' : '';
    cartPane.style.display = (tab === 'cart') ? '' : 'none';

    document.querySelectorAll('.dinein-tab').forEach(function(btn, idx) {
        var isCartBtn = idx === 1;
        btn.classList.toggle('active', isCartBtn === (tab === 'cart'));
    });

    var url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.replaceState(null, null, url);
}

function dineinFillLastCustomer() {
    var nameInput = document.getElementById('dineinCustomerName');
    var phoneInput = document.getElementById('dineinCustomerPhone');
    if (nameInput) nameInput.value = <?= json_encode($_SESSION['dinein_last_name'] ?? '') ?>;
    if (phoneInput) phoneInput.value = <?= json_encode($_SESSION['dinein_last_phone'] ?? '') ?>;
}

// โชว์/ซ่อนกล่องแนบสลิป (QR พร้อมเพย์ + ช่องอัปโหลด) เฉพาะตอนเลือกวิธีจ่าย "โอนเงินเอง" เท่านั้น
// และตั้ง required ให้ช่องอัปโหลดเฉพาะตอนที่กล่องนี้โชว์อยู่ (กันกรอกวิธีอื่นแล้วโดนบังคับแนบไฟล์ไปด้วย)
function dineinToggleTransferProof() {
    var box = document.getElementById('dineinTransferProofBox');
    var slipInput = document.getElementById('dineinPaymentSlip');
    var transferRadio = document.getElementById('dineinPayTransfer');
    if (!box || !transferRadio) return;
    var isTransfer = transferRadio.checked;
    box.style.display = isTransfer ? 'block' : 'none';
    if (slipInput) slipInput.required = isTransfer;
}

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('dineinOrderForm');
    if (!form) return;
    // ใช้ submit event ธรรมดา (ไม่เรียก form.submit() ตรงๆ) เพื่อให้ required/validation ของเบราว์เซอร์ทำงานปกติ
    form.addEventListener('submit', function () {
        var btn = document.getElementById('dineinSubmitOrderBtn');
        if (btn) {
            btn.innerHTML = 'กำลังส่งออเดอร์...';
            btn.disabled = true;
        }
    });
});
</script>

<?php include '../includes/footer_dinein.php'; ?>
