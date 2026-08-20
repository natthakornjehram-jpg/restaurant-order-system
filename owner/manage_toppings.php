<?php
// owner/manage_toppings.php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['owner_id'])) {
    header("Location: ../login.php");
    exit;
}

// --- 1. จัดการข้อมูลหมวดหมู่ (เพิ่ม/แก้ไข) ---
if (isset($_POST['save_category'])) {
    $cat_name = trim($_POST['topping_cat_name']);
    $cat_id = isset($_POST['cat_id']) ? intval($_POST['cat_id']) : 0;

    if ($cat_id > 0) {
        // แก้ไข
        $stmt = $conn->prepare("UPDATE topping_categories SET topping_cat_name = ? WHERE topping_cat_id = ?");
        $stmt->bind_param("si", $cat_name, $cat_id);
    } else {
        // เพิ่มใหม่
        $stmt = $conn->prepare("INSERT INTO topping_categories (topping_cat_name) VALUES (?)");
        $stmt->bind_param("s", $cat_name);
    }
    $stmt->execute();
    header("Location: manage_toppings.php"); exit();
}

// --- 2. จัดการข้อมูลท็อปปิ้ง (เพิ่ม/แก้ไข) ---
if (isset($_POST['save_topping'])) {
    $name = trim($_POST['topping_name']);
    $price = floatval($_POST['price']);
    $cat_id = intval($_POST['topping_cat_id']);
    $t_id = isset($_POST['topping_id']) ? intval($_POST['topping_id']) : 0;

    if ($t_id > 0) {
        // แก้ไข
        $stmt = $conn->prepare("UPDATE topping SET topping_name = ?, topping_cat_id = ?, price = ? WHERE topping_id = ?");
        $stmt->bind_param("sidi", $name, $cat_id, $price, $t_id);
    } else {
        // เพิ่มใหม่
        $stmt = $conn->prepare("INSERT INTO topping (topping_name, topping_cat_id, price, is_active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("sid", $name, $cat_id, $price);
    }
    $stmt->execute();
    header("Location: manage_toppings.php"); exit();
}

// --- 3. ลบข้อมูล ---
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM topping WHERE topping_id = $id");
    header("Location: manage_toppings.php"); exit();
}

include '../includes/header_owner.php';
include '../includes/nav_owner.php';
?>

<style>
    .out-of-stock { opacity: 0.6; background-color: #f8f9fa; }
    .out-of-stock .topping-name { text-decoration: line-through; color: #6c757d; }
    .btn-status { font-size: 0.7rem; font-weight: bold; width: 75px; }
</style>

<div class="container py-5" style="margin-top: 60px;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <h2 class="fw-bold m-0"><i class="bi bi-egg-fried text-warning me-2"></i>จัดการท็อปปิ้ง</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary rounded-pill px-4 fw-bold" onclick="openCatModal()">+ หมวดหมู่</button>
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="openToppingModal()">+ เพิ่มท็อปปิ้ง</button>
        </div>
    </div>

    <?php
    $cats_query = $conn->query("SELECT * FROM topping_categories ORDER BY topping_cat_name ASC");
    while($cat = $cats_query->fetch_assoc()):
        $current_cat_id = $cat['topping_cat_id'];
    ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-light border-0 py-3 ps-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold m-0 text-dark"><?= htmlspecialchars($cat['topping_cat_name']) ?></h5>
            <button class="btn btn-sm btn-link text-secondary text-decoration-none" 
                    onclick="openCatModal(<?= $current_cat_id ?>, '<?= htmlspecialchars($cat['topping_cat_name']) ?>')">
                <i class="bi bi-pencil-square"></i> แก้ไขชื่อหมวด
            </button>
        </div>
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4 py-2 small" style="width: 35%;">ชื่อตัวเลือก</th>
                    <th class="py-2 small" style="width: 20%;">ราคา</th>
                    <th class="text-center py-2 small" style="width: 20%;">สถานะ</th>
                    <th class="text-center py-2 small" style="width: 25%;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt_items = $conn->prepare("SELECT * FROM topping WHERE topping_cat_id = ? ORDER BY price ASC");
                $stmt_items->bind_param("i", $current_cat_id);
                $stmt_items->execute();
                $items_res = $stmt_items->get_result();
                
                while($row = $items_res->fetch_assoc()):
                    $is_in_stock = $row['is_active'] == 1;
                ?>
                <tr id="row-<?= $row['topping_id'] ?>" class="<?= !$is_in_stock ? 'out-of-stock' : '' ?>">
                    <td class="ps-4 fw-bold topping-name"><?= htmlspecialchars($row['topping_name']) ?></td>
                    <td class="text-success fw-bold">+ ฿<?= number_format($row['price'], 2) ?></td>
                    <td class="text-center">
                        <button onclick="toggleToppingStatus(<?= $row['topping_id'] ?>, <?= $row['is_active'] ?>)" 
                                class="btn rounded-pill btn-status <?= $is_in_stock ? 'btn-success' : 'btn-danger' ?>">
                            <?= $is_in_stock ? 'มีของ' : 'ของหมด' ?>
                        </button>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" 
                                onclick="openToppingModal(<?= $row['topping_id'] ?>, '<?= htmlspecialchars($row['topping_name']) ?>', <?= $row['price'] ?>, <?= $current_cat_id ?>)">
                            แก้ไข
                        </button>
                        <a href="?delete_id=<?= $row['topping_id'] ?>" class="btn btn-sm btn-link text-danger p-0" onclick="return confirm('ลบรายการนี้?')">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endwhile; ?>
</div>

<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 rounded-4" method="POST">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="fw-bold m-0" id="catModalTitle">จัดการหมวดหมู่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <input type="hidden" name="cat_id" id="cat_id">
                <div class="mb-3">
                    <label class="small fw-bold mb-2">ชื่อหมวดหมู่</label>
                    <input type="text" name="topping_cat_name" id="cat_name" class="form-control rounded-3" placeholder="เช่น ประเภทไข่" required>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="save_category" class="btn btn-primary w-100 rounded-pill fw-bold">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="toppingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 rounded-4" method="POST">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="fw-bold m-0" id="toppingModalTitle">จัดการท็อปปิ้ง</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <input type="hidden" name="topping_id" id="t_id">
                <div class="mb-3">
                    <label class="small fw-bold mb-2">เลือกหมวดหมู่</label>
                    <select name="topping_cat_id" id="t_cat_id" class="form-select rounded-3" required>
                        <?php
                        $cats = $conn->query("SELECT * FROM topping_categories ORDER BY topping_cat_name ASC");
                        while($c = $cats->fetch_assoc()):
                        ?>
                            <option value="<?= $c['topping_cat_id'] ?>"><?= htmlspecialchars($c['topping_cat_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold mb-2">ชื่อท็อปปิ้ง</label>
                    <input type="text" name="topping_name" id="t_name" class="form-control rounded-3" required>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold mb-2">ราคาบวกเพิ่ม (฿)</label>
                    <input type="number" step="0.01" name="price" id="t_price" class="form-control rounded-3" required>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" name="save_topping" class="btn btn-primary w-100 rounded-pill fw-bold">บันทึกข้อมูลท็อปปิ้ง</button>
            </div>
        </form>
    </div>
</div>

<script>
// ฟังก์ชันเปิด Modal หมวดหมู่
function openCatModal(id = '', name = '') {
    document.getElementById('cat_id').value = id;
    document.getElementById('cat_name').value = name;
    document.getElementById('catModalTitle').innerText = id ? 'แก้ไขชื่อหมวดหมู่' : 'เพิ่มหมวดหมู่ใหม่';
    new bootstrap.Modal(document.getElementById('catModal')).show();
}

// ฟังก์ชันเปิด Modal ท็อปปิ้ง
function openToppingModal(id = '', name = '', price = '0.00', cat_id = '') {
    document.getElementById('t_id').value = id;
    document.getElementById('t_name').value = name;
    document.getElementById('t_price').value = price;
    document.getElementById('t_cat_id').value = cat_id;
    document.getElementById('toppingModalTitle').innerText = id ? 'แก้ไขข้อมูลท็อปปิ้ง' : 'เพิ่มท็อปปิ้งใหม่';
    new bootstrap.Modal(document.getElementById('toppingModal')).show();
}

/// ฟังก์ชันเปิด/ปิด ของหมด (ฉบับแก้ไขให้คุยกับ API รู้เรื่อง)
function toggleToppingStatus(id, currentStatus) {
    const newStatus = (currentStatus == 1) ? 0 : 1;
    const fd = new FormData();
    
    // 🔴 แก้ไขตรงนี้ให้ชื่อตรงกับที่ PHP รอรับ
    fd.append('id', id); 
    fd.append('type', 'topping'); // ต้องบอกประเภทด้วย PHP ถึงจะยอมทำงาน
    fd.append('new_status', newStatus);

    // เช็คชื่อไฟล์ให้ตรงกับที่คุณตั้งไว้ (api_update_status.php หรือ api_update_topping_status.php)
    fetch('api_update_status.php', { 
        method: 'POST', 
        body: fd 
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            // ถ้าใช้ SweetAlert2 ที่อยู่ใน Footer ก็จะสวยเลยครับ
            location.reload(); 
        } else {
            alert('เกิดข้อผิดพลาด: ' + (data.error || 'บันทึกไม่สำเร็จ'));
        }
    })
    .catch(err => console.error('Error:', err));
}
</script>

<?php include '../includes/footer_owner.php'; ?>