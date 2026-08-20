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

<div class="main-content container-fluid text-dark" style="margin-top: 80px;">
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

<script>
// 1. ฟังก์ชันโหลดข้อมูลหมวดหมู่มาโชว์ในตาราง
function loadCategories() {
    fetch('cat_api.php?action=list')
    .then(res => res.json())
    .then(data => {
        let html = '';
        if(data && data.length > 0) {
            data.forEach(item => {
                html += `
                <tr>
                    <td class="ps-4 fw-bold">${item.category_name}</td>
                    <td class="text-center">
                        <button class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="deleteCategory(${item.category_id})">
                            <i class="bi bi-trash"></i> ลบ
                        </button>
                    </td>
                </tr>`;
            });
        } else {
            html = '<tr><td colspan="2" class="text-center py-5 text-muted">ยังไม่มีข้อมูลหมวดหมู่</td></tr>';
        }
        document.getElementById('catList').innerHTML = html;
    })
    .catch(error => console.error('Error loading categories:', error));
}

// 2. ฟังก์ชันบันทึกข้อมูล (เพิ่มหมวดหมู่)
function saveCategory() {
    const nameInput = document.getElementById('cat_name');
    const name = nameInput.value.trim();
    if(!name) { alert('กรุณากรอกชื่อหมวดหมู่'); return; }

    const formData = new FormData();
    formData.append('category_name', name);

    fetch('cat_api.php?action=add', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            nameInput.value = ''; // ล้างช่องกรอกข้อมูล
            loadCategories(); // โหลดตารางใหม่
        } else {
            alert('เกิดข้อผิดพลาด: ' + data.message);
        }
    })
    .catch(error => console.error('Error saving category:', error));
}

// 3. ฟังก์ชันลบข้อมูล
function deleteCategory(id) {
    if(!confirm('ยืนยันการลบหมวดหมู่? (หมวดหมู่ที่มีเมนูอาหารค้างอยู่จะไม่สามารถลบได้)')) return;
    
    fetch('cat_api.php?action=delete&id=' + id)
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            loadCategories();
        } else {
            alert(data.message || 'ไม่สามารถลบได้');
        }
    })
    .catch(error => console.error('Error deleting category:', error));
}

// โหลดข้อมูลครั้งแรกเมื่อเปิดหน้า
loadCategories();
</script>

<?php include '../includes/footer_owner.php'; ?>