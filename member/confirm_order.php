<?php
// member/confirm_order.php
session_start();
require_once '../includes/db.php';
require_once '../includes/csrf.php';

// 1. เช็กความปลอดภัย
if (empty($_SESSION['cart'])) {
    header("Location: ../menu.php");
    exit;
}

// เช็คสถานะร้านอีกครั้งฝั่งเซิร์ฟเวอร์ (จุดตัดสินใจจริงอยู่ที่ submit_order.php แต่เช็คตรงนี้ด้วย
// เพื่อไม่ให้ลูกค้าเห็นหน้ายืนยันออเดอร์ทั้งที่ร้านปิดไปแล้วระหว่างที่กำลังเลือกเมนูอยู่)
if (empty($store['is_shop_open'])) {
    header("Location: ../menu.php");
    exit;
}

// รับค่าจากหน้าตะกร้า
$order_type = $_POST['order_type'] ?? 'takeaway';
$takeaway_name = trim($_POST['takeaway_name'] ?? '');
$table_no = $_SESSION['table_number'] ?? '';
$is_online = !isset($_SESSION['table_id']);

// 2. คำนวณยอดเงินรวมอีกครั้งเพื่อความชัวร์ (Security)
$total_amount = 0;
foreach ($_SESSION['cart'] as $item) {
    $total_amount += ($item['price'] * $item['quantity']);
}

include '../includes/header_customer.php';
include '../includes/nav_customer.php';
?>

<style>
    :root { --cafe-brown: #795548; --cafe-dark: #3e2723; }
    body { background-color: #fdfaf5; font-family: 'Sarabun', sans-serif; padding-bottom: 50px; }
    .confirm-card { border-radius: 25px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); background: #fff; }
    .btn-brown { background: var(--cafe-brown); color: white; border-radius: 50px; transition: 0.3s; }
    .btn-brown:hover { background: var(--cafe-dark); color: white; transform: translateY(-2px); }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
        <h4 class="fw-bold m-0 text-dark"><i class="bi bi-check-circle-fill text-success me-2"></i>ยืนยันคำสั่งซื้อ</h4>
        <a href="cart.php" class="text-decoration-none text-muted fw-bold small"><i class="bi bi-arrow-left"></i> กลับไปตะกร้า</a>
    </div>

    <div class="row justify-content-center g-4">
        <div class="col-lg-6">
            <div class="card confirm-card p-4 mb-4">
                <h6 class="fw-bold mb-3 border-bottom pb-2">สรุปรายการอาหาร</h6>
                <?php foreach ($_SESSION['cart'] as $item): ?>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span><?= $item['quantity'] ?>x <?= htmlspecialchars($item['name']) ?></span>
                        <span class="fw-bold">฿<?= number_format($item['price'] * $item['quantity']) ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="d-flex justify-content-between h5 fw-bold mt-3 pt-3 border-top text-success">
                    <span>ยอดสุทธิที่ต้องชำระ</span>
                    <span>฿<?= number_format($total_amount) ?></span>
                </div>
            </div>

            <form action="submit_order.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="order_type" value="<?= htmlspecialchars($order_type) ?>">
                <input type="hidden" name="total_amount" value="<?= $total_amount ?>">
                <input type="hidden" name="takeaway_name" value="<?= htmlspecialchars($takeaway_name) ?>">

                <?php if ($order_type === 'dine_in'): ?>
                    <div class="alert alert-info rounded-4 border-0 shadow-sm text-center py-4 mb-4">
                        <h5 class="fw-bold text-dark"><i class="bi bi-shop"></i> ทานที่ร้าน (โต๊ะ <?= htmlspecialchars($table_no) ?>)</h5>
                        <p class="mb-0 small text-muted">คุณสามารถกดส่งออเดอร์เข้าครัวได้เลย<br>และชำระเงินภายหลังเมื่อทานเสร็จครับ</p>
                    </div>

                    <button type="submit" class="btn btn-brown w-100 rounded-pill py-3 fw-bold shadow-sm fs-5">
                        <i class="bi bi-send-fill me-2"></i> ส่งออเดอร์เข้าครัว
                    </button>

                <?php else: ?>
                    <div class="card confirm-card border-primary border-2 p-4 mb-4 shadow-sm">
                        <h5 class="fw-bold text-dark mb-2">ข้อมูลผู้สั่ง</h5>

                        <?php if ($is_online): ?>
                        <div class="text-start mb-3">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small fw-bold">ชื่อผู้สั่ง/ชื่อเล่น</label>
                                    <input type="text" name="customer_name_online" class="form-control form-control-sm bg-light border-0" placeholder="ระบุชื่อ" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">เบอร์โทรศัพท์</label>
                                    <input type="tel" name="customer_phone_online" class="form-control form-control-sm bg-light border-0" placeholder="08x-xxx-xxxx" required>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <p class="small text-muted mb-3">คุณ <?= htmlspecialchars($takeaway_name) ?></p>
                        <?php endif; ?>

                        <div class="text-start mb-3">
                            <label class="form-label small fw-bold d-block mb-2">วิธีชำระเงิน (จ่ายที่หน้าเคาน์เตอร์ตอนมารับอาหาร)</label>
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="payQr" value="qr_counter" checked>
                                    <label class="form-check-label fw-bold" for="payQr"><i class="bi bi-qr-code me-1"></i>สแกน QR หน้าเคาน์เตอร์</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="payCash" value="cash">
                                    <label class="form-check-label fw-bold" for="payCash"><i class="bi bi-cash-coin me-1"></i>เงินสดหน้าเคาน์เตอร์</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="payTransfer" value="transfer">
                                    <label class="form-check-label fw-bold" for="payTransfer"><i class="bi bi-bank me-1"></i>โอนเงินเอง แล้วโชว์สลิปตอนมารับ</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow-sm fs-5">
                        <i class="bi bi-check-circle-fill me-2"></i> ยืนยันคำสั่งซื้อ
                    </button>
                <?php endif; ?>
            </form>

        </div>
    </div>
</div>

<?php include '../includes/footer_customer.php'; ?>