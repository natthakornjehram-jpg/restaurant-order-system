<?php
// manage_categories.php
session_start();
require_once '../includes/db.php'; 

// เช็กสิทธิ์เจ้าของร้าน (อิงจาก Session ของตาราง owner)
if (!isset($_SESSION['owner_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../includes/header_owner.php'; 
include '../includes/nav_owner.php'; 
?>

<div class="main-content container-fluid text-dark">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
        <h1 class="h2 fw-bold"><i class="bi bi-tags me-2 text-primary"></i>จัดการหมวดหมู่อาหาร</h1>
        <a href="manage_menu.php" class="btn btn-secondary rounded-pill px-4">กลับหน้าจัดการเมนู</a>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-primary text-white py-3 rounded-top-4">
                    <h5 class="mb-0 fw-bold">เพิ่มหมวดหมู่ใหม่</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ชื่อหมวดหมู่</label>
                        <input type="text" id="cat_name" class="form-control form-control-lg" placeholder="เช่น อาหารจานเดียว" required>
                    </div>
                    <button type="button" onclick="saveCategory()" class="btn btn-success w-100 py-2 fw-bold rounded-3">
                        <i class="bi bi-plus-circle me-2"></i>บันทึกข้อมูล
                    </button>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0" id="catTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 py-3">ชื่อหมวดหมู่</th>
                                <th class="text-center py-3" style="width: 150px;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="catList">
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/owner-manage-categories.js"></script>

<?php include '../includes/footer_owner.php'; ?>