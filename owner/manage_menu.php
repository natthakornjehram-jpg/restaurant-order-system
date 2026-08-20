<?php 
// manage_menu.php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';

// การบันทึกเมนูและสลับสถานะพร้อมขาย แยกไปอยู่ที่ api_save_menu.php / api_toggle_menu_status.php แล้ว
// (มีการ include upload_helper.php เฉพาะที่ api_save_menu.php ที่ต้องใช้)

// --- ลบเมนู ---
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $img_query = $conn->query("SELECT image_url FROM item WHERE item_id = $id");
    if ($img_row = $img_query->fetch_assoc()) {
        if (!empty($img_row['image_url']) && $img_row['image_url'] != 'default_food.jpg.' && file_exists("../assets/images/items/" . $img_row['image_url'])) {
            unlink("../assets/images/items/" . $img_row['image_url']);
        }
        $conn->query("DELETE FROM menu_toppings WHERE item_id = $id");
        $conn->query("DELETE FROM item WHERE item_id = $id");
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

include '../includes/header_owner.php'; 
include '../includes/nav_owner.php'; 
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/owner-manage-menu.css">

<div class="main-content container-fluid p-4 dashboard-spacing text-dark" style="margin-top: 60px;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <h2 class="fw-bold text-dark m-0"><i class="bi bi-folder-fill text-warning me-2"></i>เมนูอาหารในร้าน</h2>
        <div class="d-flex flex-column flex-sm-row gap-2">
            <a href="manage_categories.php" class="btn btn-outline-dark rounded-pill px-4 fw-bold shadow-sm">จัดการหมวดหมู่</a>
            <button class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addMenuModal">
                <i class="bi bi-plus-circle-fill me-1"></i>เพิ่มเมนูใหม่
            </button>
        </div>
    </div>
    </div>

    <div class="accordion" id="menuAccordion">
        <?php 
        $is_first = true;
        foreach($all_categories as $cat): 
            $cat_id = $cat['category_id'];
            $cat_menus = $conn->query("SELECT * FROM item WHERE category_id = $cat_id ORDER BY name ASC");
            $show_class = $is_first ? 'show' : '';
            $collapsed_class = $is_first ? '' : 'collapsed';
            $is_first = false;
        ?>
        <div class="accordion-item folder-card overflow-hidden">
            <h2 class="accordion-header">
                <button class="accordion-button bg-white text-dark <?php echo $collapsed_class; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#cat_collapse_<?php echo $cat_id; ?>">
                    <i class="bi bi-folder2-open me-3 text-primary"></i> <?php echo $cat['category_name']; ?>
                    <span class="ms-auto badge bg-light text-dark rounded-pill px-4"><?php echo $cat_menus ? $cat_menus->num_rows : 0; ?> รายการ</span>
                </button>
            </h2>
            <div id="cat_collapse_<?php echo $cat_id; ?>" class="accordion-collapse collapse <?php echo $show_class; ?>" data-bs-parent="#menuAccordion">
                <div class="accordion-body p-0 text-dark">
                    <?php if($cat_menus && $cat_menus->num_rows > 0): while($menu = $cat_menus->fetch_assoc()): ?>
                       <div class="menu-item-row text-dark p-3 p-md-4 border-bottom" style="display: block;">
                            
                            <div class="d-flex align-items-start mb-3">
                                <img src="uploads/menu_images/<?php echo !empty($menu['image_url']) ? $menu['image_url'] : 'default_food.jpg'; ?>" 
                                     class="rounded-4 me-3 shadow-sm border" 
                                     style="width: 85px; height: 85px; min-width: 85px; object-fit: cover;" 
                                     onerror="this.src='../assets/images/items/default_food.jpg'">
                                
                                <div class="flex-grow-1">
                                    <div class="fw-bold fs-5 mb-1 text-dark lh-1"><?php echo $menu['name']; ?></div>
                                    <div class="mb-2 d-flex flex-wrap gap-1">
                                        <?php 
                                        $m_id = $menu['item_id'];
                                        $show_tops = $conn->query("SELECT t.topping_name, t.price FROM menu_toppings mt JOIN topping t ON mt.topping_id = t.topping_id WHERE mt.item_id = $m_id");
                                        if($show_tops && $show_tops->num_rows > 0):
                                            while($st = $show_tops->fetch_assoc()):
                                        ?>
                                            <span class="badge bg-light text-secondary border rounded-pill fw-normal" style="font-size: 0.75rem;">+ <?php echo $st['topping_name']; ?> (฿<?php echo number_format($st['price']); ?>)</span>
                                        <?php endwhile; endif; ?>
                                    </div>
                                    <button type="button" id="status-btn-<?php echo $menu['item_id']; ?>" 
                                            class="status-btn <?php echo ($menu['is_active'] == 1) ? 'status-green' : 'status-gray'; ?> py-1 px-3" 
                                            onclick="changeStatus(<?php echo $menu['item_id']; ?>, <?php echo ($menu['is_active'] == 1) ? 0 : 1; ?>)"
                                            style="font-size: 0.8rem;">
                                        ● <?php echo ($menu['is_active'] == 1) ? 'พร้อมขาย' : 'ไม่พร้อมขาย'; ?>
                                    </button>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center bg-light rounded-4 p-2 px-3 border border-light">
                                <div class="fw-bold text-dark fs-4 m-0">฿<?php echo number_format($menu['price'], 0); ?></div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-warning rounded-pill px-3 btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#editMenu_<?php echo $menu['item_id']; ?>">แก้ไข</button>
                                    <a href="?delete_id=<?php echo $menu['item_id']; ?>" class="btn btn-outline-danger rounded-pill px-3 btn-sm fw-bold shadow-sm" onclick="return confirm('ต้องการลบเมนูนี้ใช่ไหม?')">ลบ</a>
                                </div>
                            </div>
                            
                        </div>

                        <div class="modal fade" id="editMenu_<?php echo $menu['item_id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <form class="modal-content" method="POST" action="api_save_menu.php" enctype="multipart/form-data">
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
                                            <div class="col-md-8"><label class="fw-bold mb-2">ชื่อเมนู</label><input type="text" name="menu_name" class="form-control form-control-lg" value="<?php echo $menu['name']; ?>" required></div>
                                            <div class="col-md-4"><label class="fw-bold mb-2">ราคา</label><input type="number" name="price" class="form-control form-control-lg" value="<?php echo $menu['price']; ?>" required></div>
                                            <div class="col-12"><label class="fw-bold mb-2">หมวดหมู่</label>
                                                <select name="category_id" class="form-select form-select-lg">
                                                    <?php foreach($all_categories as $c): ?>
                                                        <option value="<?php echo $c['category_id']; ?>" <?php echo ($c['category_id'] == $menu['category_id']) ? 'selected' : ''; ?>><?php echo $c['category_name']; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="topping-section-label">2. เลือกท็อปปิ้งเสริม</div>
                                        <div class="topping-group-box">
                                            <?php 
                                            $my_tops = [];
                                            $check_res = $conn->query("SELECT topping_id FROM menu_toppings WHERE item_id = ".$menu['item_id']);
                                            if($check_res) {
                                                while($mt = $check_res->fetch_assoc()) { $my_tops[] = $mt['topping_id']; }
                                            }

                                            $top_cats_edit = $conn->query("SELECT * FROM topping_categories ORDER BY topping_cat_id ASC");
                                            if($top_cats_edit): while($t_cat = $top_cats_edit->fetch_assoc()):
                                                $tops = $conn->query("SELECT * FROM topping WHERE topping_cat_id = ".$t_cat['topping_cat_id']." AND is_active = 1");
                                                if($tops && $tops->num_rows > 0):
                                                    $group_class_edit = "edit-group-".$menu['item_id']."-".$t_cat['topping_cat_id'];
                                            ?>
                                                <div class="d-flex justify-content-between align-items-center border-bottom mb-2 pb-1">
                                                    <div class="fw-bold text-success small">หมวด: <?php echo $t_cat['topping_cat_name']; ?></div>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="selectAll('<?php echo $group_class_edit; ?>')"> เลือกทั้งหมด</button>
                                                </div>
                                                <div class="row g-2 mb-4">
                                                    <?php while($top = $tops->fetch_assoc()): ?>
                                                    <div class="col-6 col-md-4">
                                                        <label class="custom-option w-100">
                                                            <input type="checkbox" name="topping_ids[]" value="<?php echo $top['topping_id']; ?>" class="<?php echo $group_class_edit; ?>" <?php echo in_array($top['topping_id'], $my_tops) ? 'checked' : ''; ?>>
                                                            <span class="option-btn text-dark"><?php echo $top['topping_name']; ?></span>
                                                        </label>
                                                    </div>
                                                    <?php endwhile; ?>
                                                </div>
                                            <?php endif; endwhile; endif; ?>
                                        </div>
                                        
                                        <div class="col-12 text-center border p-3 rounded-4 bg-light mt-3">
                                            <img src="../assets/images/items/<?php echo !empty($menu['image_url']) ? $menu['image_url'] : 'default_food.jpg'; ?>" class="rounded-4 mb-2 shadow" style="width: 150px; height: 100px; object-fit: cover;" onerror="this.src='../assets/images/items/default_food.jpg'">
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
            <div class="modal-header border-0 pt-4 px-4 text-dark"><h3 class="fw-bold m-0">➕ เพิ่มรายการใหม่</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body px-4 text-start text-dark">
                <div class="section-label">1. ข้อมูลพื้นฐาน</div>
                <div class="row g-4 mb-4">
                    <div class="col-md-8"><label class="fw-bold mb-2">ชื่อเมนู</label><input type="text" name="menu_name" class="form-control form-control-lg" required></div>
                    <div class="col-md-4"><label class="fw-bold mb-2">ราคา</label><input type="number" name="price" class="form-control form-control-lg" required></div>
                    <div class="col-12"><label class="fw-bold mb-2">หมวดหมู่</label>
                        <select name="category_id" class="form-select form-select-lg" required>
                            <?php foreach($all_categories as $c): ?>
                                <option value="<?php echo $c['category_id']; ?>"><?php echo $c['category_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="topping-section-label">2. เลือกท็อปปิ้ง</div>
                    <div class="topping-group-box">
                        <?php 
                        $top_cats_add = $conn->query("SELECT * FROM topping_categories ORDER BY topping_cat_id ASC");
                        if($top_cats_add): while($t_cat = $top_cats_add->fetch_assoc()):
                            $tops = $conn->query("SELECT * FROM topping WHERE topping_cat_id = ".$t_cat['topping_cat_id']." AND is_active = 1");
                            if($tops && $tops->num_rows > 0):
                                $group_class_add = "add-group-".$t_cat['topping_cat_id'];
                        ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom mb-2 pb-1">
                                <div class="fw-bold text-success small">หมวด: <?php echo $t_cat['topping_cat_name']; ?></div>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="selectAll('<?php echo $group_class_add; ?>')"> เลือกทั้งหมด</button>
                            </div>
                            <div class="row g-2 mb-4">
                                <?php while($top = $tops->fetch_assoc()): ?>
                                <div class="col-6 col-md-4">
                                    <label class="custom-option w-100">
                                        <input type="checkbox" name="topping_ids[]" value="<?php echo $top['topping_id']; ?>" class="<?php echo $group_class_add; ?>">
                                        <span class="option-btn text-dark"><?php echo $top['topping_name']; ?></span>
                                    </label>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; endwhile; endif; ?>
                    </div>
                
                <div class="col-12 mt-3"><label class="fw-bold mb-2">รูปภาพอาหาร</label><input type="file" name="image" class="form-control form-control-lg"></div>
            </div>
            <div class="modal-footer border-0 p-4"><button type="submit" name="save_menu" class="btn btn-primary w-100 btn-save">✨ บันทึกเมนูใหม่</button></div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= BASE_URL ?>assets/js/owner-manage-menu.js"></script>

<script>
// ✅ แจ้งเตือนเมื่อบันทึกข้อมูลหรือลบข้อมูลสำเร็จ (ปิดตัวเองอัตโนมัติ)
<?php if (isset($_SESSION['success_msg'])): ?>
    Swal.fire({
        icon: 'success',
        title: '<?php echo $_SESSION['success_msg']; ?>',
        showConfirmButton: false,
        timer: 1500
    });
    <?php unset($_SESSION['success_msg']); ?>
<?php endif; ?>

// ✅ แจ้งเตือนเมื่อมี Error
<?php if (isset($_SESSION['error_msg'])): ?>
    Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด',
        text: '<?php echo $_SESSION['error_msg']; ?>',
    });
    <?php unset($_SESSION['error_msg']); ?>
<?php endif; ?>
</script>

<?php include '../includes/footer_owner.php'; ?>