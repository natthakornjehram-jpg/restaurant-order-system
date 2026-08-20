<?php
// admin/dashboard.php
include 'auth_admin.php'; // ตรวจสอบสิทธิ์แอดมิน
require_once '../includes/db.php';

// 1. ดึงสถิติรวม (ปรับให้ตรงกับชื่อคอลัมน์ใน DB ของคุณ)
// นับร้านค้าทั้งหมด
$total_stores = $conn->query("SELECT COUNT(*) as total FROM restaurants")->fetch_assoc()['total'];

// นับเจ้าของร้านที่รออนุมัติ (เช็คจากตาราง users ที่ role='owner' และ is_approved=0)
$pending_stores = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'owner' AND is_approved = 0")->fetch_assoc()['total'];

// นับลูกค้าสมาชิกทั้งหมด
$total_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'customer'")->fetch_assoc()['total'];

// ยอดขายรวมทั้งหมด (เฉพาะออเดอร์ที่เสิร์ฟแล้ว)
$total_sales_res = $conn->query("SELECT SUM(total_price) as total FROM orders WHERE order_status = 'served'");
$total_sales = $total_sales_res->fetch_assoc()['total'] ?? 0;

include 'header_admin.php'; 
include 'nav_admin.php'; 
?>

<style>
    :root { --admin-gold: #a67c00; --admin-dark: #212121; --cafe-brown: #795548; }
    body { background-color: #f4f1ea; font-family: 'Sarabun', sans-serif; }
    .stat-card {
        border-radius: 20px; border: none; padding: 25px;
        background: white; box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        transition: 0.3s; height: 100%;
    }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
    .icon-box {
        width: 50px; height: 50px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; margin-bottom: 15px;
    }
    .table-card { border-radius: 20px; border: none; background: white; box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
</style>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
        <div>
            <h2 class="fw-bold mb-0" style="color: var(--admin-dark);">Dashboard ส่วนกลาง</h2>
            <p class="text-muted small mb-0">ยินดีต้อนรับแอดมิน: <?= htmlspecialchars($_SESSION['fullname']) ?></p>
        </div>
        <span class="badge bg-dark rounded-pill px-3 py-2 shadow-sm"><i class="bi bi-shield-lock me-1"></i> Administrator Mode</span>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon-box bg-success text-white shadow-sm"><i class="bi bi-currency-dollar"></i></div>
                <div class="small text-muted fw-bold">ยอดขายรวมทั้งหมด</div>
                <div class="h4 fw-bold text-success">฿<?= number_format($total_sales, 0) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon-box bg-warning text-dark shadow-sm"><i class="bi bi-clock-history"></i></div>
                <div class="small text-muted fw-bold">เจ้าของร้านรออนุมัติ</div>
                <div class="h4 fw-bold text-danger"><?= number_format($pending_stores) ?> <span class="small fw-normal text-muted">ราย</span></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon-box bg-primary text-white shadow-sm"><i class="bi bi-people"></i></div>
                <div class="small text-muted fw-bold">สมาชิก (ลูกค้า)</div>
                <div class="h4 fw-bold text-primary"><?= number_format($total_users) ?> <span class="small fw-normal text-muted">คน</span></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon-box bg-dark text-white shadow-sm"><i class="bi bi-shop"></i></div>
                <div class="small text-muted fw-bold">ร้านค้าในระบบ</div>
                <div class="h4 fw-bold"><?= number_format($total_stores) ?> <span class="small fw-normal text-muted">ร้าน</span></div>
            </div>
        </div>
    </div>

    <div class="card table-card overflow-hidden">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-journal-check me-2"></i>คำขออนุมัติค้างอยู่</h5>
            <a href="approve_users.php" class="btn btn-sm btn-dark rounded-pill px-3">
                ดูรายการทั้งหมด <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="card-body text-center py-4">
            <?php if ($pending_stores > 0): ?>
                <p class="mb-0">มี <span class="fw-bold text-danger"><?= $pending_stores ?></span> รายการรอการอนุมัติ</p>
            <?php else: ?>
                <p class="text-muted mb-0"><i class="bi bi-check-circle-fill text-success me-1"></i> ไม่มีรายการค้างอยู่</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer_customer.php'; ?>