<?php
// customer/order_detail.php
session_start();
require_once '../includes/db.php';

// 1. เช็กสิทธิ์เบื้องต้น (ต้องล็อกอินสมาชิก หรือ เป็นคนสแกนสั่งที่โต๊ะ)
$customer_id = $_SESSION['customer_id'] ?? NULL;
$table_id = $_SESSION['table_id'] ?? NULL;
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 2. ดึงข้อมูลร้านค้า
$store = $conn->query("SELECT restaurant_name FROM owner LIMIT 1")->fetch_assoc();
$restaurant_name = $store['restaurant_name'] ?? 'RANNAIBAAN';

// 3. ดึงข้อมูลออเดอร์หลัก
// เช็กความเป็นเจ้าของออเดอร์: สมาชิกต้องเป็นเจ้าของออเดอร์นั้นจริง, แขกที่สแกนโต๊ะต้องดูได้เฉพาะออเดอร์ของโต๊ะตัวเอง
// ถ้าไม่มีทั้ง customer_id และ table_id ในเซสชัน ห้ามดูออเดอร์ใดๆ ทั้งสิ้น (กัน IDOR)
if ($customer_id) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?");
    $stmt->bind_param("ii", $order_id, $customer_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
} elseif ($table_id) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND table_id = ? AND customer_id IS NULL");
    $stmt->bind_param("ii", $order_id, $table_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
} else {
    $order = null;
}

// 4. ดึงข้อมูลการชำระเงิน (สลิป)
$sql_pay = "SELECT slip_image FROM payment WHERE order_id = ? LIMIT 1";
$stmt_pay = $conn->prepare($sql_pay);
$stmt_pay->bind_param("i", $order_id);
$stmt_pay->execute();
$payment = $stmt_pay->get_result()->fetch_assoc();



if (!$order) {
    die("<div class='container mt-5 alert alert-danger text-center rounded-4'>ไม่พบข้อมูลออเดอร์นี้</div>");
}

include '../includes/header_customer.php'; 
include '../includes/nav_customer.php'; 

?>

<style>
    :root { --cafe-brown: #795548; --cafe-dark: #3e2723; }
    body { background-color: #fdfaf5; font-family: 'Sarabun', sans-serif; padding-bottom: 50px; }
    .receipt-card {
        background: white; border-radius: 30px; border: none;
        box-shadow: 0 15px 35px rgba(62,39,35,0.05); padding: 30px;
    }
    .status-badge { border-radius: 50px; padding: 6px 18px; font-weight: bold; font-size: 0.85rem; }
    .item-row { border-bottom: 1px dashed #eee; padding: 12px 0; }
</style>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 mt-3">
            
            <div class="mb-3 px-2">
                <a href="history.php" class="text-decoration-none text-muted fw-bold small">
                    <i class="bi bi-chevron-left"></i> ย้อนกลับ
                </a>
            </div>

            <div class="card receipt-card">
                <div class="text-center mb-4">
                    <div class="fs-3 fw-bold text-dark mb-1"><i class="bi bi-shop me-2"></i><?= htmlspecialchars($restaurant_name) ?></div>
                    <div class="text-muted small mb-3">วันที่สั่ง: <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></div>
                    
                    <?php 
                        // ปรับให้ตรงกับค่าใน MySQL ของคุณ (pending, cooking, ready, served, cancelled)
                        $status_colors = [
                            'pending'  => 'bg-warning text-dark',
                            'cooking'  => 'bg-info text-white',
                            'ready'    => 'bg-primary text-white', 
                            'served'   => 'bg-success text-white', 
                            'canceled' => 'bg-danger text-white'   
                        ];

                        $status_text = [
                            'pending'  => '⏳ รอรับออเดอร์',
                            'cooking'  => '👨‍🍳 กำลังปรุงอาหาร',
                            'ready'    => '🔔 อาหารพร้อมเสิร์ฟ',
                            'served'   => '✅ สำเร็จเรียบร้อย',
                            'canceled' => '❌ ยกเลิกออเดอร์'
                        ];
                    ?>
                    <span class="status-badge <?= $status_colors[$order['order_status']] ?? 'bg-secondary text-white' ?> shadow-sm">
                        <?= $status_text[$order['order_status']] ?? $order['order_status'] ?>
                    </span>
                    <hr class="mt-4 mb-2 opacity-10">
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-3">รายการอาหาร</h6>
                    <?php 
                    // ดึงรายการอาหาร
                    $sql_items = "SELECT od.*, i.name AS item_name 
                                  FROM orderdetail od 
                                  JOIN item i ON od.item_id = i.item_id 
                                  WHERE od.order_id = ?";
                    $stmt_items = $conn->prepare($sql_items);
                    $stmt_items->bind_param("i", $order_id);
                    $stmt_items->execute();
                    $items_res = $stmt_items->get_result();

                    while($item = $items_res->fetch_assoc()):
                        $subtotal = $item['quantity'] * $item['unit_price'];
                    ?>
                    <div class="item-row d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="fw-bold text-dark"><?= $item['quantity'] ?>x <?= htmlspecialchars($item['item_name']) ?></div>
                            <?php if(!empty($item['note'])): ?>
                                <small class="text-danger d-block ms-2 small italic">* <?= htmlspecialchars($item['note']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="text-end fw-bold">฿<?= number_format($subtotal) ?></div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <div class="bg-light p-3 rounded-4 mb-3 border border-light">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">ประเภทการสั่ง</span>
                        <span class="fw-bold small">
                            <?= ($order['order_type'] == 'dine_in') ? 'ทานที่ร้าน' : 'กลับบ้าน' ?>
                        </span>
                    </div>

                    <?php if($order['order_type'] == 'dine_in' && !empty($order['table_id'])): 
                        $t_stmt = $conn->prepare("SELECT table_number FROM restauranttable WHERE table_id = ?");
                        $t_stmt->bind_param("i", $order['table_id']);
                        $t_stmt->execute();
                        $t_data = $t_stmt->get_result()->fetch_assoc();
                    ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">หมายเลขโต๊ะ</span>
                        <span class="fw-bold small"><?= htmlspecialchars($t_data['table_number'] ?? '-') ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between h5 fw-bold mt-3 pt-3 border-top">
                        <span class="text-dark">ยอดสุทธิ</span>
                        <span class="text-success fs-4">฿<?= number_format($order['total_amount']) ?></span>
                    </div>
                    <?php if (!empty($payment['slip_image'])): ?>
                        <div class="mt-4 p-3 rounded-4 border text-center" style="background-color: #f8f9fa;">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-image me-1"></i> หลักฐานการโอนเงิน</h6>
                            
                            <a href="../assets/images/slips/<?= htmlspecialchars($payment['slip_image']) ?>" target="_blank">
                                <img src="../assets/images/slips/<?= htmlspecialchars($payment['slip_image']) ?>" 
                                    alt="Payment Slip" 
                                    class="img-fluid rounded-3 shadow-sm border" 
                                    style="max-height: 300px; cursor: zoom-in;">
                            </a>
                            
                            <div class="mt-2 small text-muted">
                                <i class="bi bi-info-circle me-1"></i> คลิกที่รูปเพื่อดูขนาดเต็ม
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-4 py-2 fw-bold w-100 shadow-sm">
                    <i class="bi bi-printer me-2"></i> พิมพ์ใบเสร็จ
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer_customer.php'; ?>