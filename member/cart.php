<?php
// member/cart.php
session_start();
require_once '../includes/db.php';

// ดึงข้อมูลโต๊ะ และเช็กว่าออนไลน์หรือหน้าร้าน
$table_no = $_SESSION['table_no'] ?? '';
$is_online = empty($table_no); // ถ้าไม่มีโต๊ะ = สั่งออนไลน์จากที่บ้าน
$is_takeaway_qr = ($table_no === 'กลับบ้าน' || $table_no === 'Takeaway'); // สแกน QR สั่งกลับบ้านหน้าร้าน

// เช็กสถานะร้าน
$store_res = $conn->query("SELECT is_online_open FROM owner LIMIT 1");
$store = $store_res->fetch_assoc();

include '../includes/header_customer.php';
include '../includes/nav_customer.php';
?>

<style>
    :root { --cafe-brown: #795548; --cafe-dark: #3e2723; }
    body { background-color: #fdfaf5; font-family: 'Sarabun', sans-serif; padding-bottom: 50px; }
    .cart-card { border-radius: 25px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); background: #fff; }
    .item-row { border-bottom: 1px solid #f1f1f1; padding: 20px 0; }
    .item-row:last-child { border-bottom: none; }
    .btn-brown { background: var(--cafe-brown); color: white; border-radius: 50px; transition: 0.3s; }
    .btn-brown:hover { background: var(--cafe-dark); color: white; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(121,85,72,0.3); }
    .btn-outline-brown { border: 1px solid var(--cafe-brown); color: var(--cafe-brown); font-weight: bold; }
    .btn-check:checked + .btn-outline-brown { background: var(--cafe-brown); color: white; }
    .sticky-summary { top: 100px; }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
        <h4 class="fw-bold m-0" style="color: var(--cafe-dark);"><i class="bi bi-cart3 me-2"></i>ตะกร้าสินค้า</h4>
        <a href="../menu.php" class="text-decoration-none text-muted fw-bold small"><i class="bi bi-arrow-left"></i> เลือกเมนูเพิ่ม</a>
    </div>

    <?php if($store['is_online_open'] == 0 && empty($_SESSION['table_no'])): ?>
        <div class="alert alert-danger text-center rounded-4 shadow-sm py-4 mb-4">
            <h5 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill"></i> ร้านงดรับออเดอร์ออนไลน์ชั่วคราว</h5>
        </div>
    <?php endif; ?>

    <?php if (empty($_SESSION['cart'])): ?>
        <div class="card cart-card text-center py-5 border-0 mt-4">
            <div class="py-5">
                <i class="bi bi-basket2 display-1 text-muted opacity-25"></i>
                <p class="mt-3 text-muted fw-bold">ยังไม่มีอาหารในตะกร้าของคุณ</p>
                <a href="menu.php" class="btn btn-brown rounded-pill px-5 py-2 mt-2">ดูเมนูอาหารตอนนี้</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card cart-card p-4 border-0">
                    <h5 class="fw-bold mb-3 border-bottom pb-3"><i class="bi bi-card-list me-2 text-muted"></i>รายการที่เลือก</h5>
                    <?php 
                    $total_raw = 0;
                    foreach ($_SESSION['cart'] as $key => $item): 
                        $total_raw += ($item['price'] * $item['quantity']);
                        $img_path = !empty($item['image']) ? "../assets/images/items/" . $item['image'] : "../assets/images/items/default_food.jpg";
                    ?>
                        <div class="item-row d-flex align-items-center">
                            <img src="<?= htmlspecialchars($img_path) ?>" class="rounded-4 me-3 shadow-sm" style="width: 90px; height: 90px; object-fit: cover;" onerror="this.src='../assets/images/items/default_food.jpg'">
                            <div class="flex-grow-1 pe-3">
                                <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($item['name']) ?></h6>
                                <div class="small text-muted mb-1"><i class="bi bi-plus-circle-dotted me-1"></i><?= !empty($item['topping_names']) ? htmlspecialchars($item['topping_names']) : 'รสชาติดั้งเดิม'; ?></div>
                                <?php if(!empty($item['note'])): ?>
                                    <div class="small text-danger italic"><i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars($item['note']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="text-end" style="min-width: 110px;">
                                <div class="fw-bold text-dark fs-5">฿<?= number_format($item['price'] * $item['quantity'], 0) ?></div>
                                <div class="small text-muted mb-2"><?= $item['quantity'] ?> จาน</div>
                                <a href="cart_action.php?action=remove&id=<?= $key ?>" class="text-danger small text-decoration-none fw-bold bg-danger bg-opacity-10 px-2 py-1 rounded"><i class="bi bi-trash3"></i> ลบ</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card cart-card p-4 sticky-top sticky-summary shadow border-0 border-top border-4 border-primary">
                    <h5 class="fw-bold mb-4 text-dark">สรุปคำสั่งซื้อ</h5>
                    <div class="bg-light rounded-4 p-3 my-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="h6 mb-0 fw-bold">ยอดสุทธิ</span>
                            <span class="h3 mb-0 fw-bold text-success">฿<?= number_format($total_raw, 0) ?></span>
                        </div>
                    </div>

                        <form action="confirm_order.php" method="POST">
                            <input type="hidden" name="final_price" value="<?= $total_raw ?>">
                            <input type="hidden" name="order_type" value="<?= ($is_online || $is_takeaway_qr) ? 'takeaway' : 'dine_in' ?>">
                            
                            <?php if ($is_takeaway_qr): ?>
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark small"><i class="bi bi-person-badge-fill me-1"></i> ชื่อลูกค้า (สำหรับเรียกรับคิว)</label>
                                    <input type="text" name="takeaway_name" class="form-control bg-light border-0" placeholder="เช่น พี่เอ, น้องบี" required>
                                </div>
                            <?php endif; ?>

                            <?php if($store['is_online_open'] == 1 || !empty($_SESSION['table_no'])): ?>
                                <button type="submit" class="btn btn-brown w-100 rounded-pill py-3 fw-bold shadow-sm fs-5">
                                    <i class="bi bi-check-circle-fill me-2"></i> ดำเนินการต่อ
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary w-100 rounded-pill py-3 fw-bold disabled">งดรับออเดอร์ชั่วคราว</button>
                            <?php endif; ?>
                        </form>

                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer_customer.php'; ?>