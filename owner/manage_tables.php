<?php 
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';

// --- 1. จัดการเพิ่มโต๊ะใหม่ ---
if (isset($_POST['add_table'])) {
    $table_no = mysqli_real_escape_string($conn, $_POST['table_number']);
    $check = $conn->query("SELECT * FROM restauranttable WHERE table_number = '$table_no'");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO restauranttable (table_number, status) VALUES ('$table_no', 'available')");
    }
    echo "<script>window.location='manage_tables.php';</script>";
}

// --- 2. จัดการแก้ไขชื่อโต๊ะ ---
if (isset($_POST['edit_table'])) {
    $t_id = intval($_POST['table_id']);
    $new_no = mysqli_real_escape_string($conn, $_POST['new_table_number']);
    $conn->query("UPDATE restauranttable SET table_number = '$new_no' WHERE table_id = $t_id");
    echo "<script>window.location='manage_tables.php';</script>";
}

// --- 3. จัดการลบโต๊ะ ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM restauranttable WHERE table_id = $id");
    echo "<script>window.location='manage_tables.php';</script>";
}

include '../includes/header_owner.php'; 
include '../includes/nav_owner.php'; 
?>

<div class="main-content container-fluid" style="padding-top: 100px;">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom text-dark">
        <h1 class="h2 fw-bold"><i class="bi bi-grid-3x3-gap me-2"></i>จัดการโต๊ะอาหาร</h1>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addTableModal">
            <i class="bi bi-plus-circle me-2"></i>เพิ่มโต๊ะใหม่
        </button>
    </div>

    <div class="row g-4" id="table-grid">
        <?php 
        $tables = $conn->query("SELECT * FROM restauranttable ORDER BY table_number ASC");
        if($tables && $tables->num_rows > 0):
            while($t = $tables->fetch_assoc()):
                $is_busy = ($t['status'] !== 'available');
        ?>
        <div class="col-6 col-md-4 col-lg-2" id="card-table-<?php echo $t['table_id']; ?>">
            <div class="card h-100 border-0 shadow-sm rounded-4 text-center p-3 <?php echo $is_busy ? 'bg-danger text-white' : 'bg-white text-dark'; ?> status-card">
                <div class="card-body p-0">
                    <div class="small opacity-75 mb-1">TABLE</div>
                    <h2 class="fw-bold mb-2"><?php echo htmlspecialchars($t['table_number']); ?></h2>
                    
                    <div class="mb-3 status-badge">
                        <?php if($is_busy): ?>
                            <span class="badge rounded-pill bg-white text-danger px-3">ไม่ว่าง</span>
                        <?php else: ?>
                            <span class="badge rounded-pill bg-success px-3">ว่าง</span>
                        <?php endif; ?>
                    </div>

                    <div class="d-grid gap-2 btn-area">
                        <?php if($is_busy): ?>
                            <button type="button" onclick="ajaxCheckout(<?php echo $t['table_id']; ?>)" class="btn btn-sm btn-light text-danger fw-bold rounded-pill shadow-sm">
                                <i class="bi bi-cash-stack"></i> เช็คบิล
                            </button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-primary rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#editTable<?php echo $t['table_id']; ?>">
                                <i class="bi bi-pencil-square"></i> แก้ไขชื่อ
                            </button>
                            <button type="button" onclick="showQR('<?php echo $t['table_number']; ?>')" class="btn btn-sm btn-outline-dark rounded-pill shadow-sm">
                                <i class="bi bi-qr-code"></i> พิมพ์ QR
                            </button>
                        <?php endif; ?>
                        
                        <a href="?delete=<?php echo $t['table_id']; ?>" class="btn btn-link btn-sm text-<?php echo $is_busy ? 'white' : 'danger'; ?> text-decoration-none x-small" onclick="return confirm('ลบโต๊ะนี้?')">ลบโต๊ะ</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade text-dark" id="editTable<?php echo $t['table_id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-sm modal-dialog-centered">
                <form class="modal-content border-0 rounded-4 shadow" method="POST">
                    <div class="modal-header border-0">
                        <h5 class="fw-bold">แก้ไขชื่อโต๊ะ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body py-0">
                        <input type="hidden" name="table_id" value="<?php echo $t['table_id']; ?>">
                        <label class="small fw-bold mb-2">เลขโต๊ะใหม่</label>
                        <input type="text" name="new_table_number" class="form-control rounded-3" value="<?php echo htmlspecialchars($t['table_number']); ?>" required>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" name="edit_table" class="btn btn-warning w-100 rounded-pill py-2 fw-bold">อัปเดตชื่อโต๊ะ</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endwhile; else: ?>
            <div class="col-12 text-center py-5 text-muted">ยังไม่มีข้อมูลโต๊ะในระบบ</div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="addTableModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form class="modal-content border-0 rounded-4 shadow text-dark" method="POST">
            <div class="modal-header border-0"><h5 class="fw-bold">เพิ่มโต๊ะใหม่</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body py-0">
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
                <button type="button" class="btn btn-secondary w-100 rounded-pill" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/owner-manage-tables.js"></script>

<?php include '../includes/footer_owner.php'; ?>
