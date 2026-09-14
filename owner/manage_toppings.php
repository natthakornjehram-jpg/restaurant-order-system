<?php
// owner/manage_toppings.php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';
require_once '../includes/csrf.php';

// --- 1. จัดการหมวดหมู่ + ตัวเลือกย่อยทั้งหมดในหมวดนั้น รวมในหน้าจอเดียว (สร้าง/แก้ไข/ลบตัวเลือกย่อยได้พร้อมกัน) ---
if (isset($_POST['save_category_group'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        header("Location: manage_toppings.php"); exit();
    }
    $cat_name = trim($_POST['topping_cat_name'] ?? '');
    $cat_id = isset($_POST['cat_id']) ? intval($_POST['cat_id']) : 0;

    if ($cat_name === '') {
        $_SESSION['error_msg'] = "กรุณากรอกชื่อหมวดหมู่";
        header("Location: manage_toppings.php"); exit();
    }

    if ($cat_id > 0) {
        $stmt = $conn->prepare("UPDATE topping_categories SET topping_cat_name = ? WHERE topping_cat_id = ?");
        $stmt->bind_param("si", $cat_name, $cat_id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO topping_categories (topping_cat_name) VALUES (?)");
        $stmt->bind_param("s", $cat_name);
        $stmt->execute();
        $cat_id = $conn->insert_id;
    }

    // ลบตัวเลือกย่อยที่ถูกกดถังขยะออกจากหน้าจอนี้ (กันลบถ้าเคยถูกใช้ในคำสั่งซื้อจริงแล้ว เหมือนตอนลบทีละตัว)
    $blocked_deletes = [];
    foreach (($_POST['deleted_topping_ids'] ?? []) as $del_id) {
        $del_id = intval($del_id);
        if ($del_id <= 0) continue;

        $check = $conn->prepare("SELECT COUNT(*) AS cnt FROM orderdetail_topping WHERE topping_id = ?");
        $check->bind_param("i", $del_id);
        $check->execute();
        $used = (int) $check->get_result()->fetch_assoc()['cnt'];

        if ($used > 0) {
            $name_stmt = $conn->prepare("SELECT topping_name FROM topping WHERE topping_id = ?");
            $name_stmt->bind_param("i", $del_id);
            $name_stmt->execute();
            $nm = $name_stmt->get_result()->fetch_assoc();
            $blocked_deletes[] = $nm ? $nm['topping_name'] : "#$del_id";
            continue;
        }

        $del_link = $conn->prepare("DELETE FROM menu_toppings WHERE topping_id = ?");
        $del_link->bind_param("i", $del_id);
        $del_link->execute();
        $del_stmt = $conn->prepare("DELETE FROM topping WHERE topping_id = ?");
        $del_stmt->bind_param("i", $del_id);
        $del_stmt->execute();
    }

    // เพิ่ม/แก้ไขตัวเลือกย่อยแต่ละแถว (แถวที่ไม่ได้กรอกชื่อจะข้ามไปเฉยๆ ไม่บันทึก)
    $row_ids = $_POST['row_topping_id'] ?? [];
    $row_names = $_POST['row_topping_name'] ?? [];
    $row_prices = $_POST['row_price'] ?? [];
    foreach ($row_names as $idx => $r_name) {
        $r_name = trim($r_name);
        if ($r_name === '') continue;
        $r_price = max(0, floatval($row_prices[$idx] ?? 0));
        $r_id = intval($row_ids[$idx] ?? 0);

        if ($r_id > 0) {
            $u = $conn->prepare("UPDATE topping SET topping_name = ?, price = ? WHERE topping_id = ?");
            $u->bind_param("sdi", $r_name, $r_price, $r_id);
            $u->execute();
        } else {
            // ตัวเลือกย่อยที่เพิ่มจากหน้าจอนี้ ปิดติดตามคลังสินค้าไว้เป็นค่าเริ่มต้นเสมอ (ของแบบ "ไม่มีวันหมด" เช่น ระดับความเผ็ด)
            $i = $conn->prepare("INSERT INTO topping (topping_name, topping_cat_id, price, use_stock, is_active) VALUES (?, ?, ?, 0, 1)");
            $i->bind_param("sid", $r_name, $cat_id, $r_price);
            $i->execute();
        }
    }

    if (!empty($blocked_deletes)) {
        $_SESSION['error_msg'] = "บันทึกข้อมูลแล้ว แต่ไม่สามารถลบ: " . implode(', ', $blocked_deletes) . " เพราะเคยถูกใช้ในคำสั่งซื้อแล้ว";
    } else {
        $_SESSION['success_msg'] = "บันทึกหมวดหมู่เรียบร้อยแล้ว";
    }
    header("Location: manage_toppings.php"); exit();
}

// --- 2. จัดการข้อมูลตัวเลือกเสริม (เพิ่ม/แก้ไข) ---
if (isset($_POST['save_topping'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        header("Location: manage_toppings.php"); exit();
    }
    $name = trim($_POST['topping_name']);
    $cat_id = intval($_POST['topping_cat_id']);
    $price = floatval($_POST['price']);
    $use_stock = isset($_POST['use_stock']) ? 1 : 0;
    $stock_qty = max(0, intval($_POST['stock_qty'] ?? 50));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $t_id = isset($_POST['topping_id']) ? intval($_POST['topping_id']) : 0;

    // ตรวจสอบข้อมูลฝั่งเซิร์ฟเวอร์ (กันกรณี validation ฝั่ง JS ถูกข้าม)
    if ($name === '' || $cat_id <= 0 || $price < 0) {
        $_SESSION['error_msg'] = "กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้องก่อนบันทึก";
        header("Location: manage_toppings.php"); exit();
    }

    if ($t_id > 0) {
        // แก้ไข
        $stmt = $conn->prepare("UPDATE topping SET topping_name = ?, topping_cat_id = ?, price = ?, use_stock = ?, stock_qty = ?, is_active = ? WHERE topping_id = ?");
        $stmt->bind_param("sidiiii", $name, $cat_id, $price, $use_stock, $stock_qty, $is_active, $t_id);
    } else {
        // เพิ่มใหม่
        $stmt = $conn->prepare("INSERT INTO topping (topping_name, topping_cat_id, price, use_stock, stock_qty, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sidiii", $name, $cat_id, $price, $use_stock, $stock_qty, $is_active);
    }
    $stmt->execute();
    $_SESSION['success_msg'] = ($t_id > 0) ? "แก้ไขตัวเลือกเสริมเรียบร้อยแล้ว" : "เพิ่มตัวเลือกเสริมเรียบร้อยแล้ว";
    header("Location: manage_toppings.php"); exit();
}

// --- 2.1 สลับสถานะเปิด/ปิดขาย (Toggle ในหน้ารายการ ผ่าน AJAX) ---
if (isset($_POST['action']) && $_POST['action'] === 'toggle_active') {
    header('Content-Type: application/json');
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit();
    }
    $id = intval($_POST['topping_id']);
    $new_status = !empty($_POST['new_status']) ? 1 : 0;
    $stmt = $conn->prepare("UPDATE topping SET is_active = ? WHERE topping_id = ?");
    $stmt->bind_param("ii", $new_status, $id);
    $ok = $stmt->execute();
    echo json_encode(['success' => $ok]);
    exit();
}

// --- 2.2 ย้ายลำดับการแสดงหมวดหมู่ขึ้น/ลง (ผ่าน AJAX) ---
if (isset($_POST['action']) && $_POST['action'] === 'reorder_category') {
    header('Content-Type: application/json');
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit();
    }
    $cat_id = intval($_POST['cat_id'] ?? 0);
    $direction = $_POST['direction'] ?? '';

    $list_res = $conn->query("SELECT topping_cat_id FROM topping_categories ORDER BY sort_order ASC, topping_cat_name ASC");
    $ordered_ids = [];
    while ($r = $list_res->fetch_assoc()) { $ordered_ids[] = (int) $r['topping_cat_id']; }

    $pos = array_search($cat_id, $ordered_ids, true);
    $swap_pos = $direction === 'up' ? $pos - 1 : ($direction === 'down' ? $pos + 1 : null);

    if ($pos === false || $swap_pos === null || $swap_pos < 0 || $swap_pos >= count($ordered_ids)) {
        echo json_encode(['success' => false, 'error' => 'ย้ายลำดับต่อไม่ได้แล้ว']);
        exit();
    }

    // สลับตำแหน่งกันในอาร์เรย์ แล้วเขียน sort_order ใหม่ทั้งหมดให้เรียงต่อเนื่องเสมอ
    // (กันปัญหาค่าเดิมซ้ำ/ไม่ต่อเนื่องจากการย้ายครั้งก่อนๆ)
    [$ordered_ids[$pos], $ordered_ids[$swap_pos]] = [$ordered_ids[$swap_pos], $ordered_ids[$pos]];

    $upd = $conn->prepare("UPDATE topping_categories SET sort_order = ? WHERE topping_cat_id = ?");
    foreach ($ordered_ids as $i => $id) {
        $upd->bind_param("ii", $i, $id);
        $upd->execute();
    }

    echo json_encode(['success' => true]);
    exit();
}

// --- 3. ลบข้อมูล (ต้องผ่าน popup ยืนยันฝั่งหน้าบ้านก่อนเสมอ) ---
// เดิมเป็นลิงก์ GET (?delete_id=) ไม่มี CSRF token เลย เปลี่ยนเป็น POST + ตรวจ CSRF token
if (isset($_POST['delete_id'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_msg'] = "คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
        header("Location: manage_toppings.php"); exit();
    }
    $id = intval($_POST['delete_id']);

    // กันการลบตัวเลือกเสริมที่เคยถูกใช้ในคำสั่งซื้อจริงแล้ว (รักษาความถูกต้องของประวัติการขาย)
    $check = $conn->prepare("SELECT COUNT(*) AS cnt FROM orderdetail_topping WHERE topping_id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $used_in_orders = (int) $check->get_result()->fetch_assoc()['cnt'];

    if ($used_in_orders > 0) {
        $_SESSION['error_msg'] = "ไม่สามารถลบได้ เนื่องจากตัวเลือกเสริมนี้เคยถูกใช้ในคำสั่งซื้อแล้ว";
    } else {
        // ลบความสัมพันธ์กับเมนูออกก่อน (ไม่กระทบประวัติออเดอร์เก่า)
        $del_link = $conn->prepare("DELETE FROM menu_toppings WHERE topping_id = ?");
        $del_link->bind_param("i", $id);
        $del_link->execute();

        $del_stmt = $conn->prepare("DELETE FROM topping WHERE topping_id = ?");
        $del_stmt->bind_param("i", $id);
        $del_stmt->execute();
        $_SESSION['success_msg'] = "ลบตัวเลือกเสริมเรียบร้อยแล้ว";
    }
    header("Location: manage_toppings.php"); exit();
}

include '../includes/header_owner.php';
include '../includes/nav_owner.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/owner-manage-toppings.css?v=<?= time() ?>">

<div class="container py-3 py-md-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-white rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; border: 1px solid #edf2f7; background: #ffffff; color: #4a5568;" title="ย้อนกลับ">
                <i class="bi bi-arrow-left fs-4"></i>
            </a>
            <h4 class="fw-bold text-dark m-0" style="font-size: 1.25rem;"><i class="bi bi-egg-fried text-warning me-2"></i>จัดการตัวเลือกเสริม</h4>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary rounded-pill px-4 fw-bold" onclick="openCatModal()">+ หมวดหมู่</button>
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="openToppingModal()">+ เพิ่มตัวเลือกเสริม</button>
        </div>
    </div>

    <?php
    $cats_query = $conn->query("SELECT * FROM topping_categories ORDER BY sort_order ASC, topping_cat_name ASC");
    $all_cats_list = [];
    while ($c = $cats_query->fetch_assoc()) { $all_cats_list[] = $c; }
    $cat_count = count($all_cats_list);

    foreach ($all_cats_list as $cat_index => $cat):
        $current_cat_id = $cat['topping_cat_id'];

        $stmt_items = $conn->prepare("SELECT * FROM topping WHERE topping_cat_id = ? ORDER BY price ASC");
        $stmt_items->bind_param("i", $current_cat_id);
        $stmt_items->execute();
        $items_res = $stmt_items->get_result();
        $cat_toppings = [];
        while ($row = $items_res->fetch_assoc()) { $cat_toppings[] = $row; }

        // ข้อมูลย่อ (id, ชื่อ, ราคา) ส่งให้ JS ใช้เปิดหน้าจอ "แก้ไขหมวดนี้" พร้อมแถวตัวเลือกย่อยเดิมทันที ไม่ต้องยิง AJAX แยก
        $cat_toppings_json = json_encode(array_map(function ($t) {
            return ['id' => $t['topping_id'], 'name' => $t['topping_name'], 'price' => $t['price']];
        }, $cat_toppings), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS);
        $cat_name_json = json_encode($cat['topping_cat_name'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS);
    ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-light border-0 py-3 ps-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold m-0 text-dark"><?= htmlspecialchars($cat['topping_cat_name']) ?></h5>
            <div class="d-flex align-items-center gap-1">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; padding: 0;"
                        onclick="moveCategory(<?= $current_cat_id ?>, 'up')" <?= $cat_index === 0 ? 'disabled' : '' ?> title="ย้ายขึ้น">
                    <i class="bi bi-arrow-up"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; padding: 0;"
                        onclick="moveCategory(<?= $current_cat_id ?>, 'down')" <?= $cat_index === $cat_count - 1 ? 'disabled' : '' ?> title="ย้ายลง">
                    <i class="bi bi-arrow-down"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary rounded-pill ms-1"
                        onclick='openCatModal(<?= $current_cat_id ?>, <?= $cat_name_json ?>, <?= $cat_toppings_json ?>)'>
                    <i class="bi bi-pencil-square"></i> แก้ไขหมวดนี้
                </button>
            </div>
        </div>
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4 py-2 small" style="width: 34%;">ชื่อตัวเลือกเสริม</th>
                    <th class="py-2 small" style="width: 20%;">ราคาที่บวกเพิ่ม</th>
                    <th class="text-center py-2 small" style="width: 16%;">เปิดขาย</th>
                    <th class="text-center py-2 small" style="width: 30%;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($cat_toppings as $row):
                    $is_in_stock = $row['is_active'] == 1;
                    // json_encode + htmlspecialchars (ไม่ใช่ htmlspecialchars อย่างเดียว) เพราะค่านี้ถูกใส่ใน onclick="..."
                    // เป็นสตริง JS ด้วย - htmlspecialchars(ENT_QUOTES) เข้ารหัส ' เป็น &#039; ซึ่งเบราว์เซอร์จะถอดรหัส
                    // HTML entity กลับเป็น ' ก่อนส่งให้ JS parser เสมอ ทำให้หลุดออกจากสตริง JS ได้อยู่ดีถ้าชื่อมี '
                    $t_name_js = htmlspecialchars(json_encode($row['topping_name'], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                ?>
                <tr id="row-<?= $row['topping_id'] ?>" class="<?= !$is_in_stock ? 'out-of-stock' : '' ?>">
                    <td class="ps-4 fw-bold topping-name">
                        <?= htmlspecialchars($row['topping_name']) ?>
                        <?php if (!empty($row['use_stock'])): ?>
                            <i class="bi bi-box-seam text-secondary ms-1" style="font-size: 0.8rem;" title="ติดตามคลังสินค้าอยู่ (ดูจำนวนคงเหลือได้ที่หน้าจัดการคลังสินค้า)"></i>
                        <?php endif; ?>
                    </td>
                    <td class="text-success fw-bold">+<?= number_format($row['price'], 2) ?> บาท</td>
                    <td class="text-center">
                        <div class="form-check form-switch d-inline-block m-0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="active_<?= $row['topping_id'] ?>"
                                   onchange="toggleToppingActive(<?= $row['topping_id'] ?>, this.checked)"
                                   <?= $is_in_stock ? 'checked' : '' ?>>
                        </div>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1"
                                onclick="openToppingModal(<?= $row['topping_id'] ?>, <?= $t_name_js ?>, <?= $row['price'] ?>, <?= $current_cat_id ?>, <?= !empty($row['use_stock']) ? 1 : 0 ?>, <?= (int)$row['stock_qty'] ?>, <?= (int)$row['is_active'] ?>)">
                            <i class="bi bi-pencil-square"></i> แก้ไข
                        </button>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="confirmDeleteTopping(<?= $row['topping_id'] ?>, <?= $t_name_js ?>)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content border-0 rounded-4" method="POST" id="catGroupForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="fw-bold m-0" id="catModalTitle">เพิ่มหมวดหมู่ใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <input type="hidden" name="cat_id" id="cat_id">
                <div class="mb-3">
                    <label class="small fw-bold mb-2">ชื่อหมวดหมู่</label>
                    <input type="text" name="topping_cat_name" id="cat_name" class="form-control rounded-3" placeholder="เช่น ระดับความเผ็ด" required>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="small fw-bold m-0">ตัวเลือกย่อยในหมวดนี้</label>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" onclick="addSubOptionRow()">
                        <i class="bi bi-plus-circle me-1"></i>เพิ่ม
                    </button>
                </div>
                <div id="subOptionsContainer"></div>
                <div class="form-text mb-0">ใส่ 0 ถ้าตัวเลือกนั้นราคาเท่าเดิม ไม่บวกเพิ่ม — ตัวเลือกย่อยที่เพิ่มจากตรงนี้จะไม่ถูกนับคลังสินค้า เหมาะกับของที่ไม่มีวันหมด เช่น ระดับความเผ็ด ขนาดจาน (ถ้าต้องการนับคลังสินค้า ให้ไปเพิ่มที่หน้าจัดการคลังสินค้าแทน)</div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" name="save_category_group" class="btn btn-primary rounded-pill px-4 fw-bold">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="toppingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 rounded-4" method="POST" id="toppingForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="fw-bold m-0" id="toppingModalTitle">จัดการตัวเลือกเสริม</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <input type="hidden" name="topping_id" id="t_id">

                <div class="mb-3">
                    <label class="small fw-bold mb-2">ชื่อตัวเลือกเสริม</label>
                    <input type="text" name="topping_name" id="t_name" class="form-control rounded-3" required>
                    <div class="invalid-feedback">กรุณากรอกชื่อตัวเลือกเสริม</div>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold mb-2">กลุ่มตัวเลือกเสริม</label>
                    <select name="topping_cat_id" id="t_cat_id" class="form-select rounded-3" required>
                        <?php
                        $cats = $conn->query("SELECT * FROM topping_categories ORDER BY sort_order ASC, topping_cat_name ASC");
                        while($c = $cats->fetch_assoc()):
                        ?>
                            <option value="<?= $c['topping_cat_id'] ?>"><?= htmlspecialchars($c['topping_cat_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <div class="invalid-feedback">กรุณาเลือกกลุ่มตัวเลือกเสริม</div>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold mb-2">ราคา (฿)</label>
                    <input type="number" step="0.01" min="0" name="price" id="t_price" class="form-control rounded-3" value="0.00" required>
                    <div class="invalid-feedback">ราคาต้องเป็นตัวเลขและห้ามติดลบ</div>
                </div>

                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="use_stock" id="t_use_stock" onchange="toggleStockQtyField()">
                    <label class="form-check-label small fw-bold" for="t_use_stock">
                        ติดตามคลังสินค้าสำหรับตัวเลือกนี้
                    </label>
                    <div class="form-text">เปิดตัวเลือกที่มีจำนวนจำกัด เช่น เนื้อสัตว์ ไข่ ไม่ต้องเปิดตัวเลือกที่ไม่มีจำนวนจำกัด เช่น ระดับความเผ็ด ขนาดจาน</div>
                </div>

                <div class="mb-3" id="t_stock_qty_wrap" style="display: none;">
                    <label class="small fw-bold mb-2">จำนวนเริ่มต้นในคลังสินค้า</label>
                    <input type="number" step="1" min="0" name="stock_qty" id="t_stock_qty" class="form-control rounded-3" value="50">
                    <div class="invalid-feedback">จำนวนต้องเป็นจำนวนเต็มและห้ามติดลบ</div>
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" id="t_is_active" checked>
                    <label class="form-check-label small fw-bold" for="t_is_active">เปิดขาย</label>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" name="save_topping" class="btn btn-primary rounded-pill px-4 fw-bold">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" action="manage_toppings.php" id="deleteToppingForm" class="d-none">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="delete_id" id="delete_topping_id">
</form>

<script src="<?= BASE_URL ?>assets/js/owner-manage-toppings.js?v=<?= time() ?>"></script>

<?php include '../includes/footer_owner.php'; ?>
<?php include '../includes/owner_flash.php'; ?>