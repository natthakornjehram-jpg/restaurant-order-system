<?php
// customer/order_detail.php
session_start();
require_once '../includes/db.php';

// 1. เช็กสิทธิ์เบื้องต้น (ไม่มีระบบบัญชีลูกค้าแล้ว - เช็กจาก session ของแขกที่สแกนโต๊ะ หรือแขกที่เพิ่งสั่งออนไลน์/กลับบ้านเอง)
$table_id = $_SESSION['table_id'] ?? NULL;
$guest_order_ids = $_SESSION['guest_order_ids'] ?? [];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 2. ดึงข้อมูลร้านค้า
$store = $conn->query("SELECT restaurant_name, logo_url FROM owner LIMIT 1")->fetch_assoc();
$restaurant_name = $store['restaurant_name'] ?? 'My Restaurant';

// 3. ดึงข้อมูลออเดอร์หลัก
// เช็กความเป็นเจ้าของออเดอร์: แขกที่สแกนโต๊ะดูได้เฉพาะออเดอร์ของโต๊ะตัวเอง, แขกออนไลน์/กลับบ้านดูได้เฉพาะออเดอร์ที่ session ตัวเองเพิ่งสั่ง
// ถ้าไม่เข้าเงื่อนไขไหนเลย ห้ามดูออเดอร์ใดๆ ทั้งสิ้น (กัน IDOR)
if ($table_id) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND table_id = ?");
    $stmt->bind_param("ii", $order_id, $table_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
} elseif (in_array($order_id, $guest_order_ids, true)) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
} else {
    $order = null;
}

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
                <a href="../menu.php" class="text-decoration-none text-muted fw-bold small">
                    <i class="bi bi-chevron-left"></i> กลับไปหน้าเมนู
                </a>
            </div>

            <div class="card receipt-card">
                <div class="text-center mb-4">
                    <div class="fs-3 fw-bold text-dark mb-1">
                        <?php if (!empty($store['logo_url']) && $store['logo_url'] !== 'default_logo.png'): ?>
                            <img src="../assets/images/logos/<?= htmlspecialchars($store['logo_url']) ?>" alt="logo" style="width:28px;height:28px;object-fit:cover;border-radius:50%;" class="me-2">
                        <?php else: ?>
                            <i class="bi bi-shop me-2"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($restaurant_name) ?>
                    </div>
                    <div class="text-muted small mb-2">วันที่สั่ง: <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></div>
                    <div class="fw-bold mb-3" style="font-size: 1.3rem; color: var(--cafe-brown);">
                        คิวที่ #<?= str_pad($order['daily_order_no'] ?: $order['order_id'], 3, '0', STR_PAD_LEFT) ?>
                    </div>

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
                    // ดึงรายการอาหารพร้อมท็อปปิ้ง
                    $sql_items = "SELECT od.*, i.name AS item_name,
                                  (SELECT GROUP_CONCAT(t.topping_name SEPARATOR ', ') 
                                   FROM orderdetail_topping odt 
                                   JOIN topping t ON odt.topping_id = t.topping_id 
                                   WHERE odt.order_detail_id = od.order_detail_id) AS topping_names
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
                            <?php if(!empty($item['topping_names'])): ?>
                                <small class="text-muted d-block ms-2 small">+ <?= htmlspecialchars($item['topping_names']) ?></small>
                            <?php endif; ?>
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
                </div>

                <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-4 py-2 fw-bold w-100 shadow-sm">
                    <i class="bi bi-printer me-2"></i> พิมพ์ใบเสร็จ
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer_customer.php'; ?>