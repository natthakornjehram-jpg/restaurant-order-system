<?php 
session_start();
include '../includes/db.php';
include '../includes/upload_helper.php';

if (!isset($_SESSION['owner_id'])) {
    header("Location: ../login.php");
    exit;
}

$owner_id = $_SESSION['owner_id'];
$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $res_name = trim($_POST['restaurant_name']);
    $res_phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $max_queue = intval($_POST['max_queue']);
    $is_online = isset($_POST['is_online_open']) ? 1 : 0;
    $is_shop = isset($_POST['is_shop_open']) ? 1 : 0;
    $close_reason = trim($_POST['close_reason']);
    $bank_info = trim($_POST['bank_info']); // รับค่าข้อมูลบัญชีธนาคาร

    // ดึงข้อมูลเดิมเพื่อเช็กชื่อไฟล์รูปเก่า
    $old_data = $conn->query("SELECT logo_url, promptpay_qr FROM owner WHERE owner_id = $owner_id")->fetch_assoc();
    $logo_name = $old_data['logo_url'];
    $qr_name = $old_data['promptpay_qr'];

    // โฟลเดอร์เก็บรูป (ย้ายมาไว้ใต้ assets/images/ ให้สอดคล้องกับที่อื่นในระบบ)
    $target_dir = "../assets/images/logos/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

    // --- จัดการอัปโหลดรูปโลโก้ ---
    if (!empty($_FILES['logo']['name'])) {
        $new_logo_name = handle_image_upload($_FILES['logo'], $target_dir, 'logo');
        if ($new_logo_name !== false) {
            if ($logo_name != 'default_logo.png' && !empty($logo_name) && file_exists($target_dir . $logo_name)) {
                unlink($target_dir . $logo_name); // ลบรูปเก่า
            }
            $logo_name = $new_logo_name;
        }
    }

    // --- จัดการอัปโหลดรูป QR Code พร้อมเพย์ ---
    if (!empty($_FILES['promptpay_qr']['name'])) {
        $new_qr_name = handle_image_upload($_FILES['promptpay_qr'], $target_dir, 'qr');
        if ($new_qr_name !== false) {
            if (!empty($qr_name) && file_exists($target_dir . $qr_name)) {
                unlink($target_dir . $qr_name); // ลบรูป QR เก่า
            }
            $qr_name = $new_qr_name;
        }
    }

    // --- อัปเดตข้อมูลลงฐานข้อมูล ---
    $sql = "UPDATE owner SET 
            restaurant_name = ?, phone = ?, address = ?, 
            max_queue = ?, is_online_open = ?, is_shop_open = ?, 
            close_reason = ?, logo_url = ?, promptpay_qr = ?, bank_info = ? 
            WHERE owner_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssiisssssi", $res_name, $res_phone, $address, $max_queue, $is_online, $is_shop, $close_reason, $logo_name, $qr_name, $bank_info, $owner_id);
    
    if ($stmt->execute()) {
        $success_msg = "อัปเดตข้อมูลร้านค้าและช่องทางชำระเงินเรียบร้อยแล้ว!";
    } else {
        $error_msg = "เกิดข้อผิดพลาด: " . $conn->error;
    }
}

// ดึงข้อมูลล่าสุดมาแสดงผล
$stmt = $conn->prepare("SELECT * FROM owner WHERE owner_id = ?");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();

include '../includes/header_owner.php'; 
include '../includes/nav_owner.php'; 
?>

<div class="main-content container py-5" style="margin-top: 70px;">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold m-0"><i class="bi bi-gear-fill text-secondary me-2"></i>ตั้งค่าร้านค้า</h2>
            </div>

            <?php if($success_msg): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4"><i class="bi bi-check-circle-fill me-2"></i><?= $success_msg ?></div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="row g-4">
                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-body p-4 p-md-5">
                                <h5 class="fw-bold mb-4 border-bottom pb-2">ข้อมูลทั่วไป</h5>
                                
                                <div class="text-center mb-4">
                                    <div class="position-relative d-inline-block">
                                        <img src="../assets/images/logos/<?= $store['logo_url'] ?? 'default_logo.png' ?>" id="preview_logo" class="rounded-circle shadow-sm border" style="width: 120px; height: 120px; object-fit: cover;">
                                        <label for="logo_input" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow" style="width: 35px; height: 35px; cursor: pointer;">
                                            <i class="bi bi-camera-fill"></i>
                                        </label>
                                        <input type="file" id="logo_input" name="logo" class="d-none" accept="image/*" onchange="previewImg(this, 'preview_logo')">
                                    </div>
                                    <p class="text-muted small mt-2">โลโก้ร้าน</p>
                                </div>

                                <div class="mb-3">
                                    <label class="fw-bold mb-1">ชื่อร้านอาหาร</label>
                                    <input type="text" name="restaurant_name" class="form-control rounded-3" value="<?= htmlspecialchars($store['restaurant_name']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="fw-bold mb-1">เบอร์โทรศัพท์ร้าน</label>
                                    <input type="text" name="phone" class="form-control rounded-3" value="<?= htmlspecialchars($store['phone']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="fw-bold mb-1">ที่อยู่ร้าน</label>
                                    <textarea name="address" class="form-control rounded-3" rows="2"><?= htmlspecialchars($store['address']) ?></textarea>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="fw-bold mb-1 text-danger">จำกัดคิวสูงสุด (กันออเดอร์ล้น)</label>
                                        <input type="number" name="max_queue" class="form-control rounded-3 border-danger" value="<?= $store['max_queue'] ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="fw-bold mb-1 text-muted">เหตุผลปิดร้าน (ถ้ามี)</label>
                                        <input type="text" name="close_reason" class="form-control rounded-3" value="<?= htmlspecialchars($store['close_reason']) ?>" placeholder="เช่น วัตถุดิบหมด">
                                    </div>
                                </div>

                                <div class="border-top pt-3">
                                    <div class="form-check form-switch h5 mb-2">
                                        <input class="form-check-input" type="checkbox" name="is_shop_open" <?= ($store['is_shop_open'] == 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label">เปิดรับลูกค้าหน้าร้าน</label>
                                    </div>
                                    <div class="form-check form-switch h5">
                                        <input class="form-check-input" type="checkbox" name="is_online_open" <?= ($store['is_online_open'] == 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label">เปิดรับออเดอร์ออนไลน์</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-body p-4 p-md-5 bg-light rounded-4">
                                <h5 class="fw-bold mb-4 border-bottom pb-2 text-success"><i class="bi bi-wallet2 me-2"></i>ช่องทางรับเงิน (ออนไลน์)</h5>
                                
                                <div class="mb-4 text-center">
                                    <label class="fw-bold mb-2 d-block">QR Code รับเงิน (พร้อมเพย์/ธนาคาร)</label>
                                    <div class="border rounded-4 bg-white p-2 mb-2 mx-auto" style="width: 200px; height: 200px; overflow: hidden; position: relative;">
                                        <?php if(!empty($store['promptpay_qr'])): ?>
                                            <img src="../assets/images/logos/<?= $store['promptpay_qr'] ?>" id="preview_qr" style="width: 100%; height: 100%; object-fit: contain;">
                                        <?php else: ?>
                                            <img src="" id="preview_qr" style="width: 100%; height: 100%; object-fit: contain; display: none;">
                                            <div id="qr_placeholder" class="d-flex align-items-center justify-content-center h-100 text-muted flex-column">
                                                <i class="bi bi-qr-code-scan fs-1"></i>
                                                <small>ยังไม่มี QR Code</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <label for="qr_input" class="btn btn-sm btn-outline-success rounded-pill px-3">
                                        <i class="bi bi-upload me-1"></i> อัปโหลดรูป QR
                                    </label>
                                    <input type="file" id="qr_input" name="promptpay_qr" class="d-none" accept="image/*" onchange="previewImg(this, 'preview_qr', 'qr_placeholder')">
                                </div>

                                <div>
                                    <label class="fw-bold mb-2">รายละเอียดบัญชีธนาคาร</label>
                                    <textarea name="bank_info" class="form-control rounded-3" rows="4" placeholder="เช่น&#10;ธ.กสิกรไทย 123-4-56789-0&#10;ชื่อบัญชี นายใจดี ขายอร่อย&#10;พร้อมเพย์ 0812345678"><?= htmlspecialchars($store['bank_info']) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow fs-5">
                            <i class="bi bi-save2 me-2"></i>บันทึกการตั้งค่าทั้งหมด
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function previewImg(input, targetId, placeholderId = null) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var targetImg = document.getElementById(targetId);
            targetImg.src = e.target.result;
            targetImg.style.display = 'block';
            if(placeholderId) {
                var placeholder = document.getElementById(placeholderId);
                if(placeholder) placeholder.style.display = 'none';
            }
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include '../includes/footer_owner.php'; ?>