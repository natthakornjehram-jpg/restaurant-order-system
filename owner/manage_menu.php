<?php 
// manage_menu.php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';
require_once '../includes/csrf.php';

// การบันทึกเมนูและสลับสถานะแนะนำ แยกไปอยู่ที่ api_save_menu.php / api_toggle_featured.php แล้ว
// (มีการ include upload_helper.php เฉพาะที่ api_save_menu.php ที่ต้องใช้)

// --- ลบเมนู ---
// เดิมเป็นลิงก์ GET ธรรมดา (?delete_id=) ไม่มี CSRF token เลย ทำให้หน้าอื่นฝัง <img src="manage_menu.php?delete_id=..">
// แล้วหลอกให้ owner ที่ล็อกอินอยู่ลบเมนูโดยไม่ตั้งใจได้ จึงเปลี่ยนเป็น POST form + ตรวจ CSRF token แทน
if (isset($_POST['delete_id'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_msg'] = "คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
        header("Location: manage_menu.php");
        exit();
    }
    $id = intval($_POST['delete_id']);
    $img_stmt = $conn->prepare("SELECT image_url FROM item WHERE item_id = ?");
    $img_stmt->bind_param("i", $id);
    $img_stmt->execute();
    $img_row = $img_stmt->get_result()->fetch_assoc();
    if ($img_row) {
        if (!empty($img_row['image_url']) && $img_row['image_url'] != 'default_food.jpg' && file_exists("../assets/images/items/" . $img_row['image_url'])) {
            unlink("../assets/images/items/" . $img_row['image_url']);
        }
        $del_top_stmt = $conn->prepare("DELETE FROM menu_toppings WHERE item_id = ?");
        $del_top_stmt->bind_param("i", $id);
        $del_top_stmt->execute();

        $del_item_stmt = $conn->prepare("DELETE FROM item WHERE item_id = ?");
        $del_item_stmt->bind_param("i", $id);
        $del_item_stmt->execute();
    }
    // ✅ เพิ่มแจ้งเตือนตอนลบเมนูสำเร็จด้วย
    $_SESSION['success_msg'] = "ลบรายการอาหารเรียบร้อยแล้ว!";
    header("Location: manage_menu.php");
    exit();
}

$all_categories = [];
$categories_res = $conn->query("SELECT * FROM category ORDER BY category_id ASC");
if($categories_res) {
    while($c = $categories_res->fetch_assoc()){ $all_categories[] = $c; }
}

// --- เตรียมข้อมูล "กลุ่มตัวเลือกเสริม" ไว้ล่วงหน้าครั้งเดียว ใช้ซ้ำได้ทุกโมดัลแก้ไข/เพิ่มเมนู ---
// (แทนที่จะ query รายชื่อท็อปปิ้งซ้ำๆ ในลูปของเมนูแต่ละรายการเหมือนโค้ดเดิม)
$topping_categories_all = [];
$tc_res = $conn->query("SELECT * FROM topping_categories ORDER BY sort_order ASC, topping_cat_name ASC");
if ($tc_res) { while ($tc = $tc_res->fetch_assoc()) { $topping_categories_all[] = $tc; } }

$toppings_by_cat = [];
$top_all_res = $conn->query("SELECT * FROM topping ORDER BY topping_cat_id ASC, is_active DESC, topping_id ASC");
if ($top_all_res) {
    while ($t = $top_all_res->fetch_assoc()) {
        $toppings_by_cat[$t['topping_cat_id']][] = $t;
    }
}

// จำนวนเมนูที่ใช้แต่ละกลุ่มอยู่ (นับเมนูต่างกันที่มีท็อปปิ้งจากกลุ่มนั้นผูกอยู่อย่างน้อย 1 ตัว) สำหรับ badge "ใช้กับ N เมนู"
$cat_usage_count = [];
$usage_res = $conn->query("SELECT t.topping_cat_id, COUNT(DISTINCT mt.item_id) AS used_count FROM topping t JOIN menu_toppings mt ON mt.topping_id = t.topping_id GROUP BY t.topping_cat_id");
if ($usage_res) { while ($u = $usage_res->fetch_assoc()) { $cat_usage_count[$u['topping_cat_id']] = (int) $u['used_count']; } }

/**
 * วาด UI เลือกตัวเลือกเสริมแบบ "การ์ดกลุ่ม" (แทนกริดเช็คบ็อกซ์ยาวๆ แบบเดิม)
 * กลุ่มที่เมนูนี้ผูกอยู่แล้วจะโชว์เปิดไว้เลย ส่วนกลุ่มที่ยังไม่ได้ใช้จะซ่อนไว้ใน
 * ปุ่ม "หยิบกลุ่มตัวเลือกที่เคยสร้างไว้มาใช้" กดแล้วค่อยโผล่มาให้ติ๊กเลือก
 * $scope_key ใช้กันชื่อ id/class ซ้ำกันระหว่างโมดัลของเมนูแต่ละรายการ (เช่น "edit-12", "add")
 */
function render_topping_groups($scope_key, $my_tops, $topping_categories_all, $toppings_by_cat, $cat_usage_count) {
    $linked_cat_ids = [];
    foreach ($topping_categories_all as $tc) {
        $cid = $tc['topping_cat_id'];
        if (!empty($toppings_by_cat[$cid])) {
            foreach ($toppings_by_cat[$cid] as $t) {
                if (in_array($t['topping_id'], $my_tops)) { $linked_cat_ids[] = $cid; break; }
            }
        }
    }
    ?>
    <div class="small text-muted fw-bold mb-2">ตัวเลือกของเมนูนี้ (<?= count($linked_cat_ids) ?>)</div>
    <div id="topping-groups-<?= $scope_key ?>">
        <?php foreach ($topping_categories_all as $tc):
            $cid = $tc['topping_cat_id'];
            if (empty($toppings_by_cat[$cid])) continue; // กลุ่มนี้ไม่มีตัวเลือกอยู่เลย ข้ามไป
            $is_linked = in_array($cid, $linked_cat_ids);
            $group_key = $scope_key . '-' . $cid;
            $names = array_map(function ($t) { return $t['topping_name']; }, $toppings_by_cat[$cid]);
        ?>
        <div class="topping-group-card mb-2 <?= $is_linked ? '' : 'd-none' ?>" id="topping-group-<?= $group_key ?>">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div class="me-2">
                    <div>
                        <span class="fw-bold small"><?= htmlspecialchars($tc['topping_cat_name']) ?></span>
                        <span class="badge bg-light text-secondary rounded-pill ms-1">ใช้กับ <?= $cat_usage_count[$cid] ?? 0 ?> เมนู</span>
                    </div>
                    <div class="text-muted small"><?= htmlspecialchars(implode(', ', $names)) ?></div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0" onclick="toggleGroupEditor('editor-<?= $group_key ?>')">แก้ไขกลุ่มนี้</button>
            </div>
            <div class="topping-group-editor mt-2" id="editor-<?= $group_key ?>" style="display: none;">
                <div class="d-flex justify-content-end mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="selectAll('grp-<?= $group_key ?>')"> เลือกทั้งหมด</button>
                </div>
                <div class="row g-2">
                    <?php foreach ($toppings_by_cat[$cid] as $t): ?>
                    <div class="col-6 col-md-4">
                        <label class="custom-option w-100 <?= empty($t['is_active']) ? 'opacity-50' : '' ?>">
                            <input type="checkbox" name="topping_ids[]" value="<?= $t['topping_id'] ?>" class="grp-<?= $group_key ?>" <?= in_array($t['topping_id'], $my_tops) ? 'checked' : '' ?>>
                            <span class="option-btn text-dark"><?= htmlspecialchars($t['topping_name']) ?><?= empty($t['is_active']) ? ' (ปิดขายอยู่)' : '' ?></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
        $unlinked = array_filter($topping_categories_all, function ($tc) use ($toppings_by_cat, $linked_cat_ids) {
            $cid = $tc['topping_cat_id'];
            return !empty($toppings_by_cat[$cid]) && !in_array($cid, $linked_cat_ids);
        });
    ?>
    <?php if (!empty($unlinked)): ?>
    <div class="dropdown mt-2">
        <button class="btn btn-sm btn-outline-primary rounded-pill w-100" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-plus-circle me-1"></i>หยิบกลุ่มตัวเลือกที่เคยสร้างไว้มาใช้
        </button>
        <ul class="dropdown-menu w-100">
            <?php foreach ($unlinked as $tc): $cid = $tc['topping_cat_id']; $group_key = $scope_key . '-' . $cid; ?>
            <li><button type="button" class="dropdown-item" onclick="attachToppingGroup('topping-group-<?= $group_key ?>')"><?= htmlspecialchars($tc['topping_cat_name']) ?> <span class="text-muted small">(ใช้กับ <?= $cat_usage_count[$cid] ?? 0 ?> เมนู)</span></button></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php else: ?>
        <p class="text-muted small mt-2 mb-0">ใช้ครบทุกกลุ่มตัวเลือกที่มีอยู่แล้ว</p>
    <?php endif; ?>
    <div class="d-flex flex-wrap gap-2 mt-2">
        <a href="manage_toppings.php" class="btn btn-sm btn-outline-success rounded-pill">
            <i class="bi bi-plus-circle me-1"></i>เพิ่มหมวดหมู่/ตัวเลือกใหม่ที่ไม่มีวันหมด (เช่น ระดับความเผ็ด)
        </a>
        <a href="manage_stock.php" class="btn btn-sm btn-outline-primary rounded-pill">
            <i class="bi bi-plus-circle me-1"></i>เพิ่มวัตถุดิบใหม่ที่ต้องนับคลังสินค้า
        </a>
    </div>
    <?php
}

include '../includes/header_owner.php';
include '../includes/nav_owner.php'; 
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/owner-manage-menu.css?v=<?= time() ?>">

<div class="main-content container-fluid p-4 dashboard-spacing text-dark">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-white rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; border: 1px solid #edf2f7; background: #ffffff; color: #4a5568;" title="ย้อนกลับ">
                <i class="bi bi-arrow-left fs-4"></i>
            </a>
            <h4 class="fw-bold text-dark m-0" style="font-size: 1.25rem;"><i class="bi bi-folder-fill text-warning me-2"></i>เมนูอาหารในร้าน</h4>
        </div>
        <div class="d-flex flex-column flex-sm-row gap-2">
            <a href="manage_categories.php" class="btn btn-outline-dark rounded-pill px-4 fw-bold shadow-sm">จัดการหมวดหมู่</a>
            <button class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addMenuModal">
                <i class="bi bi-plus-circle-fill me-1"></i>เพิ่มเมนูใหม่
            </button>
        </div>
    </div>

    <div class="accordion" id="menuAccordion">
        <?php 
        $is_first = true;
        foreach($all_categories as $cat): 
            $cat_id = $cat['category_id'];
            $cat_menus_stmt = $conn->prepare("SELECT * FROM item WHERE category_id = ? ORDER BY name ASC");
            $cat_menus_stmt->bind_param("i", $cat_id);
            $cat_menus_stmt->execute();
            $cat_menus = $cat_menus_stmt->get_result();
            $show_class = $is_first ? 'show' : '';
            $collapsed_class = $is_first ? '' : 'collapsed';
            $is_first = false;
        ?>
        <div class="accordion-item folder-card overflow-hidden">
            <h2 class="accordion-header">
                <button class="accordion-button bg-white text-dark <?php echo $collapsed_class; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#cat_collapse_<?php echo $cat_id; ?>">
                    <i class="bi bi-folder2-open me-3 text-primary"></i> <?php echo htmlspecialchars($cat['category_name']); ?>
                    <span class="ms-auto badge bg-light text-dark rounded-pill px-4"><?php echo $cat_menus ? $cat_menus->num_rows : 0; ?> รายการ</span>
                </button>
            </h2>
            <div id="cat_collapse_<?php echo $cat_id; ?>" class="accordion-collapse collapse <?php echo $show_class; ?>" data-bs-parent="#menuAccordion">
                <div class="accordion-body p-0 text-dark">
                    <?php if($cat_menus && $cat_menus->num_rows > 0): while($menu = $cat_menus->fetch_assoc()): ?>
                       <div class="menu-item-row text-dark p-3 p-md-4 border-bottom" style="display: block;">
                            
                            <div class="d-flex align-items-start mb-3">
                                <img src="../assets/images/items/<?php echo !empty($menu['image_url']) ? htmlspecialchars($menu['image_url']) : 'default_food.jpg'; ?>"
                                     class="rounded-4 me-3 shadow-sm border"
                                     style="width: 85px; height: 85px; min-width: 85px; object-fit: cover;" 
                                     onerror="this.src='../assets/images/items/default_food.jpg'">
                                
                                <div class="flex-grow-1">
                                    <div class="fw-bold fs-5 mb-1 text-dark lh-1"><?php echo htmlspecialchars($menu['name']); ?></div>
                                    <div class="mb-2 d-flex flex-wrap gap-1">
                                        <?php
                                        $m_id = (int)$menu['item_id'];
                                        $show_tops_stmt = $conn->prepare("SELECT t.topping_name, t.price FROM menu_toppings mt JOIN topping t ON mt.topping_id = t.topping_id WHERE mt.item_id = ?");
                                        $show_tops_stmt->bind_param("i", $m_id);
                                        $show_tops_stmt->execute();
                                        $show_tops = $show_tops_stmt->get_result();
                                        if($show_tops && $show_tops->num_rows > 0):
                                            while($st = $show_tops->fetch_assoc()):
                                        ?>
                                            <span class="badge bg-light text-secondary border rounded-pill fw-normal" style="font-size: 0.75rem;">+ <?php echo htmlspecialchars($st['topping_name']); ?> (฿<?php echo number_format($st['price']); ?>)</span>
                                        <?php endwhile; endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center bg-light rounded-4 p-2 px-3 border border-light">
                                <div class="fw-bold text-dark fs-4 m-0">฿<?php echo number_format($menu['price'], 0); ?></div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-warning rounded-pill px-3 btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#editMenu_<?php echo $menu['item_id']; ?>">แก้ไข</button>
                                    <form method="POST" action="manage_menu.php" class="d-inline" onsubmit="return ownerConfirmSubmit(event, 'ยืนยันลบเมนูนี้?');">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="delete_id" value="<?php echo $menu['item_id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger rounded-pill px-3 btn-sm fw-bold shadow-sm">ลบ</button>
                                    </form>
                                </div>
                            </div>
                            
                        </div>

                        <div class="modal fade" id="editMenu_<?php echo $menu['item_id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <form class="modal-content" method="POST" action="api_save_menu.php" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <div class="modal-header border-0 pt-4 px-4 text-dark"><h3 class="fw-bold m-0">✏️ แก้ไขเมนู</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body px-4 text-start text-dark">
                                        <div class="mb-4">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="is_active" id="sw_<?php echo $menu['item_id']; ?>" value="1" <?php echo ($menu['is_active'] == 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label fw-bold ms-3" for="sw_<?php echo $menu['item_id']; ?>">เปิดการขาย (พร้อมสั่งอาหาร)</label>
                                            </div>
                                        </div>
                                        <input type="hidden" name="item_id" value="<?php echo $menu['item_id']; ?>">
                                        <div class="section-label">1. ข้อมูลพื้นฐาน</div>
                                        <div class="row g-4 mb-3">
                                            <div class="col-md-8"><label class="fw-bold mb-2">ชื่อเมนู</label><input type="text" name="menu_name" class="form-control form-control-lg" value="<?= htmlspecialchars($menu['name']) ?>" required></div>
                                            <div class="col-md-4"><label class="fw-bold mb-2">ราคา</label><input type="number" name="price" class="form-control form-control-lg" value="<?php echo $menu['price']; ?>" required></div>
                                            <div class="col-12"><label class="fw-bold mb-2">หมวดหมู่</label>
                                                <select name="category_id" class="form-select form-select-lg">
                                                    <?php foreach($all_categories as $c): ?>
                                                        <option value="<?php echo $c['category_id']; ?>" <?php echo ($c['category_id'] == $menu['category_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($c['category_name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="topping-section-label">2. เลือกท็อปปิ้งเสริม</div>
                                        <div class="topping-group-box">
                                            <?php
                                            $my_tops = [];
                                            $edit_item_id = (int) $menu['item_id'];
                                            $check_stmt = $conn->prepare("SELECT topping_id FROM menu_toppings WHERE item_id = ?");
                                            $check_stmt->bind_param("i", $edit_item_id);
                                            $check_stmt->execute();
                                            $check_res = $check_stmt->get_result();
                                            if ($check_res) {
                                                while ($mt = $check_res->fetch_assoc()) { $my_tops[] = $mt['topping_id']; }
                                            }
                                            render_topping_groups('edit-' . $edit_item_id, $my_tops, $topping_categories_all, $toppings_by_cat, $cat_usage_count);
                                            ?>
                                        </div>

                                        <div class="col-12 text-center border p-3 rounded-4 bg-light mt-3">
                                            <img src="../assets/images/items/<?= !empty($menu['image_url']) ? htmlspecialchars($menu['image_url']) : 'default_food.jpg' ?>" class="rounded-4 mb-2 shadow" style="width: 150px; height: 100px; object-fit: cover;" onerror="this.src='../assets/images/items/default_food.jpg'">
                                            <input type="file" name="image" class="form-control form-control-lg">
                                        </div>
                                    </div>
                                    <div class="modal-footer border-0 p-4"><button type="submit" name="save_menu" class="btn btn-success w-100 btn-save">✅ บันทึกการแก้ไข</button></div>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="addMenuModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" action="api_save_menu.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header border-0 pt-4 px-4 text-dark"><h3 class="fw-bold m-0">➕ เพิ่มรายการใหม่</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body px-4 text-start text-dark">
                <div class="section-label">1. ข้อมูลพื้นฐาน</div>
                <div class="row g-4 mb-4">
                    <div class="col-md-8"><label class="fw-bold mb-2">ชื่อเมนู</label><input type="text" name="menu_name" class="form-control form-control-lg" required></div>
                    <div class="col-md-4"><label class="fw-bold mb-2">ราคา</label><input type="number" name="price" class="form-control form-control-lg" required></div>
                    <div class="col-12"><label class="fw-bold mb-2">หมวดหมู่</label>
                        <select name="category_id" class="form-select form-select-lg" required>
                            <?php foreach($all_categories as $c): ?>
                                <option value="<?php echo $c['category_id']; ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="topping-section-label">2. เลือกท็อปปิ้ง</div>
                    <div class="topping-group-box">
                        <?php render_topping_groups('add', [], $topping_categories_all, $toppings_by_cat, $cat_usage_count); ?>
                    </div>

                <div class="col-12 mt-3"><label class="fw-bold mb-2">รูปภาพอาหาร</label><input type="file" name="image" class="form-control form-control-lg"></div>
            </div>
            <div class="modal-footer border-0 p-4"><button type="submit" name="save_menu" class="btn btn-primary w-100 btn-save">✨ บันทึกเมนูใหม่</button></div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/owner-manage-menu.js?v=<?= time() ?>"></script>

<?php include '../includes/footer_owner.php'; ?>
<?php include '../includes/owner_flash.php'; ?>