<?php
// member/history.php
session_start();
require_once '../includes/db.php';

// 🛡️ 1. เช็กสิทธิ์สมาชิก
if (!isset($_SESSION['customer_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login_customer.php");
    exit;
}
$user_id = $_SESSION['customer_id'];

// 2. ดึงประวัติการสั่งซื้อ (ใช้ total_amount ตามฐานข้อมูลจริงของคุณ)
// Join กับ owner เพื่อเอาชื่อร้านมาแสดง
$sql = "SELECT o.*, own.restaurant_name 
        FROM orders o 
        CROSS JOIN owner own 
        WHERE o.customer_id = ? 
        ORDER BY o.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$history = $stmt->get_result();


include '../includes/header_customer.php'; 
include '../includes/nav_customer.php';  
?>

<style>
    :root { --cafe-brown: #795548; --cafe-dark: #3e2723; }
    body { background-color: #fdfaf5; font-family: 'Sarabun', sans-serif; }
    
    .history-card {
        background: white; border-radius: 20px; border: none;
        box-shadow: 0 5px 15px rgba(62,39,35,0.05);
        margin-bottom: 20px; transition: 0.3s;
    }
    
    .status-badge {
        padding: 5px 15px; border-radius: 50px;
        font-size: 0.8rem; font-weight: bold;
    }

    /* สีตามสถานะในฐานข้อมูลของคุณ */
    .status-pending { background: #fff4e6; color: #d9480f; } 
    .status-cooking { background: #e7f5ff; color: #1971c2; } 
    .status-completed { background: #ebfbee; color: #2b8a3e; } 
    .status-cancelled { background: #fff5f5; color: #c92a2a; }
</style>
<div class="container mt-3">
    <a href="../menu.php" class="text-decoration-none text-muted fw-bold small transition-all hover-opacity">
        <i class="bi bi-chevron-left"></i> ย้อนกลับ
    </a>
</div> 
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <div class="bg-brown text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; background: var(--cafe-brown);">
            <i class="bi bi-clock-history fs-4"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--cafe-dark);">ประวัติการสั่งซื้อ</h4>
            <p class="text-muted mb-0 small">ตรวจสอบรายการอาหารที่คุณเคยสั่งทั้งหมด</p>
        </div>
    </div>

    <?php if ($history->num_rows > 0): ?>
        <?php while ($row = $history->fetch_assoc()): 
            $status_class = 'status-' . $row['order_status'];
            $status_text = '';
            switch($row['order_status']) {
                case 'pending': $status_text = '⏳ รอรับออเดอร์'; break;
                case 'cooking': $status_text = '👨‍🍳 กำลังปรุง'; break;
                case 'ready': $status_text = '✅ สำเร็จ'; break;
                case 'served':  $status_text = '✅ เรียบร้อยแล้ว'; break;
                case 'cancelled': $status_text = '❌ ยกเลิก'; break;
                default: $status_text = $row['order_status'];
            }
        ?>
            <div class="card history-card p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="text-muted small">Order #<?= str_pad($row['order_id'], 5, '0', STR_PAD_LEFT) ?></span>
                        <h5 class="fw-bold mt-1 mb-0"><?= htmlspecialchars($row['restaurant_name']) ?></h5>
                        <small class="text-muted"><i class="bi bi-calendar3 me-1"></i> <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></small>
                    </div>
                    <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                </div>

                <div class="border-top pt-3">
                    <div class="row align-items-center">
                        <div class="col-6">
                            <div class="small text-muted">ยอดรวมสุทธิ</div>
                            <div class="fw-bold fs-5 text-dark">฿<?= number_format($row['total_amount'], 2) ?></div>
                        </div>
                        <div class="col-6 text-end">
                            <a href="order_detail.php?id=<?= $row['order_id'] ?>" class="btn btn-outline-brown btn-sm rounded-pill px-3" style="border-color: var(--cafe-brown); color: var(--cafe-brown);">
                                ดูรายละเอียด <i class="bi bi-chevron-right small"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-clipboard-x display-1 text-muted opacity-25"></i>
            <p class="mt-3">ยังไม่มีประวัติการสั่งซื้อครับ</p>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer_customer.php'; ?>