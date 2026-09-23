<?php
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';
require_once '../includes/csrf.php';
require_once '../includes/table_render.php';

// ให้หน้านี้ตอบเป็น JSON แทนการรีโหลดทั้งหน้าได้ ถ้าคำขอมาจาก fetch() ของ JS - ตรรกะเพิ่ม/แก้ไข/ลบโต๊ะด้านล่างเหมือนเดิมทุกอย่าง
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// --- 1. จัดการเพิ่มโต๊ะใหม่ ---
if (isset($_POST['add_table'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง']); exit; }
        header("Location: manage_tables.php");
        exit();
    }
    $table_no = trim($_POST['table_number']);
    if (empty($table_no)) {
        if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'กรุณากรอกเลขโต๊ะ']); exit; }
        header("Location: manage_tables.php");
        exit();
    }
    $check = $conn->prepare("SELECT table_id FROM restauranttable WHERE table_number = ?");
    $check->bind_param("s", $table_no);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'มีโต๊ะชื่อนี้อยู่แล้ว']); exit; }
        header("Location: manage_tables.php");
        exit();
    }

    // สุ่มโทเค็นลับต่อโต๊ะไว้ฝังใน QR code ตั้งแต่ตอนสร้างโต๊ะเลย (ดูรายละเอียดที่ includes/db.php)
    $qr_token = bin2hex(random_bytes(8));
    $stmt = $conn->prepare("INSERT INTO restauranttable (table_number, status, qr_token) VALUES (?, 'available', ?)");
    $stmt->bind_param("ss", $table_no, $qr_token);
    $stmt->execute();
    $new_table_id = $conn->insert_id;

    if ($is_ajax) {
        $new_table_row = ['table_id' => $new_table_id, 'table_number' => $table_no, 'status' => 'available', 'qr_token' => $qr_token];
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'card_html' => render_owner_table_card($new_table_row),
            'modal_html' => render_owner_table_edit_modal($new_table_row),
        ]);
        exit;
    }
    $_SESSION['success_msg'] = "เพิ่มโต๊ะเรียบร้อยแล้ว";
    header("Location: manage_tables.php");
    exit();
}

// --- 2. จัดการแก้ไขชื่อโต๊ะ ---
if (isset($_POST['edit_table'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง']); exit; }
        header("Location: manage_tables.php");
        exit();
    }
    $t_id = intval($_POST['table_id']);
    $new_no = trim($_POST['new_table_number']);
    if ($t_id <= 0 || empty($new_no)) {
        if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'กรุณากรอกเลขโต๊ะ']); exit; }
        header("Location: manage_tables.php");
        exit();
    }
    $stmt = $conn->prepare("UPDATE restauranttable SET table_number = ? WHERE table_id = ?");
    $stmt->bind_param("si", $new_no, $t_id);
    $stmt->execute();

    if ($is_ajax) {
        $row_stmt = $conn->prepare("SELECT * FROM restauranttable WHERE table_id = ?");
        $row_stmt->bind_param("i", $t_id);
        $row_stmt->execute();
        $t_row = $row_stmt->get_result()->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'table_id' => $t_id,
            'card_html' => $t_row ? render_owner_table_card($t_row) : '',
            'modal_html' => $t_row ? render_owner_table_edit_modal($t_row) : '',
        ]);
        exit;
    }
    $_SESSION['success_msg'] = "แก้ไขชื่อโต๊ะเรียบร้อยแล้ว";
    header("Location: manage_tables.php");
    exit();
}

// --- 3. จัดการลบโต๊ะ ---
// เดิมเป็นลิงก์ GET (?delete=) ไม่มี CSRF token เลย เปลี่ยนเป็น POST form + ตรวจ CSRF token
// เหมือนการเพิ่ม/แก้ไขโต๊ะด้านบน กันหน้าอื่นหลอกให้ owner ที่ล็อกอินอยู่ลบโต๊ะโดยไม่ตั้งใจ
if (isset($_POST['delete_table'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง']); exit; }
        header("Location: manage_tables.php");
        exit();
    }
    $id = intval($_POST['delete_table']);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM restauranttable WHERE table_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success' => true]); exit; }
        $_SESSION['success_msg'] = "ลบโต๊ะเรียบร้อยแล้ว";
    } elseif ($is_ajax) {
        header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => 'ไม่พบโต๊ะนี้']); exit;
    }
    header("Location: manage_tables.php");
    exit();
}

include '../includes/header_owner.php'; 
include '../includes/nav_owner.php'; 
?>

<div class="main-content container-fluid">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom text-dark">
        <div class="d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-white rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; border: 1px solid #edf2f7; background: #ffffff; color: #4a5568;" title="ย้อนกลับ">
                <i class="bi bi-arrow-left fs-4"></i>
            </a>
            <h4 class="fw-bold mb-0 text-dark" style="font-size: 1.25rem;"><i class="bi bi-grid-3x3-gap me-2"></i>จัดการโต๊ะอาหาร</h4>
        </div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addTableModal">
            <i class="bi bi-plus-circle me-2"></i>เพิ่มโต๊ะใหม่
        </button>
    </div>

    <div class="row g-4" id="table-grid">
        <?php
        $tables = $conn->query("SELECT * FROM restauranttable ORDER BY table_number ASC");
        if($tables && $tables->num_rows > 0):
            while($t = $tables->fetch_assoc()):
                echo render_owner_table_card($t);
                echo render_owner_table_edit_modal($t);
            endwhile;
        else: ?>
            <div class="col-12 text-center py-5 text-muted" id="tableEmptyState">ยังไม่มีข้อมูลโต๊ะในระบบ</div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="addTableModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form class="modal-content border-0 rounded-4 shadow text-dark table-mini-form" data-table-action="add" action="manage_tables.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-header border-0"><h5 class="fw-bold">เพิ่มโต๊ะใหม่</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body py-0">
                <div class="add-table-error"></div>
                <label class="small fw-bold mb-2">เลขโต๊ะ / ชื่อโต๊ะ</label>
                <input type="text" name="table_number" class="form-control form-control-lg rounded-3" placeholder="เช่น A1" required>
            </div>
            <div class="modal-footer border-0"><button type="submit" name="add_table" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">บันทึกลงระบบ</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow text-dark">
            <div class="modal-header border-0">
                <h5 class="fw-bold m-0">QR Code โต๊ะ <span id="qrTableNum"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center pt-0">
                <div id="qrImageContainer" class="mb-3 position-relative d-inline-block">
                    <img id="qrImg" src="" class="img-fluid rounded-3 shadow-sm" alt="QR Code" style="min-width: 200px;">
                    <a id="downloadQrBtn" href="" download="" target="_blank" class="btn btn-primary position-absolute top-0 end-0 m-2 rounded-circle shadow-sm" title="ดาวน์โหลดรูปภาพ">
                        <i class="bi bi-download"></i>
                    </a>
                </div>
                <p class="small text-muted mb-3">กดปุ่มไอคอน <i class="bi bi-download"></i> เพื่อดาวน์โหลด <br> หรือคลิกขวาที่รูปเพื่อบันทึก</p>

                <div class="d-grid gap-2 mb-3">
                    <a id="openTableLinkBtn" href="" target="_blank" class="btn btn-outline-primary rounded-pill fw-bold">
                        <i class="bi bi-box-arrow-up-right me-1"></i> เปิดลิงก์เมนูโต๊ะนี้
                    </a>
                    <button type="button" id="copyTableLinkBtn" class="btn btn-outline-secondary rounded-pill fw-bold">
                        <i class="bi bi-link-45deg"></i> คัดลอกลิงก์
                    </button>
                </div>

                <button type="button" class="btn btn-secondary w-100 rounded-pill" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<script>const BASE_URL = "<?= BASE_URL ?>";</script>
<script src="<?= BASE_URL ?>assets/js/owner-manage-tables.js?v=<?= @filemtime(__DIR__ . '/../assets/js/owner-manage-tables.js') ?>"></script>

<?php include '../includes/footer_owner.php'; ?>
<?php include '../includes/owner_flash.php'; ?>
