<?php 
// owner/unpaid_orders.php
session_start();
include '../includes/db.php';

// 1. เช็กสิทธิ์เจ้าของร้าน
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit;
}

$owner_id = $_SESSION['user_id'];

// หา restaurant_id ของเจ้าของร้านนี้
$stmt_rest = $conn->prepare("SELECT restaurant_id FROM restaurants WHERE owner_id = ? LIMIT 1");
$stmt_rest->bind_param("i", $owner_id);
$stmt_rest->execute();
$restaurant_id = $stmt_rest->get_result()->fetch_assoc()['restaurant_id'];

include '../includes/header_owner.php'; 
include '../includes/nav_owner.php'; 

// 2. ดึงออเดอร์ที่พร้อมเสิร์ฟ (ready/served) แต่ยังไม่จ่ายเงิน (unpaid/verifying) ของร้านตัวเอง
$sql = "SELECT * FROM orders 
        WHERE restaurant_id = ? 
        AND order_status IN ('ready', 'served') 
        AND payment_status IN ('unpaid', 'verifying')
        ORDER BY created_at ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $restaurant_id);
$stmt->execute();
$res = $stmt->get_result();
?>

<style>
    .main-content { background-color: #fcfcfc; min-height: 100vh; font-family: 'Sarabun', sans-serif; }
    .card-unpaid { 
        border: none; 
        border-radius: 20px; 
        transition: 0.3s; 
        background: #fff;
        border-bottom: 5px solid #e3f2fd;
    }
    .card-unpaid:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
    .price-tag { font-size: 2rem; color: #16a34a; font-weight: 800; }
    .modal-content { border-radius: 25px; border: none; }
    .btn-pay-cash { background-color: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; }
    .btn-pay-transfer { background-color: #f0f9ff; color: #0284c7; border: 1px solid #e0f2fe; }
</style>

<div class="main-content container-fluid pb-5" style="margin-top: 80px;">
    <div class="pt-4 pb-3 mb-4 d-flex justify-content-between align-items-center border-bottom">
        <div>
            <h2 class="fw-bold mb-0 text-dark">รายการรอชำระเงิน</h2>
            <p class="text-muted small mb-0">มีทั้งหมด <?php echo $res->num_rows; ?> รายการที่ต้องเรียกเก็บเงิน</p>
        </div>
        <a href="dashboard.php" class="btn btn-light rounded-pill px-4 btn-sm border shadow-sm">กลับหน้าหลัก</a>
    </div>

    <div class="row g-3">
        <?php if($res->num_rows > 0): while($row = $res->fetch_assoc()): 
            $oid = $row['order_id'];
            $order_type = $row['order_type'];
        ?>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card card-unpaid shadow-sm h-100">
                <div class="card-body p-4 text-center">
                    <span class="badge bg-primary rounded-pill px-3 mb-2">
                        ออเดอร์ #<?php echo str_pad($oid, 4, '0', STR_PAD_LEFT); ?>
                    </span>
                    <div class="small text-muted mb-3">
                        <?php 
                            if($order_type == 'dine_in') echo '🏠 ทานที่ร้าน';
                            elseif($order_type == 'takeaway') echo '🛍️ กลับบ้าน';
                            else echo '🛵 เดลิเวอรี่';
                        ?>
                    </div>
                    <div class="price-tag mb-3">฿<?php echo number_format($row['total_price'], 0); ?></div>
                    <button class="btn btn-dark w-100 rounded-pill fw-bold py-2 shadow-sm" 
                            data-bs-toggle="modal" data-bs-target="#payModal<?php echo $oid; ?>">
                        เรียกเก็บเงิน
                    </button>
                </div>
            </div>
        </div>

        <div class="modal fade" id="payModal<?php echo $oid; ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-lg">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="fw-bold">สรุปยอด ออเดอร์ #<?php echo $oid; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="bg-light p-3 rounded-4 mb-4 border">
                            <label class="small text-muted fw-bold mb-2 text-uppercase">รายการอาหาร</label>
                            <?php 
                            $items = $conn->query("SELECT oi.*, m.menu_name FROM order_items oi JOIN menus m ON oi.menu_id = m.menu_id WHERE oi.order_id = $oid");
                            while($i = $items->fetch_assoc()): ?>
                                <div class="d-flex justify-content-between mb-2 small text-dark">
                                    <span><?php echo $i['quantity']; ?>x <?php echo htmlspecialchars($i['menu_name']); ?></span>
                                    <span class="fw-bold">฿<?php echo number_format($i['subtotal'], 0); ?></span>
                                </div>
                            <?php endwhile; ?>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">ยอดรวมสุทธิ</span>
                                <span class="price-tag" style="font-size: 1.5rem;">฿<?php echo number_format($row['total_price'], 0); ?></span>
                            </div>
                        </div>
                        
                        <div class="row g-2">
                            <div class="col-6">
                                <button onclick="confirmPayment(<?php echo $oid; ?>, 'cash')" class="btn btn-pay-cash w-100 py-3 fw-bold rounded-4 shadow-sm">
                                    <i class="bi bi-cash me-1"></i> เงินสด
                                </button>
                            </div>
                            <div class="col-6">
                                <button onclick="confirmPayment(<?php echo $oid; ?>, 'transfer')" class="btn btn-pay-transfer w-100 py-3 fw-bold rounded-4 shadow-sm">
                                    <i class="bi bi-qr-code-scan me-1"></i> เงินโอน
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div class="col-12 text-center py-5">
            <div class="bg-white rounded-4 p-5 shadow-sm d-inline-block border">
                <i class="bi bi-check2-circle text-success" style="font-size: 4rem;"></i>
                <h4 class="mt-3 fw-bold">ไม่มีรายการค้างชำระ</h4>
                <p class="text-muted small">ออเดอร์ทั้งหมดถูกจัดการเรียบร้อยแล้วครับ</p>
                <a href="dashboard.php" class="btn btn-primary rounded-pill px-4">ไปที่แดชบอร์ด</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-Refresh หน้าจอทุก 15 วินาที
setInterval(function(){
    if(document.querySelectorAll('.modal.show').length === 0) {
        location.reload();
    }
}, 15000);

function confirmPayment(orderId, method) {
    let methodName = (method === 'cash') ? 'เงินสด' : 'เงินโอน';
    if (confirm('ยืนยันรับชำระเงินด้วย ' + methodName + ' ใช่หรือไม่?')) {
        const fd = new FormData();
        fd.append('order_id', orderId);
        fd.append('payment_method', method);

        fetch('payments.php', { 
            method: 'POST', 
            body: fd 
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert('บันทึกการชำระเงินสำเร็จ!');
                location.reload(); 
            } else {
                alert('เกิดข้อผิดพลาด: ' + data.error);
            }
        })
        .catch(err => alert('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'));
    }
}
</script>

<?php include '../includes/footer_owner.php'; ?>