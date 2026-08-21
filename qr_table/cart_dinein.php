<?php
// qr_table/cart_dinein.php
session_start();
require_once '../includes/db.php';

// กันหน้านี้โดนแคชไว้ในเบราว์เซอร์ (สำคัญเวลากดปุ่มย้อนกลับหลังปิดออเดอร์ไปแล้ว)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// เช็กว่าสแกนโต๊ะมาจริงไหม และเลือกประเภทออเดอร์ไว้แล้วหรือยัง
if (!isset($_SESSION['table_id']) || !isset($_SESSION['order_type'])) {
    echo "<script>alert('กรุณาสแกน QR Code ที่โต๊ะก่อนครับ'); window.location='../index.php';</script>";
    exit;
}

$order_type = $_SESSION['order_type'];

$table_no = $_SESSION['table_number'] ?? 'ไม่ระบุ';

// ดึงสถานะร้าน
$store_res = $conn->query("SELECT restaurant_name FROM owner LIMIT 1");
$store = $store_res->fetch_assoc();

// 🟢 ระบบนับคิว: ดึงจำนวนออเดอร์ที่ครัวกำลังรับมืออยู่ (pending และ cooking)
$queue_sql = "SELECT COUNT(*) as queue_count FROM orders WHERE order_status IN ('pending', 'cooking')";
$queue_res = $conn->query($queue_sql);
$queue_count = $queue_res->fetch_assoc()['queue_count'] ?? 0;

include '../includes/header_dinein.php'; 
include '../includes/nav_dinein.php'; 
?>

<style>
    .cart-card { border-radius: 20px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
    .item-row { border-bottom: 1px dashed #ddd; padding: 15px 0; }
    .item-row:last-child { border-bottom: none; }
</style>

<div class="container py-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold m-0 text-dark"><i class="bi bi-cart3 me-2"></i>ตะกร้าของคุณ</h4>
        <a href="menu_dinein.php?table=<?= urlencode($table_no) ?>" class="text-decoration-none text-muted fw-bold small"><i class="bi bi-arrow-left"></i> สั่งเพิ่ม</a>
    </div>

    <?php if (empty($_SESSION['cart'])): ?>
        <div class="text-center py-5">
            <i class="bi bi-basket2 display-1 text-muted opacity-25"></i>
            <p class="mt-3 text-muted fw-bold">ยังไม่มีอาหารในตะกร้า</p>
            <a href="menu_dinein.php?table=<?= urlencode($table_no) ?>" class="btn btn-outline-primary rounded-pill px-4 mt-2">กลับไปดูเมนู</a>
        </div>
    <?php else: ?>
        <div class="card cart-card p-3 mb-4">
            <?php 
            $total_raw = 0;
            foreach ($_SESSION['cart'] as $key => $item): 
                $total_raw += ($item['price'] * $item['quantity']);
                $img_path = !empty($item['image']) ? "../assets/images/items/" . $item['image'] : "../assets/images/items/default_food.jpg";
            ?>
                <div class="item-row d-flex align-items-center">
                    <img src="<?= htmlspecialchars($img_path) ?>" class="rounded-3 me-3" style="width: 70px; height: 70px; object-fit: cover;" onerror="this.src='../assets/images/items/default_food.jpg'">
                    <div class="flex-grow-1 pe-2">
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                        <div class="small text-muted mb-1"><?= !empty($item['topping_names']) ? htmlspecialchars($item['topping_names']) : 'ดั้งเดิม'; ?></div>
                        <?php if(!empty($item['note'])): ?>
                            <div class="small text-danger"><i class="bi bi-chat-text"></i> <?= htmlspecialchars($item['note']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="text-end" style="min-width: 80px;">
                        <div class="fw-bold text-dark mb-1">฿<?= number_format($item['price'] * $item['quantity'], 0) ?></div>
                        <div class="small text-muted mb-1">x<?= $item['quantity'] ?></div>
                        <a href="../member/cart_action.php?action=remove&id=<?= $key ?>&return_url=../qr_table/cart_dinein.php" class="text-danger small text-decoration-none fw-bold"><i class="bi bi-trash"></i> ลบ</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($queue_count > 0): ?>
            <div class="alert <?= ($queue_count >= 5) ? 'alert-danger border-danger' : 'alert-warning border-warning' ?> border text-dark rounded-4 mb-4 shadow-sm d-flex align-items-center">
                <i class="bi <?= ($queue_count >= 5) ? 'bi-exclamation-octagon-fill text-danger' : 'bi-info-circle-fill text-warning' ?> fs-1 me-3"></i>
                <div>
                    <h6 class="fw-bold mb-1">คิวปัจจุบัน: <?= $queue_count ?> คิว</h6>
                    <?php if ($queue_count >= 5): ?>
                        <span class="small">ขออภัยค่ะ ขณะนี้ออเดอร์ค่อนข้างเยอะ อาหารอาจจะล่าช้ากว่าปกตินิดหน่อยนะคะ 🙏</span>
                    <?php else: ?>
                        <span class="small">ครัวกำลังเตรียมอาหารให้ตามคิวค่ะ นั่งรอรับความอร่อยได้เลย 👨‍🍳</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card cart-card p-4 border-top border-4 border-success text-center">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <span class="h6 mb-0 fw-bold">ยอดสุทธิรวม</span>
                <span class="h3 mb-0 fw-bold text-success">฿<?= number_format($total_raw, 0) ?></span>
            </div>

            <form action="../member/submit_order.php" method="POST">
                <input type="hidden" name="order_type" value="<?= htmlspecialchars($order_type) ?>">
                <input type="hidden" name="total_amount" value="<?= $total_raw ?>">
                
                <button type="submit" class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow-sm fs-5" onclick="this.innerHTML='กำลังส่งออเดอร์...'; this.disabled=true; this.form.submit();">
                    <i class="bi bi-send-fill me-2"></i> ส่งออเดอร์เข้าครัว
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer_dinein.php'; ?>