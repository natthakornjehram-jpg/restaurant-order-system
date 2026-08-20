<?php 
// owner/manage_payments.php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['owner_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../includes/header_owner.php'; 
include '../includes/nav_owner.php'; 

// --- แยกการดึงข้อมูลเป็น 3 กลุ่ม ---

// 1. ออนไลน์ (แนบสลิปแล้ว รอร้านตรวจและอนุมัติเข้าครัว)
$sql_online = "SELECT o.*, p.status AS pay_status, p.slip_image, p.transaction_ref 
        FROM orders o 
        JOIN payment p ON o.order_id = p.order_id 
        WHERE o.payment_status = 'unpaid' AND o.order_type != 'dine_in' AND p.status = 'pending'
        ORDER BY o.created_at ASC";
$res_online = $conn->query($sql_online);

// 2. หน้าร้าน (กินเสร็จแล้ว / อาหารเสิร์ฟแล้ว รอจ่ายเงิน)
$sql_served = "SELECT * FROM orders 
        WHERE payment_status = 'unpaid' AND order_type = 'dine_in' AND order_status IN ('ready', 'served')
        ORDER BY created_at DESC";
$res_served = $conn->query($sql_served);

// 3. หน้าร้าน (กำลังกิน / ครัวกำลังทำ / เพิ่งสั่ง)
$sql_eating = "SELECT * FROM orders 
        WHERE payment_status = 'unpaid' AND order_type = 'dine_in' AND order_status IN ('pending', 'cooking')
        ORDER BY created_at DESC";
$res_eating = $conn->query($sql_eating);
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/owner-manage-payments.css">

<div class="main-content container-fluid text-dark pb-5 px-4 pt-4" style="margin-top: 80px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-white rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; border: 1px solid #edf2f7; background: #ffffff; color: #4a5568;">
                <i class="bi bi-arrow-left fs-4"></i>
            </a>
            <div>
                <h2 class="fw-bold mb-0" style="color: #1a202c;"><i class="bi bi-wallet2 text-success me-2"></i>จัดการชำระเงิน</h2>
                <p class="text-muted small mb-0">รับเงินหน้าร้าน และ ตรวจสอบสลิปออนไลน์</p>
            </div>
        </div>
    </div>
    
    <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pills-online-tab" data-bs-toggle="pill" data-bs-target="#pills-online" type="button" role="tab">
                <i class="bi bi-phone me-1"></i> ออนไลน์รอตรวจสลิป 
                <?php if($res_online && $res_online->num_rows > 0) echo "<span class='badge bg-danger rounded-pill ms-1'>{$res_online->num_rows}</span>"; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pills-served-tab" data-bs-toggle="pill" data-bs-target="#pills-served" type="button" role="tab">
                <i class="bi bi-shop me-1"></i> หน้าร้านรอเช็คบิล 
                <?php if($res_served && $res_served->num_rows > 0) echo "<span class='badge bg-warning text-dark rounded-pill ms-1'>{$res_served->num_rows}</span>"; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pills-eating-tab" data-bs-toggle="pill" data-bs-target="#pills-eating" type="button" role="tab">
                <i class="bi bi-fire me-1"></i> โต๊ะกำลังทาน 
                <span class='badge bg-secondary rounded-pill ms-1'><?php echo ($res_eating ? $res_eating->num_rows : 0); ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="pills-tabContent">
        
        <div class="tab-pane fade show active" id="pills-online" role="tabpanel">
            <div class="row g-4">
                <?php if($res_online && $res_online->num_rows > 0): while($row = $res_online->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="soft-card h-100 border-top border-4 border-primary">
                        <div class="card-body p-4 text-center d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-primary"><i class="bi bi-phone"></i> ออนไลน์</span>
                                    <div class="order-id">#<?php echo str_pad($row['order_id'], 5, '0', STR_PAD_LEFT);?></div>
                                </div>
                                <div class="mb-3 mt-3"><span class="badge-soft-warning"><i class="bi bi-hourglass-split"></i> แนบสลิปแล้ว รอตรวจ</span></div>
                                <p class="text-muted small mb-1">ยอดโอน (ดูจากสลิป)</p>
                                <div class="total-price mb-4 text-primary">฿<?php echo number_format($row['total_amount'], 0);?></div>
                            </div>
                            <button class="btn btn-primary w-100 rounded-3 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#payModal<?php echo $row['order_id'];?>">
                                <i class="bi bi-search me-1"></i> ตรวจสลิป & อนุมัติ
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                    <div class="col-12 text-center py-5"><h5 class="text-muted"><i class="bi bi-check-circle"></i> ไม่มีสลิปออนไลน์รอตรวจ</h5></div>
                <?php endif;?>
            </div>
        </div>

        <div class="tab-pane fade" id="pills-served" role="tabpanel">
            <div class="row g-4">
                <?php if($res_served && $res_served->num_rows > 0): while($row = $res_served->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="soft-card h-100 border-top border-4 border-warning">
                        <div class="card-body p-4 text-center d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-warning text-dark fw-bold"><i class="bi bi-shop"></i> โต๊ะ <?php echo $row['table_id']; ?></span>
                                    <div class="order-id">#<?php echo str_pad($row['order_id'], 5, '0', STR_PAD_LEFT);?></div>
                                </div>
                                <div class="mb-3 mt-3"><span class="badge-soft-success"><i class="bi bi-cup-hot"></i> อาหารเสิร์ฟแล้ว</span></div>
                                <p class="text-muted small mb-1">ยอดสุทธิที่ต้องชำระ</p>
                                <div class="total-price mb-4 text-success">฿<?php echo number_format($row['total_amount'], 0);?></div>
                            </div>
                            <button class="btn btn-success w-100 rounded-3 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#payModal<?php echo $row['order_id'];?>">
                                <i class="bi bi-cash-coin me-1"></i> ปิดบิลหน้าร้าน
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                    <div class="col-12 text-center py-5"><h5 class="text-muted"><i class="bi bi-emoji-smile"></i> ยังไม่มีโต๊ะเรียกเก็บเงิน</h5></div>
                <?php endif;?>
            </div>
        </div>

        <div class="tab-pane fade" id="pills-eating" role="tabpanel">
            <div class="row g-4">
                <?php if($res_eating && $res_eating->num_rows > 0): while($row = $res_eating->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="soft-card h-100 border-top border-4 border-secondary opacity-75">
                        <div class="card-body p-4 text-center">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-secondary"><i class="bi bi-shop"></i> โต๊ะ <?php echo $row['table_id']; ?></span>
                                <div class="order-id text-muted">#<?php echo str_pad($row['order_id'], 5, '0', STR_PAD_LEFT);?></div>
                            </div>
                            <div class="mb-3 mt-3">
                                <?php if($row['order_status'] == 'cooking'): ?>
                                    <span class="badge-soft-info"><i class="bi bi-fire"></i> ครัวกำลังทำ</span>
                                <?php else: ?>
                                    <span class="badge-soft-warning"><i class="bi bi-hourglass"></i> เพิ่งสั่งออเดอร์</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-muted small mb-1">ยอดสะสมปัจจุบัน</p>
                            <div class="total-price mb-4 text-muted fs-3">฿<?php echo number_format($row['total_amount'], 0);?></div>
                            <button class="btn btn-outline-secondary w-100 rounded-3 py-2 fw-bold" onclick="alert('ออเดอร์นี้ยังไม่เสิร์ฟ หากลูกค้าต้องการเช็คบิล กรุณากดปิดบิลหน้าร้าน (หรืออัปเดตสถานะอาหารก่อน)')">
                                <i class="bi bi-eye"></i> ดูรายละเอียด
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; else: ?>
                    <div class="col-12 text-center py-5"><h5 class="text-muted"><i class="bi bi-wind"></i> ไม่มีโต๊ะที่กำลังทานอยู่</h5></div>
                <?php endif;?>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/owner-manage-payments.js"></script>

<?php include '../includes/footer_owner.php';?>