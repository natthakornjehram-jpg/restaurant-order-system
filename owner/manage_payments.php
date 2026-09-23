<?php 
// owner/manage_payments.php
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';

include '../includes/header_owner.php'; 
include '../includes/nav_owner.php'; 

// --- แยกการดึงข้อมูลเป็น 2 กลุ่ม ---

// 1. ออนไลน์ (สั่งกลับบ้าน จ่ายเงินสดตอนมารับ รอร้านยืนยันปิดออเดอร์)
// เพิ่ม p.slip_image เข้ามาด้วย เพื่อโชว์สลิปที่ลูกค้าแนบมาตอนสั่งจริง (กรณีเลือกโอนเงินเอง) ให้ร้านตรวจสอบได้
// order_status != 'canceled' กันออเดอร์กลับบ้านที่เจ้าของร้านยกเลิกไปแล้ว (ตอนยัง pending) โผล่มาค้างรอปิดออเดอร์อยู่ตรงนี้
$sql_online = "SELECT o.*, p.status AS pay_status, p.method AS pay_method, p.transaction_ref, p.slip_image
        FROM orders o
        JOIN payment p ON o.order_id = p.order_id
        WHERE o.payment_status = 'unpaid' AND o.order_type != 'dine_in' AND p.status = 'pending' AND o.order_status != 'canceled'
        ORDER BY o.created_at ASC";
$res_online = $conn->query($sql_online);

// 2. หน้าร้าน (กินเสร็จแล้ว / อาหารเสิร์ฟแล้ว รอจ่ายเงิน)
$sql_served = "SELECT o.*, t.table_number FROM orders o
        LEFT JOIN restauranttable t ON o.table_id = t.table_id
        WHERE o.payment_status = 'unpaid' AND o.order_type = 'dine_in' AND o.order_status IN ('ready', 'served')
        ORDER BY o.created_at DESC";
$res_served = $conn->query($sql_served);
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/owner-manage-payments.css?v=<?= time() ?>">

<div class="main-content container-fluid text-dark pb-5 px-4 pt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-white rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; border: 1px solid #edf2f7; background: #ffffff; color: #4a5568;">
                <i class="bi bi-arrow-left fs-4"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-0" style="color: #1a202c; font-size: 1.25rem;"><i class="bi bi-wallet2 text-success me-2"></i>จัดการชำระเงิน</h4>
                <p class="text-muted small mb-0">รับเงินหน้าร้าน และ ปิดออเดอร์สั่งกลับบ้าน</p>
            </div>
        </div>
    </div>
    
    <ul class="nav nav-pills mb-4 gap-2" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-3 px-4 py-3 fw-bold shadow-sm" id="pills-served-tab" data-bs-toggle="pill" data-bs-target="#pills-served" type="button" role="tab">
                <i class="bi bi-shop me-1"></i> ทานที่ร้าน (รอเช็คบิล)
                <span id="servedCountBadge" class="badge bg-success rounded-circle ms-2" style="<?= ($res_served && $res_served->num_rows > 0) ? '' : 'display:none;' ?>"><?= $res_served ? $res_served->num_rows : 0 ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 px-4 py-3 fw-bold shadow-sm" id="pills-online-tab" data-bs-toggle="pill" data-bs-target="#pills-online" type="button" role="tab">
                <i class="bi bi-bag me-1"></i> สั่งกลับบ้าน (รอปิดออเดอร์)
                <span id="onlineCountBadge" class="badge bg-danger rounded-circle ms-2" style="<?= ($res_online && $res_online->num_rows > 0) ? '' : 'display:none;' ?>"><?= $res_online ? $res_online->num_rows : 0 ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="pills-tabContent">
        
        <!-- 🟢 TAB 1: หน้าร้านรอเช็คบิล (อาหารปรุงเสิร์ฟเสร็จเรียบร้อยแล้วเท่านั้น) -->
        <div class="tab-pane fade show active" id="pills-served" role="tabpanel">
            <div class="row g-4">
                <?php if($res_served && $res_served->num_rows > 0): while($row = $res_served->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4 col-xl-3" id="payment-col-<?php echo $row['order_id'];?>">
                    <div class="soft-card h-100 border-top border-4 border-success shadow-sm">
                        <div class="card-body p-4 text-start d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-warning text-dark fw-bold px-3 py-2 fs-6">
                                        <i class="bi bi-shop me-1"></i> โต๊ะ <?php echo htmlspecialchars($row['table_number'] ?? $row['table_id']); ?>
                                    </span>
                                    <div class="order-id text-primary fw-bold fs-5">
                                        #<?php echo str_pad($row['daily_order_no'] ?: $row['order_id'], 3, '0', STR_PAD_LEFT);?>
                                    </div>
                                </div>
                                <div class="text-muted small mb-3">
                                    <i class="bi bi-clock me-1"></i> เวลาสั่ง: <?php echo date('d/m/Y H:i', strtotime($row['created_at']));?> น.
                                </div>

                                <div class="fw-bold small text-muted mb-2 text-uppercase" style="letter-spacing: 0.5px;">
                                    <i class="bi bi-list-check me-1"></i> รายการอาหารที่สั่ง
                                </div>

                                <div class="bg-light rounded-3 p-3 mb-3 border">
                                    <?php 
                                    $curr_ord_id = (int)$row['order_id'];
                                    $card_items_sql = "SELECT od.*, i.name AS item_name, i.price AS item_price,
                                        (SELECT GROUP_CONCAT(t.topping_name SEPARATOR ', ') 
                                         FROM orderdetail_topping odt 
                                         JOIN topping t ON odt.topping_id = t.topping_id 
                                         WHERE odt.order_detail_id = od.order_detail_id) AS topping_names
                                        FROM orderdetail od
                                        JOIN item i ON od.item_id = i.item_id
                                        WHERE od.order_id = $curr_ord_id";
                                    $card_items = $conn->query($card_items_sql);
                                    if ($card_items && $card_items->num_rows > 0):
                                        while($item = $card_items->fetch_assoc()):
                                            $item_unit_price = isset($item['unit_price']) ? (float)$item['unit_price'] : (float)($item['item_price'] ?? 0);
                                            $item_subtotal = $item_unit_price * (int)$item['quantity'];
                                    ?>
                                        <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                                            <div>
                                                <div class="fw-bold text-dark mb-0">
                                                    <?php echo htmlspecialchars($item['item_name']); ?> 
                                                    <span class="text-muted small">x <?php echo $item['quantity']; ?></span>
                                                </div>
                                                <?php if (!empty($item['topping_names'])): ?>
                                                    <small class="text-muted d-block">
                                                        <i class="bi bi-plus-circle me-1"></i>+ <?php echo htmlspecialchars($item['topping_names']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="fw-bold text-dark ms-3">
                                                ฿<?php echo number_format($item_subtotal, 2); ?>
                                            </div>
                                        </div>
                                    <?php 
                                        endwhile; 
                                    else: 
                                    ?>
                                        <div class="text-muted small text-center py-2">ไม่พบรายละเอียดรายการอาหาร</div>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between align-items-center pt-2">
                                        <span class="fw-bold text-dark">ราคารวมสุทธิ</span>
                                        <span class="h3 fw-bold text-success m-0">฿<?php echo number_format($row['total_amount'], 2);?></span>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="fw-bold small mb-1 d-block text-dark">
                                        <i class="bi bi-wallet2 me-1"></i> วิธีรับเงิน
                                    </label>
                                    <select id="payMethod_<?php echo $row['order_id'];?>" class="form-select rounded-3 border-success fw-bold">
                                        <option value="transfer" selected>📱 โอนเงิน / สแกน QR</option>
                                        <option value="cash">💵 เงินสด</option>
                                    </select>
                                </div>
                            </div>

                            <button type="button" class="btn btn-success w-100 rounded-pill py-3 fw-bold shadow-sm" onclick="approveDineInPayment(<?php echo $row['order_id'];?>)">
                                <i class="bi bi-check-circle me-1"></i> ยืนยันรับเงิน & ปิดบิล
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-receipt display-1 text-muted opacity-25"></i>
                        <h5 class="mt-3 text-muted">ยังไม่มีโต๊ะที่ปรุงอาหารเสร็จรอเช็คบิล</h5>
                        <p class="small text-muted">เมื่อห้องครัวกด "ปรุงเสร็จแล้ว" รายการจะย้ายมาที่นี่ให้อัตโนมัติ</p>
                    </div>
                <?php endif;?>
            </div>
        </div>

        <!-- 🟢 TAB 2: กลับบ้านรอปิดออเดอร์ (จ่ายเงินสดตอนมารับ) -->
        <div class="tab-pane fade" id="pills-online" role="tabpanel">
            <div class="row g-4">
                <?php if($res_online && $res_online->num_rows > 0): while($row = $res_online->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4 col-xl-3" id="payment-col-<?php echo $row['order_id'];?>">
                    <div class="soft-card h-100 border-top border-4 border-primary">
                        <div class="card-body p-4 text-center d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-primary"><i class="bi bi-phone"></i> <?php echo htmlspecialchars($row['online_customer_name'] ?: 'กลับบ้าน');?></span>
                                    <div class="order-id text-primary fw-bold fs-5">#<?php echo str_pad($row['daily_order_no'] ?: $row['order_id'], 3, '0', STR_PAD_LEFT);?></div>
                                </div>
                                <div class="mb-3 mt-3">
                                    <?php if ($row['pay_method'] === 'transfer'): ?>
                                        <span class="badge-soft-info"><i class="bi bi-bank"></i> โอนเงินเอง<?= !empty($row['slip_image']) ? ' (แนบสลิปแล้ว)' : ' (ยังไม่แนบสลิป)' ?></span>
                                    <?php elseif ($row['pay_method'] === 'qr_counter'): ?>
                                        <span class="badge-soft-info"><i class="bi bi-qr-code"></i> สแกน QR หน้าเคาน์เตอร์</span>
                                    <?php else: ?>
                                        <span class="badge-soft-info"><i class="bi bi-cash-coin"></i> จ่ายเงินสดตอนมารับ</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-muted small mb-1">ยอดที่ต้องชำระ</p>
                                <div class="total-price mb-4 text-primary">฿<?php echo number_format($row['total_amount'], 0);?></div>
                            </div>
                            <button class="btn btn-primary w-100 rounded-3 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#payModal<?php echo $row['order_id'];?>">
                                <i class="bi bi-check-circle me-1"></i> ยืนยันออเดอร์
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal fade text-dark" id="payModal<?php echo $row['order_id'];?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-4 border-0 shadow">
                            <div class="modal-header border-bottom bg-light py-3">
                                <div>
                                    <h5 class="modal-title fw-bold mb-0">
                                        <i class="bi bi-receipt me-1 text-primary"></i> รายละเอียดออเดอร์ #<?php echo str_pad($row['daily_order_no'] ?: $row['order_id'], 3, '0', STR_PAD_LEFT);?>
                                    </h5>
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i> เวลาสั่ง: <?php echo date('d/m/Y H:i', strtotime($row['created_at']));?> น.
                                    </small>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-4 text-start">
                                <div class="p-3 bg-light rounded-3 mb-3 border">
                                    <div class="fw-bold text-dark">
                                        <i class="bi bi-person me-1"></i> ผู้สั่ง: <?php echo htmlspecialchars($row['online_customer_name'] ?: 'กลับบ้าน');?>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        <i class="bi bi-telephone me-1"></i> เบอร์โทร: <?php echo htmlspecialchars($row['online_customer_phone'] ?: '-');?>
                                    </small>
                                </div>

                                <div class="fw-bold small text-muted mb-2 text-uppercase" style="letter-spacing: 0.5px;">
                                    <i class="bi bi-list-check me-1"></i> รายการอาหารที่สั่ง
                                </div>

                                <div class="bg-light rounded-3 p-3 mb-4 border border-light">
                                    <?php 
                                    $curr_ord_id = (int)$row['order_id'];
                                    $modal_items_sql = "SELECT od.*, i.name AS item_name, i.price AS item_price,
                                        (SELECT GROUP_CONCAT(t.topping_name SEPARATOR ', ') 
                                         FROM orderdetail_topping odt 
                                         JOIN topping t ON odt.topping_id = t.topping_id 
                                         WHERE odt.order_detail_id = od.order_detail_id) AS topping_names
                                        FROM orderdetail od
                                        JOIN item i ON od.item_id = i.item_id
                                        WHERE od.order_id = $curr_ord_id";
                                    $modal_items = $conn->query($modal_items_sql);
                                    if ($modal_items && $modal_items->num_rows > 0):
                                        while($item = $modal_items->fetch_assoc()):
                                            $item_unit_price = isset($item['unit_price']) ? (float)$item['unit_price'] : (float)($item['item_price'] ?? 0);
                                            $item_subtotal = $item_unit_price * (int)$item['quantity'];
                                    ?>
                                        <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                                            <div>
                                                <div class="fw-bold text-dark mb-0">
                                                    <?php echo htmlspecialchars($item['item_name']); ?> 
                                                    <span class="text-muted small">x <?php echo $item['quantity']; ?></span>
                                                </div>
                                                <?php if (!empty($item['topping_names'])): ?>
                                                    <small class="text-muted d-block">
                                                        <i class="bi bi-plus-circle me-1"></i>+ <?php echo htmlspecialchars($item['topping_names']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="fw-bold text-dark ms-3">
                                                ฿<?php echo number_format($item_subtotal, 2); ?>
                                            </div>
                                        </div>
                                    <?php 
                                        endwhile; 
                                    else: 
                                    ?>
                                        <div class="text-muted small text-center py-2">ไม่พบรายละเอียดรายการอาหาร</div>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between align-items-center pt-2">
                                        <span class="fw-bold text-dark fs-5">ราคารวมสุทธิ</span>
                                        <span class="h3 fw-bold text-primary m-0">฿<?php echo number_format($row['total_amount'], 2);?></span>
                                    </div>
                                </div>

                                <div class="p-3 bg-light rounded-3 text-center mb-3 border">
                                    <?php if ($row['pay_method'] === 'transfer'): ?>
                                        <?php if (!empty($row['slip_image'])): ?>
                                            <p class="fw-bold mb-2 text-dark"><i class="bi bi-bank me-1"></i>ลูกค้าเลือกโอนเงินเอง แนบสลิปมาด้วย - กรุณาตรวจสอบก่อนยืนยัน</p>
                                            <a href="../assets/images/slips/<?= htmlspecialchars($row['slip_image']) ?>" target="_blank">
                                                <img src="../assets/images/slips/<?= htmlspecialchars($row['slip_image']) ?>" alt="สลิปการโอนเงิน" class="img-fluid rounded-3 shadow-sm border" style="max-height: 320px;">
                                            </a>
                                            <div class="small text-muted mt-1">กดที่รูปเพื่อดูขนาดเต็ม</div>
                                        <?php else: ?>
                                            <i class="bi bi-exclamation-triangle text-warning fs-3 mb-1 d-block"></i>
                                            <p class="fw-bold mb-0 text-dark">ลูกค้าเลือกโอนเงินเอง แต่ยังไม่มีสลิปแนบมา กรุณาตรวจสอบก่อนยืนยัน</p>
                                        <?php endif; ?>
                                    <?php elseif ($row['pay_method'] === 'qr_counter'): ?>
                                        <i class="bi bi-qr-code text-primary fs-3 mb-1 d-block"></i>
                                        <p class="fw-bold mb-0 text-dark">ลูกค้าเลือกสแกน QR จ่ายที่หน้าเคาน์เตอร์</p>
                                    <?php else: ?>
                                        <i class="bi bi-cash-coin text-success fs-3 mb-1 d-block"></i>
                                        <p class="fw-bold mb-0 text-dark">ลูกค้าเลือกจ่ายเงินสดตอนมารับที่ร้าน</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="modal-footer border-0 bg-light py-3">
                                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>
                                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="approvePayment(<?php echo $row['order_id'];?>, '<?php echo htmlspecialchars($row['pay_method']);?>')">
                                    <i class="bi bi-check-circle me-1"></i> ยืนยันออเดอร์ & ปิดออเดอร์
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                    <div class="col-12 text-center py-5"><h5 class="text-muted"><i class="bi bi-check-circle"></i> ไม่มีออเดอร์กลับบ้านรอปิด</h5></div>
                <?php endif;?>
            </div>
        </div>

    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/owner-manage-payments.js"></script>

<?php include '../includes/footer_owner.php';?>