<?php 
// manage_menu.php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';
require_once '../includes/upload_helper.php';

// --- 1. AJAX สลับสถานะพร้อมขาย ---
if (isset($_POST['update_status_id'])) {
    $id = intval($_POST['update_status_id']);
    $status = intval($_POST['new_status_val']);
    // ใช้ตาราง item และ is_active
    $stmt = $conn->prepare("UPDATE item SET is_active = ? WHERE item_id = ?");
    $stmt->bind_param("ii", $status, $id);
    $stmt->execute();
    echo "success";
    exit();
}

// --- 2. บันทึก/อัปเดตข้อมูลเมนู ---
if (isset($_POST['save_menu'])) {
    $m_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $name = $_POST['menu_name'];
    $price = (float) $_POST['price'];
    $cat_id = (int) $_POST['category_id'];
    $status = isset($_POST['is_active']) ? 1 : 0;

    $db_image_path = "";
    if (!empty($_FILES['image']['name'])) {
        $uploaded = handle_image_upload($_FILES['image'], "../assets/images/items/", "item");
        if ($uploaded !== false) {
            $db_image_path = $uploaded;
        }
    }

    if ($m_id > 0) {
        if ($db_image_path !== "") {
            $stmt = $conn->prepare("UPDATE item SET name=?, price=?, category_id=?, is_active=?, image_url=? WHERE item_id=?");
            $stmt->bind_param("sdiisi", $name, $price, $cat_id, $status, $db_image_path, $m_id);
        } else {
            $stmt = $conn->prepare("UPDATE item SET name=?, price=?, category_id=?, is_active=? WHERE item_id=?");
            $stmt->bind_param("sdiii", $name, $price, $cat_id, $status, $m_id);
        }
    } else {
        $img = !empty($db_image_path) ? $db_image_path : 'default_food.png';
        $stmt = $conn->prepare("INSERT INTO item (name, price, category_id, image_url, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("sdis", $name, $price, $cat_id, $img);
    }

    if ($stmt->execute()) {
        $target_id = ($m_id > 0) ? $m_id : $conn->insert_id;
        
        // จัดการท็อปปิ้ง
        $conn->query("DELETE FROM menu_toppings WHERE item_id = $target_id");
        if (!empty($_POST['topping_ids'])) {
            foreach ($_POST['topping_ids'] as $t_id) {
                $conn->query("INSERT INTO menu_toppings (item_id, topping_id) VALUES ($target_id, ".intval($t_id).")");
            }
        }
        // ✅ เปลี่ยนจากการใช้ echo script alert เป็นการส่งค่าเข้า Session แทน
        $_SESSION['success_msg'] = "บันทึกข้อมูลเรียบร้อย!";
        header("Location: manage_menu.php");
        exit;
    } else {
        $_SESSION['error_msg'] = "เกิดข้อผิดพลาด: " . $conn->error;
        header("Location: manage_menu.php");
        exit;
    }
}

// --- 3. ลบเมนู ---
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

<style>
    body { background-color: #f0f2f5; font-family: 'Sarabun', sans-serif; }
    .folder-card { background: #fff; border-radius: 20px; border: none; box-shadow: 0 4px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
    .accordion-button { font-size: 1.3rem; font-weight: 800; padding: 25px; color: #1a1a1a; border-radius: 20px !important; }
    .menu-item-row { border-top: 1px solid #eee; padding: 20px; display: flex; align-items: center; }
    .modal-content { border-radius: 30px; border: none; }
    .section-label { background: #e7f1ff; color: #0d6efd; padding: 8px 20px; border-radius: 50px; font-weight: bold; display: inline-block; margin-bottom: 15px; }
    .topping-section-label { background: #e6fcf5; color: #0ca678; padding: 8px 20px; border-radius: 50px; font-weight: bold; display: inline-block; margin-top: 20px; margin-bottom: 10px; }
    .topping-group-box { background: #f8f9fa; border: 2px solid #eee; border-radius: 20px; padding: 20px; margin-bottom: 20px; }
    .form-control-lg, .form-select-lg { border-radius: 15px; border: 2px solid #dee2e6; font-size: 1.1rem; }
    .btn-save { font-size: 1.3rem; padding: 15px; border-radius: 20px; font-weight: bold; }
    .custom-option input[type="checkbox"] { display: none; }
    .custom-option .option-btn {
        display: inline-block; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px;
        background-color: #ffffff; color: #4b5563; cursor: pointer; transition: all 0.2s ease-in-out;
        user-select: none; width: 100%; text-align: center; font-size: 0.95rem;
    }
    .custom-option input[type="checkbox"]:checked + .option-btn {
        background-color: #f97316; border-color: #f97316; color: #ffffff; font-weight: bold;
    }
    .status-btn { padding: 5px 15px; border-radius: 50px; font-size: 0.9rem; font-weight: bold; cursor: pointer; border: none; }
    .status-green { background-color: #d1fae5; color: #059669; border: 1px solid #059669; }
    .status-gray { background-color: #f3f4f6; color: #6b7280; border: 1px solid #6b7280; }
</style>

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
                                <form class="modal-content" method="POST" enctype="multipart/form-data">
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
        <form class="modal-content" method="POST" enctype="multipart/form-data">
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

<script>
function selectAll(groupClassName) {
    const checkboxes = document.querySelectorAll('.' + groupClassName);
    let allChecked = true;
    checkboxes.forEach(cb => { if (!cb.checked) allChecked = false; });
    checkboxes.forEach(cb => { cb.checked = !allChecked; });
}

function changeStatus(id, newStatus) {
    if(confirm('ยืนยันการเปลี่ยนสถานะเมนูนี้?')) {
        let formData = new FormData();
        formData.append('update_status_id', id);
        formData.append('new_status_val', newStatus);

        fetch('manage_menu.php', { method: 'POST', body: formData })
        .then(response => response.text())
        .then(data => {
            if(data.trim() === 'success') {
                let btn = document.getElementById('status-btn-' + id);
                if(newStatus === 1) {
                    btn.className = 'status-btn status-green';
                    btn.innerHTML = '● พร้อมขาย';
                    btn.setAttribute('onclick', 'changeStatus(' + id + ', 0)');
                } else {
                    btn.className = 'status-btn status-gray';
                    btn.innerHTML = '● ไม่พร้อมขาย';
                    btn.setAttribute('onclick', 'changeStatus(' + id + ', 1)');
                }
            } else { 
                Swal.fire({icon: 'error', title: 'เกิดข้อผิดพลาด!'}); 
            }
        });
    }
}

// ✅ แสดงแอนิเมชัน Loading หมุนๆ ตอนกดบันทึกข้อมูล
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        Swal.fire({
            title: 'กำลังบันทึกข้อมูล...',
            html: 'กรุณารอสักครู่',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading()
            }
        });
    });
});

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