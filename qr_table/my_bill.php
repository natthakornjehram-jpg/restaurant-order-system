<?php
// qr_table/my_bill.php
session_start();
require_once '../includes/db.php';

// กันหน้านี้โดนแคชไว้ในเบราว์เซอร์ (สำคัญเวลากดปุ่มย้อนกลับหลังปิดออเดอร์ไปแล้ว)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// เช็กว่ามีการสแกนโต๊ะมาจริงไหม
if (!isset($_SESSION['table_id'])) {
    echo "<script>alert('กรุณาสแกน QR Code ที่โต๊ะก่อนครับ'); window.location='../index.php';</script>";
    exit;
}

$table_id = $_SESSION['table_id'];
$table_no = $_SESSION['table_number'] ?? '';

// บิลรวมของโต๊ะมีความหมายเฉพาะ "ทานที่ร้าน" เท่านั้น "สั่งกลับบ้าน" เป็นออเดอร์ส่วนตัว แม้จะสแกน QR โต๊ะมา
// ก็ตาม ไม่ควรเห็นบิลรวมที่อาจมีออเดอร์ของคนอื่นที่โต๊ะเดียวกันปนอยู่ ส่งไปหน้าเมนู/สถานะของตัวเองแทน
if (($_SESSION['order_type'] ?? '') !== 'dine_in') {
    header("Location: menu_dinein.php?table=" . urlencode($table_no));
    exit;
}

// เช็กรหัสร่วมโต๊ะเหมือนกับ menu_dinein.php - กันคนอื่นที่ไม่รู้รหัสเข้ามาดูบิล/รายการสั่งของโต๊ะนี้
// (ก่อนหน้านี้หน้านี้เช็คแค่ session table_id ซึ่งถูกตั้งค่าได้แค่เดาเลขโต๊ะจาก URL ของ menu_dinein.php)
$tbl_stmt = $conn->prepare("SELECT join_code FROM restauranttable WHERE table_id = ?");
$tbl_stmt->bind_param("i", $table_id);
$tbl_stmt->execute();
$tbl_data = $tbl_stmt->get_result()->fetch_assoc();
$db_join_code = $tbl_data['join_code'] ?? '';

$is_verified_join = (!empty($_SESSION['has_ordered']) || (isset($_SESSION['user_join_code']) && $_SESSION['user_join_code'] === $db_join_code));

if (!empty($db_join_code) && !$is_verified_join) {
    header("Location: join_table.php?table=" . urlencode($table_no));
    exit;
}

// หมายเหตุ: $store (ชื่อร้าน, is_shop_open ฯลฯ) ถูกโหลดมาครบแล้วจาก includes/db.php ไม่ต้อง query ซ้ำ
// (ของเดิม query ซ้ำแต่ดึงแค่ restaurant_name มาทับ $store ทำให้ nav_dinein.php เห็น is_shop_open หายไป
// ป้ายสถานะร้านบนหน้านี้เลยโชว์ "เปิดรับออเดอร์" ตลอดแม้ร้านจะปิดแล้วก็ตาม)

// ดึงออเดอร์ "ทานที่ร้าน" ของโต๊ะนี้ ที่สถานะยังไม่จ่ายเงิน (unpaid) เท่านั้น
// - ไม่รวมออเดอร์ที่ถูกยกเลิก เพราะไม่ต้องให้ลูกค้าเห็นในบิลอีกต่อไป (และจะ unpaid ค้างตลอดไปเพราะไม่มีทางถูกจ่ายเงิน)
// - กรอง order_type = 'dine_in' ซ้ำอีกชั้น (นอกเหนือจากการ redirect ด้านบน) กันบิลรวมปนกับออเดอร์กลับบ้าน
//   ของคนอื่นที่โต๊ะเดียวกันโดยไม่ตั้งใจ เผื่อกรณี session order_type เปลี่ยนไปหลังโหลดหน้านี้ค้างไว้
$sql_orders = "SELECT * FROM orders WHERE table_id = ? AND order_type = 'dine_in' AND payment_status = 'unpaid' AND order_status != 'canceled' ORDER BY created_at DESC";
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

<div class="container py-4 mb-5" id="myBillContainer">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold m-0 text-dark">
            <?php if (($_SESSION['order_type'] ?? '') === 'takeaway'): ?>
                <i class="bi bi-receipt-cutoff text-primary me-2"></i>ใบสรุปออเดอร์ (สั่งกลับบ้าน)
            <?php else: ?>
                <i class="bi bi-receipt-cutoff text-primary me-2"></i>บิลโต๊ะ <?= htmlspecialchars($table_no) ?>
            <?php endif; ?>
        </h4>
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
                }

                // ดึงรายการอาหารในแต่ละออเดอร์พร้อมท็อปปิ้ง
                $detail_sql = "SELECT od.*, i.name,
                    (SELECT GROUP_CONCAT(t.topping_name SEPARATOR ', ') 
                     FROM orderdetail_topping odt 
                     JOIN topping t ON odt.topping_id = t.topping_id 
                     WHERE odt.order_detail_id = od.order_detail_id) AS topping_names
                    FROM orderdetail od 
                    JOIN item i ON od.item_id = i.item_id 
                    WHERE od.order_id = ?";
                $d_stmt = $conn->prepare($detail_sql);
                $d_stmt->bind_param("i", $order['order_id']);
                $d_stmt->execute();
                $details = $d_stmt->get_result();
            ?>
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-light">
                        <div>
                            <span class="fw-bold text-dark small">คิวที่ #<?= str_pad($order['daily_order_no'] ?: $order['order_id'], 3, '0', STR_PAD_LEFT) ?></span>
                            <span class="badge rounded-pill <?= $status_color ?> ms-2 fw-normal" style="font-size: 0.75rem;"><?= $status_text ?></span>
                        </div>
                        <span class="small text-muted"><?= date('H:i', strtotime($order['created_at'])) ?> น.</span>
                    </div>

                    <?php while ($item = $details->fetch_assoc()): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span class="fw-bold"><?= $item['quantity'] ?>x <?= htmlspecialchars($item['name']) ?></span>
                                <span>฿<?= number_format($item['unit_price'] * $item['quantity'], 2) ?></span>
                            </div>
                            <?php if(!empty($item['topping_names'])): ?>
                                <div class="small text-muted ms-3">+ <?= htmlspecialchars($item['topping_names']) ?></div>
                            <?php endif; ?>
                            <?php if(!empty($item['note'])): ?>
                                <div class="small text-danger ms-3">* <?= htmlspecialchars($item['note']) ?></div>
                            <?php endif; ?>
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
                <a href="menu_dinein.php?table=<?= urlencode($table_no) ?>" class="btn btn-warning w-100 rounded-pill py-3 fw-bold shadow mb-3" style="background-color: var(--cafe-gold); border: none; color: var(--cafe-dark);">
                    <i class="bi bi-plus-circle-fill me-2"></i> สั่งอาหารเพิ่ม
                </a>
                <p class="small text-muted mb-0"><i class="bi bi-info-circle me-1"></i> เมื่อชำระเงินที่เคาน์เตอร์เสร็จสิ้น ระบบจะทำการปิดบิลและคืนสถานะให้อัตโนมัติ</p>
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