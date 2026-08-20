<?php
// member/confirm_order.php
session_start();
require_once '../includes/db.php';

// 1. เช็กความปลอดภัย
if (empty($_SESSION['cart'])) {
    header("Location: ../menu.php");
    exit;
}

// รับค่าจากหน้าตะกร้า
$order_type = $_POST['order_type'] ?? 'takeaway';
$takeaway_name = trim($_POST['takeaway_name'] ?? '');
$table_no = $_SESSION['table_number'] ?? '';
$is_online = !isset($_SESSION['table_id']);

// ถ้าเป็นลูกค้าออนไลน์จากบ้าน ต้องเช็กว่าล็อกอินหรือยัง
if ($is_online && !isset($_SESSION['customer_id'])) {
    header("Location: ../login_customer.php");
    exit;
}

// 2. คำนวณยอดเงินรวมอีกครั้งเพื่อความชัวร์ (Security)
$total_amount = 0;
foreach ($_SESSION['cart'] as $item) {
    $total_amount += ($item['price'] * $item['quantity']);
}

// ดึงข้อมูลร้าน (เอาไว้โชว์เลขบัญชี)
$store = $conn->query("SELECT bank_info, promptpay_qr FROM owner LIMIT 1")->fetch_assoc();

include '../includes/header_customer.php';
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

            <form action="submit_order.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="order_type" value="<?= htmlspecialchars($order_type) ?>">
                <input type="hidden" name="total_amount" value="<?= $total_amount ?>">
                <input type="hidden" name="takeaway_name" value="<?= htmlspecialchars($takeaway_name) ?>">

                <?php if (!$is_online): ?>
                    <div class="alert alert-info rounded-4 border-0 shadow-sm text-center py-4 mb-4">
                        <?php if ($order_type === 'dine_in'): ?>
                            <h5 class="fw-bold text-dark"><i class="bi bi-shop"></i> ทานที่ร้าน (โต๊ะ <?= htmlspecialchars($table_no) ?>)</h5>
                            <p class="mb-0 small text-muted">คุณสามารถกดส่งออเดอร์เข้าครัวได้เลย<br>และชำระเงินภายหลังเมื่อทานเสร็จครับ</p>
                        <?php else: ?>
                            <h5 class="fw-bold text-dark"><i class="bi bi-bag"></i> สั่งกลับบ้าน (คุณ <?= htmlspecialchars($takeaway_name) ?>)</h5>
                            <p class="mb-0 small text-muted">กรุณากดส่งออเดอร์เข้าครัว<br>และชำระเงินที่หน้าเคาน์เตอร์ครับ</p>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-brown w-100 rounded-pill py-3 fw-bold shadow-sm fs-5">
                        <i class="bi bi-send-fill me-2"></i> ส่งออเดอร์เข้าครัว
                    </button>

                <?php else: ?>
                    <div class="card confirm-card border-primary border-2 p-4 mb-4 shadow-sm">
                        <h5 class="fw-bold text-dark mb-2">ข้อมูลผู้สั่งและชำระเงิน</h5>
                        
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
                        <div class="card confirm-card border-primary border-2 p-4 mb-4 text-center shadow-sm">
                        <h5 class="fw-bold text-dark mb-2">ชำระเงินผ่านการโอน</h5>
                        <p class="small text-muted mb-3">กรุณาโอนเงินตามยอดสุทธิ และแนบสลิปด้านล่าง</p>
                        
                        <?php if (!empty($store['promptpay_qr'])): ?>
                            <img src="../assets/images/promptpay_qr/<?= htmlspecialchars($store['promptpay_qr']) ?>" alt="PromptPay QR" class="img-fluid rounded-4 mb-3 shadow-sm" style="max-height: 250px; width: auto;">
                        <?php else: ?>
                            <div class="p-4 bg-light rounded-4 mb-3 border">
                                <i class="bi bi-bank text-muted display-4 mb-2 d-block"></i>
                                <div class="fw-bold fs-5"><?= nl2br(htmlspecialchars($store['bank_info'] ?? 'กรุณาติดต่อร้านเพื่อขอเลขบัญชี')) ?></div>
                            </div>
                             <div class="text-start">
                            <label class="form-label fw-bold text-primary small"><i class="bi bi-cloud-arrow-up-fill me-1"></i> แนบสลิปโอนเงิน <span class="text-danger">*</span></label>
                            <input type="file" name="payment_slip" class="form-control bg-light border-0 shadow-sm" accept="image/*" required>
                        </div>
                        <?php endif; ?>
                    <hr>        
                    <button type="submit" class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow-sm fs-5">
                        <i class="bi bi-check-circle-fill me-2"></i> ยืนยันคำสั่งซื้อและแจ้งโอนเงิน
                    </button>
                <?php endif; ?>
            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include '../includes/footer_customer.php'; ?>