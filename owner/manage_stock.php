<?php 
// owner/manage_stock.php
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';
require_once '../includes/csrf.php';

$msg = "";

// 🟢 อัปเดตคลังสินค้ารายชิ้น หรือ ท็อปปิ้ง ผ่าน AJAX หรือ Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $is_ajax_stock = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        if ($is_ajax_stock) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        header("Location: manage_stock.php");
        exit;
    }

    if ($action === 'update_stock') {
        $item_id = intval($_POST['item_id']);
        $stock_qty = max(0, intval($_POST['stock_qty']));
        $use_stock = isset($_POST['use_stock']) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE item SET stock_qty = ?, use_stock = ? WHERE item_id = ?");
        $stmt->bind_param("iii", $stock_qty, $use_stock, $item_id);
        if ($stmt->execute()) {
            $msg = "อัปเดตคลังสินค้าเรียบร้อยแล้ว";
        }
    } elseif ($action === 'quick_adjust') {
        $item_id = intval($_POST['item_id']);
        $change = intval($_POST['change']);
        
        $stmt = $conn->prepare("UPDATE item SET stock_qty = GREATEST(0, stock_qty + ?) WHERE item_id = ?");
        $stmt->bind_param("ii", $change, $item_id);
        $stmt->execute();
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            $qty_stmt = $conn->prepare("SELECT stock_qty FROM item WHERE item_id = ?");
            $qty_stmt->bind_param("i", $item_id);
            $qty_stmt->execute();
            $new_qty = $qty_stmt->get_result()->fetch_assoc()['stock_qty'];
            echo json_encode(['success' => true, 'new_qty' => $new_qty]);
            exit;
        }
    } elseif ($action === 'quick_adjust_topping') {
        $topping_id = intval($_POST['topping_id']);
        $change = intval($_POST['change']);

        $stmt = $conn->prepare("UPDATE topping SET stock_qty = GREATEST(0, stock_qty + ?) WHERE topping_id = ?");
        $stmt->bind_param("ii", $change, $topping_id);
        $stmt->execute();

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            $qty_stmt = $conn->prepare("SELECT stock_qty FROM topping WHERE topping_id = ?");
            $qty_stmt->bind_param("i", $topping_id);
            $qty_stmt->execute();
            $new_qty = $qty_stmt->get_result()->fetch_assoc()['stock_qty'];
            echo json_encode(['success' => true, 'new_qty' => $new_qty]);
            exit;
        }
    } elseif ($action === 'toggle_topping') {
        $topping_id = intval($_POST['topping_id']);
        $current_status = intval($_POST['current_status']);
        $new_status = ($current_status == 1) ? 0 : 1;

        $stmt = $conn->prepare("UPDATE topping SET is_active = ? WHERE topping_id = ?");
        $stmt->bind_param("ii", $new_status, $topping_id);
        $stmt->execute();

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'new_status' => $new_status]);
            exit;
        }
        $msg = "อัปเดตสถานะท็อปปิ้งเรียบร้อยแล้ว";
    } elseif ($action === 'add_topping') {
        // เพิ่มท็อปปิ้ง/วัตถุดิบใหม่โดยตรงจากหน้าคลังสินค้า (ตั้งค่าติดตามคลังสินค้าเป็นเปิดเสมอ เพราะหน้านี้มีไว้จัดการของที่ต้องนับคลังสินค้าเท่านั้น)
        header('Content-Type: application/json');
        $name = trim($_POST['topping_name'] ?? '');
        $cat_id = intval($_POST['topping_cat_id'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $stock_qty = max(0, intval($_POST['stock_qty'] ?? 50));

        if ($name === '' || $cat_id <= 0 || $price < 0) {
            echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO topping (topping_name, topping_cat_id, price, use_stock, stock_qty, is_active) VALUES (?, ?, ?, 1, ?, 1)");
        $stmt->bind_param("sidi", $name, $cat_id, $price, $stock_qty);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล']);
        }
        exit;
    }
}

// นับจำนวนของหมด/ใกล้หมดในแต่ละหมวด ไว้โชว์เป็น badge เตือนบนหัวข้อหมวดที่พับเก็บอยู่
// จะได้ไม่ต้องกางทุกหมวดออกมาดูก็ยังรู้ว่าหมวดไหนต้องรีบเข้าไปดู
function stock_count_alerts_items($rows) {
    $out = 0; $low = 0;
    foreach ($rows as $m) {
        if (empty($m['use_stock'])) continue;
        $q = (int) $m['stock_qty'];
        if ($q <= 0) $out++;
        elseif ($q <= 5) $low++;
    }
    return [$out, $low];
}

function stock_count_alerts_toppings($rows) {
    $out = 0; $low = 0;
    foreach ($rows as $t) {
        $q = (int) $t['stock_qty'];
        if (empty($t['is_active']) || $q <= 0) $out++;
        elseif ($q <= 5) $low++;
    }
    return [$out, $low];
}

// การ์ดเมนูอาหาร 1 ใบ (แยกเป็นฟังก์ชันเพื่อเรียกซ้ำได้ทั้งตอนจัดกลุ่มตามหมวดหมู่ และกลุ่ม "ไม่มีหมวดหมู่")
function render_stock_item_card($m) {
    $m_id = $m['item_id'];
    $stock = $m['stock_qty'];
    $use_stock = $m['use_stock'];
    $img_path = !empty($m['image_url']) ? "../assets/images/items/" . $m['image_url'] : "../assets/images/items/default_food.jpg";

    $stock_badge = "bg-success";
    $stock_text = "มีของพอใช้";
    if ($use_stock && $stock <= 0) {
        $stock_badge = "bg-danger";
        $stock_text = "ของหมด!";
    } elseif ($use_stock && $stock <= 5) {
        $stock_badge = "bg-warning text-dark";
        $stock_text = "ใกล้หมด";
    }
    ?>
    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
        <div class="card stock-card p-3 h-100 bg-white">
            <div class="d-flex align-items-center mb-3">
                <img src="<?= htmlspecialchars($img_path) ?>" class="rounded-4 me-3 shadow-sm" style="width: 75px; height: 75px; object-fit: cover;" onerror="this.src='../assets/images/items/default_food.jpg'">
                <div class="flex-grow-1 overflow-hidden">
                    <h6 class="fw-bold mb-1 text-truncate"><?= htmlspecialchars($m['name']) ?></h6>
                    <div class="fw-bold text-success">฿<?= number_format($m['price'], 0) ?></div>
                </div>
                <button type="button" id="star-btn-<?= $m_id ?>"
                        class="star-btn <?= (!empty($m['is_featured'])) ? 'star-active' : '' ?>"
                        onclick="toggleFeatured(<?= $m_id ?>, <?= (!empty($m['is_featured'])) ? 0 : 1 ?>)"
                        title="<?= (!empty($m['is_featured'])) ? 'เมนูแนะนำ (กดเพื่อยกเลิก)' : 'ติดดาวเป็นเมนูแนะนำ' ?>">★</button>
            </div>

            <div class="bg-light rounded-4 p-3 mb-3 text-center">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-muted fw-bold">คงเหลือในคลังสินค้า</span>
                    <span class="badge rounded-pill <?= $stock_badge ?> px-3 py-1"><?= $stock_text ?></span>
                </div>

                <div class="d-flex align-items-center justify-content-center gap-3 my-2">
                    <button type="button" class="btn btn-outline-danger btn-qty shadow-sm" onclick="adjustStock(<?= $m_id ?>, -1)">-</button>
                    <span class="h2 fw-bold m-0" id="stock_display_<?= $m_id ?>" style="min-width: 60px;"><?= $stock ?></span>
                    <button type="button" class="btn btn-outline-success btn-qty shadow-sm" onclick="adjustStock(<?= $m_id ?>, 1)">+</button>
                </div>
            </div>

            <div class="mt-auto">
                <div class="d-flex gap-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill flex-grow-1" onclick="adjustStock(<?= $m_id ?>, 10)">+10</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill flex-grow-1" onclick="adjustStock(<?= $m_id ?>, 50)">+50</button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// การ์ดท็อปปิ้ง/วัตถุดิบเสริม 1 ใบ
function render_stock_topping_card($top) {
    $t_id = $top['topping_id'];
    $is_active = $top['is_active'];
    $t_stock = $top['stock_qty'];

    $top_badge = "bg-success";
    $top_text = "เปิดขาย";
    if ($is_active == 0 || $t_stock <= 0) {
        $top_badge = "bg-danger";
        $top_text = ($is_active == 0) ? "ปิดขาย (สวิตช์ปิด)" : "ของหมด!";
    } elseif ($t_stock <= 5) {
        $top_badge = "bg-warning text-dark";
        $top_text = "ใกล้หมด";
    }
    ?>
    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
        <div class="card stock-card p-3 h-100 bg-white">
            <div class="d-flex justify-content-end mb-2">
                <span class="badge rounded-pill <?= $top_badge ?> px-3 py-1" id="top_badge_<?= $t_id ?>"><?= $top_text ?></span>
            </div>
            <h5 class="fw-bold mb-1"><?= htmlspecialchars($top['topping_name']) ?></h5>
            <div class="fw-bold text-success mb-2">+ ฿<?= number_format($top['price'], 0) ?></div>

            <div class="bg-light rounded-4 p-3 mb-3 text-center">
                <div class="small text-muted fw-bold mb-1">คลังสินค้าคงเหลือ (ชุด/จาน)</div>
                <div class="d-flex align-items-center justify-content-center gap-3 my-1">
                    <button type="button" class="btn btn-outline-danger btn-qty shadow-sm" onclick="adjustToppingStock(<?= $t_id ?>, -1)">-</button>
                    <span class="h2 fw-bold m-0" id="top_stock_display_<?= $t_id ?>" style="min-width: 60px;"><?= $t_stock ?></span>
                    <button type="button" class="btn btn-outline-success btn-qty shadow-sm" onclick="adjustToppingStock(<?= $t_id ?>, 1)">+</button>
                </div>
            </div>

            <div class="mt-auto">
                <div class="d-flex gap-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill flex-grow-1" onclick="adjustToppingStock(<?= $t_id ?>, 10)">+10</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill flex-grow-1" onclick="adjustToppingStock(<?= $t_id ?>, 50)">+50</button>
                </div>
                <button type="button" id="top_btn_<?= $t_id ?>" onclick="toggleToppingStock(<?= $t_id ?>, <?= $is_active ?>)" class="btn w-100 rounded-pill py-2 fw-bold shadow-sm <?= ($is_active == 1) ? 'btn-outline-danger' : 'btn-success' ?>">
                    <?= ($is_active == 1) ? '<i class="bi bi-pause-circle me-1"></i> กดปิดขาย (ปิดชั่วคราว)' : '<i class="bi bi-play-circle me-1"></i> กดเปิดขาย (เปิดใช้งาน)' ?>
                </button>
            </div>
        </div>
    </div>
    <?php
}

include '../includes/header_owner.php';
include '../includes/nav_owner.php';
?>

<style>
    .stock-card { border-radius: 20px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: 0.3s; }
    .stock-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    .btn-qty { width: 45px; height: 45px; border-radius: 50%; font-size: 1.3rem; font-weight: bold; display: flex; align-items: center; justify-content: center; }
    .star-btn { width: 34px; height: 34px; border-radius: 50%; border: 1px solid #e5e7eb; background-color: #f9fafb; color: #d1d5db; font-size: 1.1rem; line-height: 1; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; align-self: flex-start; }
    .star-btn.star-active { background-color: #fff7e0; border-color: #f5b301; color: #f5b301; }
</style>

<div class="main-content container-fluid pb-5 px-4 pt-3 text-dark">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-white rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; border: 1px solid #edf2f7; background: #ffffff; color: #4a5568;" title="ย้อนกลับ">
                <i class="bi bi-arrow-left fs-4"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-0" style="color: #1a202c; font-size: 1.25rem;">
                    <i class="bi bi-box-seam text-warning me-2"></i>จัดการคลังสินค้า & วัตถุดิบ
                </h4>
                <p class="text-muted small mb-0">ตรวจสอบจำนวนคงเหลือคงคลังและปรับสถานะเปิด-ปิดของหมดได้อย่างง่ายดาย</p>
            </div>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success rounded-4 border-0 shadow-sm mb-4">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <ul class="nav nav-pills mb-4 gap-2" id="stockTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-3 px-4 py-3 fw-bold shadow-sm" id="items-tab" data-bs-toggle="pill" data-bs-target="#items-pane" type="button" role="tab">
                <i class="bi bi-egg-fried me-1"></i> เมนูอาหารหลัก
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 px-4 py-3 fw-bold shadow-sm" id="toppings-tab" data-bs-toggle="pill" data-bs-target="#toppings-pane" type="button" role="tab">
                <i class="bi bi-plus-circle-dotted me-1"></i> ท็อปปิ้ง & วัตถุดิบเสริม (หมูกรอบ ฯลฯ)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="stockTabsContent">
        <!-- 🟢 TAB 1: คลังสินค้าเมนูอาหารหลัก แยกเป็นหมวดหมู่ (เรียงคลังสินค้าน้อยก่อนภายในแต่ละหมวด) -->
        <div class="tab-pane fade show active" id="items-pane" role="tabpanel">
            <?php
            $items_by_cat_stock = [];
            $items_uncat_stock = [];
            $items_all_res = $conn->query("SELECT i.*, c.category_name FROM item i LEFT JOIN category c ON i.category_id = c.category_id ORDER BY i.stock_qty ASC, i.item_id DESC");
            if ($items_all_res) {
                while ($m = $items_all_res->fetch_assoc()) {
                    if (!empty($m['category_id'])) {
                        $items_by_cat_stock[$m['category_id']][] = $m;
                    } else {
                        $items_uncat_stock[] = $m;
                    }
                }
            }
            $item_cats_stock = [];
            $item_cats_res = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name ASC");
            if ($item_cats_res) { while ($c = $item_cats_res->fetch_assoc()) { $item_cats_stock[] = $c; } }
            $has_any_item = !empty($items_by_cat_stock) || !empty($items_uncat_stock);
            ?>
            <?php if (!$has_any_item): ?>
                <div class="text-center py-5">
                    <i class="bi bi-box display-1 text-muted opacity-25"></i>
                    <p class="mt-3 text-muted">ยังไม่มีรายการเมนูในระบบ</p>
                </div>
            <?php else: ?>
            <div class="accordion" id="stockItemsAccordion">
                <?php foreach ($item_cats_stock as $c):
                    $cid = $c['category_id'];
                    if (empty($items_by_cat_stock[$cid])) continue;
                    [$out, $low] = stock_count_alerts_items($items_by_cat_stock[$cid]);
                ?>
                <div class="accordion-item border-0 shadow-sm rounded-4 overflow-hidden mb-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#stockItemsCat<?= $cid ?>">
                            <i class="bi bi-folder2-open text-primary me-2"></i>
                            <?= htmlspecialchars($c['category_name']) ?>
                            <span class="badge bg-light text-dark rounded-pill ms-2"><?= count($items_by_cat_stock[$cid]) ?> รายการ</span>
                            <?php if ($out > 0): ?><span class="badge bg-danger rounded-pill ms-1">หมด <?= $out ?></span><?php endif; ?>
                            <?php if ($low > 0): ?><span class="badge bg-warning text-dark rounded-pill ms-1">ใกล้หมด <?= $low ?></span><?php endif; ?>
                        </button>
                    </h2>
                    <div id="stockItemsCat<?= $cid ?>" class="accordion-collapse collapse" data-bs-parent="#stockItemsAccordion">
                        <div class="accordion-body bg-white">
                            <div class="row g-4">
                                <?php foreach ($items_by_cat_stock[$cid] as $m) { render_stock_item_card($m); } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (!empty($items_uncat_stock)):
                    [$out, $low] = stock_count_alerts_items($items_uncat_stock);
                ?>
                <div class="accordion-item border-0 shadow-sm rounded-4 overflow-hidden mb-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#stockItemsUncat">
                            <i class="bi bi-folder2 text-secondary me-2"></i>
                            ไม่มีหมวดหมู่
                            <span class="badge bg-light text-dark rounded-pill ms-2"><?= count($items_uncat_stock) ?> รายการ</span>
                            <?php if ($out > 0): ?><span class="badge bg-danger rounded-pill ms-1">หมด <?= $out ?></span><?php endif; ?>
                            <?php if ($low > 0): ?><span class="badge bg-warning text-dark rounded-pill ms-1">ใกล้หมด <?= $low ?></span><?php endif; ?>
                        </button>
                    </h2>
                    <div id="stockItemsUncat" class="accordion-collapse collapse" data-bs-parent="#stockItemsAccordion">
                        <div class="accordion-body bg-white">
                            <div class="row g-4">
                                <?php foreach ($items_uncat_stock as $m) { render_stock_item_card($m); } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- 🟢 TAB 2: คลังสินค้าท็อปปิ้ง & วัตถุดิบเสริม (มีจำนวนคงเหลือ + ปุ่มเปิด/ปิด) -->
        <div class="tab-pane fade" id="toppings-pane" role="tabpanel">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3 gap-2">
                <p class="text-muted small mb-0">
                    แสดงเฉพาะของที่ติดตามคลังสินค้าไว้
                    <i class="bi bi-info-circle ms-1" title="ตัวเลือกที่ไม่มีวันหมด เช่น ระดับความเผ็ด ขนาดจาน จะไม่แสดงในหน้านี้"></i>
                </p>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm flex-shrink-0" onclick="openAddToppingStockModal()">
                    <i class="bi bi-plus-circle me-1"></i> เพิ่มท็อปปิ้ง/วัตถุดิบ
                </button>
            </div>
            <?php
            $toppings_by_cat_stock = [];
            $toppings_all_res = $conn->query("SELECT t.* FROM topping t WHERE t.use_stock = 1 ORDER BY t.stock_qty ASC, t.topping_id DESC");
            if ($toppings_all_res) {
                while ($top = $toppings_all_res->fetch_assoc()) {
                    $toppings_by_cat_stock[$top['topping_cat_id']][] = $top;
                }
            }
            $topping_cats_stock = [];
            $topping_cats_stock_res = $conn->query("SELECT topping_cat_id, topping_cat_name FROM topping_categories ORDER BY sort_order ASC, topping_cat_name ASC");
            if ($topping_cats_stock_res) { while ($tc = $topping_cats_stock_res->fetch_assoc()) { $topping_cats_stock[] = $tc; } }
            ?>
            <?php if (empty($toppings_by_cat_stock)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-egg-fried display-1 text-muted opacity-25"></i>
                    <p class="mt-3 text-muted">ยังไม่มีรายการท็อปปิ้งในระบบ</p>
                </div>
            <?php else: ?>
            <div class="accordion" id="stockToppingsAccordion">
                <?php foreach ($topping_cats_stock as $tc):
                    $cid = $tc['topping_cat_id'];
                    if (empty($toppings_by_cat_stock[$cid])) continue;
                    [$out, $low] = stock_count_alerts_toppings($toppings_by_cat_stock[$cid]);
                ?>
                <div class="accordion-item border-0 shadow-sm rounded-4 overflow-hidden mb-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#stockToppingsCat<?= $cid ?>">
                            <i class="bi bi-folder2-open text-primary me-2"></i>
                            <?= htmlspecialchars($tc['topping_cat_name']) ?>
                            <span class="badge bg-light text-dark rounded-pill ms-2"><?= count($toppings_by_cat_stock[$cid]) ?> รายการ</span>
                            <?php if ($out > 0): ?><span class="badge bg-danger rounded-pill ms-1">หมด <?= $out ?></span><?php endif; ?>
                            <?php if ($low > 0): ?><span class="badge bg-warning text-dark rounded-pill ms-1">ใกล้หมด <?= $low ?></span><?php endif; ?>
                        </button>
                    </h2>
                    <div id="stockToppingsCat<?= $cid ?>" class="accordion-collapse collapse" data-bs-parent="#stockToppingsAccordion">
                        <div class="accordion-body bg-white">
                            <div class="row g-4">
                                <?php foreach ($toppings_by_cat_stock[$cid] as $top) { render_stock_topping_card($top); } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: เพิ่มท็อปปิ้ง/วัตถุดิบใหม่ (เฉพาะของที่ต้องนับคลังสินค้า แยกจากการเพิ่มเมนูอาหารหลัก) -->
<div class="modal fade" id="addToppingStockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 rounded-4" id="newToppingStockForm">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="fw-bold m-0">เพิ่มท็อปปิ้ง/วัตถุดิบใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <p class="text-muted small">รายการที่เพิ่มจากหน้านี้จะเปิดติดตามคลังสินค้าให้อัตโนมัติ (ถ้าต้องการตัวเลือกที่ไม่มีวันหมด เช่น ระดับความเผ็ด ให้ไปเพิ่มที่หน้า "จัดการตัวเลือกเสริม" แทน)</p>

                <div class="mb-3">
                    <label class="small fw-bold mb-2">ชื่อท็อปปิ้ง/วัตถุดิบ</label>
                    <input type="text" name="topping_name" id="new_topping_name" class="form-control rounded-3" required>
                    <div class="invalid-feedback">กรุณากรอกชื่อท็อปปิ้ง/วัตถุดิบ</div>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold mb-2">กลุ่ม</label>
                    <select name="topping_cat_id" id="new_topping_cat_id" class="form-select rounded-3" required>
                        <?php
                        $stock_cats = $conn->query("SELECT * FROM topping_categories ORDER BY sort_order ASC, topping_cat_name ASC");
                        while ($sc = $stock_cats->fetch_assoc()):
                        ?>
                            <option value="<?= $sc['topping_cat_id'] ?>"><?= htmlspecialchars($sc['topping_cat_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <div class="invalid-feedback">กรุณาเลือกกลุ่ม</div>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold mb-2">ราคาที่บวกเพิ่ม (฿)</label>
                    <input type="number" step="0.01" min="0" name="price" id="new_topping_price" class="form-control rounded-3" value="0.00" required>
                    <div class="invalid-feedback">ราคาต้องเป็นตัวเลขและห้ามติดลบ</div>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold mb-2">จำนวนเริ่มต้นในคลังสินค้า</label>
                    <input type="number" step="1" min="0" name="stock_qty" id="new_topping_stock_qty" class="form-control rounded-3" value="50" required>
                    <div class="invalid-feedback">จำนวนต้องเป็นจำนวนเต็มและห้ามติดลบ</div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<script>
function adjustStock(itemId, change) {
    const display = document.getElementById('stock_display_' + itemId);
    let current = parseInt(display.innerText) || 0;
    let nextVal = Math.max(0, current + change);
    display.innerText = nextVal;

    const formData = new FormData();
    formData.append('action', 'quick_adjust');
    formData.append('item_id', itemId);
    formData.append('change', change);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('manage_stock.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            display.innerText = data.new_qty;
        }
    })
    .catch(err => console.error(err));
}

function adjustToppingStock(toppingId, change) {
    const display = document.getElementById('top_stock_display_' + toppingId);
    let current = parseInt(display.innerText) || 0;
    let nextVal = Math.max(0, current + change);
    display.innerText = nextVal;

    const formData = new FormData();
    formData.append('action', 'quick_adjust_topping');
    formData.append('topping_id', toppingId);
    formData.append('change', change);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('manage_stock.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            display.innerText = data.new_qty;
        }
    })
    .catch(err => console.error(err));
}

function toggleFeatured(itemId, newFeatured) {
    const formData = new FormData();
    formData.append('update_featured_id', itemId);
    formData.append('new_featured_val', newFeatured);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('api_toggle_featured.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(data => {
        if (data.trim() === 'success') {
            const btn = document.getElementById('star-btn-' + itemId);
            if (newFeatured === 1) {
                btn.className = 'star-btn star-active';
                btn.setAttribute('onclick', 'toggleFeatured(' + itemId + ', 0)');
                btn.title = 'เมนูแนะนำ (กดเพื่อยกเลิก)';
            } else {
                btn.className = 'star-btn';
                btn.setAttribute('onclick', 'toggleFeatured(' + itemId + ', 1)');
                btn.title = 'ติดดาวเป็นเมนูแนะนำ';
            }
        } else {
            ownerNotify('เกิดข้อผิดพลาด บันทึกไม่สำเร็จ', 'error');
        }
    })
    .catch(err => console.error(err));
}

function toggleToppingStock(toppingId, currentStatus) {
    const formData = new FormData();
    formData.append('action', 'toggle_topping');
    formData.append('topping_id', toppingId);
    formData.append('current_status', currentStatus);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('manage_stock.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            window.location.reload();
        }
    })
    .catch(err => console.error(err));
}

// เปิดหลังจากรีโหลด ให้กลับไปอยู่แท็บ "ท็อปปิ้ง & วัตถุดิบเสริม" ต่อ (ไม่กระโดดกลับไปแท็บเมนูอาหาร)
document.addEventListener('DOMContentLoaded', function () {
    if (sessionStorage.getItem('stockActiveTab') === 'toppings') {
        sessionStorage.removeItem('stockActiveTab');
        const trigger = document.getElementById('toppings-tab');
        if (trigger) new bootstrap.Tab(trigger).show();
    }
});

function openAddToppingStockModal() {
    const form = document.getElementById('newToppingStockForm');
    form.reset();
    form.classList.remove('was-validated');
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    document.getElementById('new_topping_price').value = '0.00';
    document.getElementById('new_topping_stock_qty').value = 50;
    new bootstrap.Modal(document.getElementById('addToppingStockModal')).show();
}

document.getElementById('newToppingStockForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const nameInput = document.getElementById('new_topping_name');
    const catInput = document.getElementById('new_topping_cat_id');
    const priceInput = document.getElementById('new_topping_price');
    const qtyInput = document.getElementById('new_topping_stock_qty');
    let valid = true;

    if (!nameInput.value.trim()) { nameInput.classList.add('is-invalid'); valid = false; } else { nameInput.classList.remove('is-invalid'); }
    if (!catInput.value) { catInput.classList.add('is-invalid'); valid = false; } else { catInput.classList.remove('is-invalid'); }
    if (priceInput.value === '' || parseFloat(priceInput.value) < 0) { priceInput.classList.add('is-invalid'); valid = false; } else { priceInput.classList.remove('is-invalid'); }
    if (qtyInput.value === '' || !Number.isInteger(Number(qtyInput.value)) || Number(qtyInput.value) < 0) { qtyInput.classList.add('is-invalid'); valid = false; } else { qtyInput.classList.remove('is-invalid'); }

    if (!valid) return;

    const formData = new FormData(this);
    formData.append('action', 'add_topping');
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('manage_stock.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addToppingStockModal')).hide();
            sessionStorage.setItem('stockActiveTab', 'toppings');
            ownerNotify('เพิ่มท็อปปิ้ง/วัตถุดิบเรียบร้อยแล้ว');
            setTimeout(() => window.location.reload(), 700);
        } else {
            ownerNotify(data.error || 'เกิดข้อผิดพลาด ไม่สามารถบันทึกได้', 'error');
        }
    })
    .catch(() => ownerNotify('เกิดข้อผิดพลาด ไม่สามารถบันทึกได้', 'error'));
});
</script>

<?php include '../includes/footer_owner.php'; ?>
