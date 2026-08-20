<?php
// qr_table/my_bill.php
session_start();
require_once '../includes/db.php';

// เช็กว่ามีการสแกนโต๊ะมาจริงไหม
if (!isset($_SESSION['table_id'])) {
    echo "<script>alert('กรุณาสแกน QR Code ที่โต๊ะก่อนครับ'); window.location='../index.php';</script>";
    exit;
}

$table_id = $_SESSION['table_id'];
$table_no = $_SESSION['table_number'] ?? '';

// ดึงข้อมูลร้าน
$store_res = $conn->query("SELECT restaurant_name FROM owner LIMIT 1");
$store = $store_res->fetch_assoc();

// ดึงออเดอร์ของโต๊ะนี้ ที่สถานะยังไม่จ่ายเงิน (unpaid)
$sql_orders = "SELECT * FROM orders WHERE table_id = ? AND payment_status = 'unpaid' ORDER BY created_at DESC";
$stmt = $conn->prepare($sql_orders);
$stmt->bind_param("i", $table_id);
$stmt->execute();
$orders = $stmt->get_result();

$grand_total = 0;

include '../includes/header_dinein.php'; 
include '../includes/nav_dinein.php'; 
?>

<style>
    .bill-card {
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        border-top: 8px solid var(--cafe-brown);
    }
    .dotted-divider {
        border-top: 2px dashed #ddd;
        margin: 15px 0;
    }
</style>

<div class="container py-4 mb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold m-0 text-dark"><i class="bi bi-receipt-cutoff text-primary me-2"></i>บิลโต๊ะ <?= htmlspecialchars($table_no) ?></h4>
        <a href="menu_dinein.php?table=<?= urlencode($table_no) ?>" class="btn btn-sm btn-outline-secondary rounded-pill shadow-sm">
            <i class="bi bi-plus-circle"></i> สั่งเพิ่ม
        </a>
    </div>

    <?php if ($orders->num_rows > 0): ?>
        <div class="card bill-card p-4">
            <div class="text-center mb-3">
                <h5 class="fw-bold"><?= htmlspecialchars($store['restaurant_name'] ?? 'ร้านของเรา') ?></h5>
                <small class="text-muted">โปรดแสดงหน้านี้ที่เคาน์เตอร์เพื่อชำระเงิน</small>
            </div>
            
            <div class="dotted-divider"></div>

            <?php 
            while ($order = $orders->fetch_assoc()): 
                $grand_total += $order['total_amount'];
                
                // 🟢 แปลงสถานะออเดอร์ให้เป็นข้อความและสีที่เข้าใจง่าย
                $status_color = 'bg-secondary';
                $status_text = 'ไม่ทราบสถานะ';
                switch ($order['order_status']) {
                    case 'pending': 
                        $status_color = 'bg-warning text-dark'; 
                        $status_text = '<i class="bi bi-hourglass-split"></i> รอรับออเดอร์'; 
                        break;
                    case 'cooking': 
                        $status_color = 'bg-primary'; 
                        $status_text = '<i class="bi bi-fire"></i> กำลังปรุง'; 
                        break;
                    case 'ready': 
                        $status_color = 'bg-success'; 
                        $status_text = '<i class="bi bi-check2-circle"></i> พร้อมเสิร์ฟ!'; 
                        break;
                    case 'served': 
                        $status_color = 'bg-info text-dark'; 
                        $status_text = '<i class="bi bi-cup-hot"></i> เสิร์ฟแล้ว'; 
                        break;
                    case 'canceled': 
                        $status_color = 'bg-danger'; 
                        $status_text = '<i class="bi bi-x-circle"></i> ยกเลิก'; 
                        break;
                }

                // ดึงรายการอาหารในแต่ละออเดอร์
                $detail_sql = "SELECT od.*, i.name FROM orderdetail od JOIN item i ON od.item_id = i.item_id WHERE od.order_id = ?";
                $d_stmt = $conn->prepare($detail_sql);
                $d_stmt->bind_param("i", $order['order_id']);
                $d_stmt->execute();
                $details = $d_stmt->get_result();
            ?>
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-light">
                        <div>
                            <span class="fw-bold text-dark small">ออเดอร์ #<?= str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) ?></span>
                            <span class="badge rounded-pill <?= $status_color ?> ms-2 fw-normal" style="font-size: 0.75rem;"><?= $status_text ?></span>
                        </div>
                        <span class="small text-muted"><?= date('H:i', strtotime($order['created_at'])) ?> น.</span>
                    </div>
                    
                    <?php while ($item = $details->fetch_assoc()): ?>
                        <div class="d-flex justify-content-between mb-1 small">
                            <span><?= $item['quantity'] ?>x <?= htmlspecialchars($item['name']) ?></span>
                            <span>฿<?= number_format($item['unit_price'] * $item['quantity'], 2) ?></span>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endwhile; ?>

            <div class="dotted-divider"></div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <span class="h6 mb-0 fw-bold">ยอดสุทธิที่ต้องชำระ</span>
                <span class="h3 mb-0 fw-bold text-success">฿<?= number_format($grand_total, 2) ?></span>
            </div>
            
            <div class="mt-4 text-center">
                <p class="small text-danger fw-bold"><i class="bi bi-info-circle"></i> เมื่อชำระเงินเสร็จสิ้น รายการจะถูกรีเซ็ตอัตโนมัติ</p>
            </div>
        </div>

    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-receipt display-1 text-muted opacity-25"></i>
            <p class="mt-3 text-muted">ยังไม่มีรายการสั่งอาหารสำหรับโต๊ะนี้ครับ</p>
            <a href="menu_dinein.php?table=<?= urlencode($table_no) ?>" class="btn btn-primary rounded-pill px-4 mt-2">กลับไปดูเมนูอาหาร</a>
        </div>
    <?php endif; ?>

</div>

<?php include '../includes/footer_dinein.php'; ?>