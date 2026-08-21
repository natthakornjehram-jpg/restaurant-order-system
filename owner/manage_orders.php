<?php 
// owner/manage_orders.php
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';

if (!isset($_SESSION['owner_id'])) {
    die("<script>alert('กรุณาล็อกอินก่อน'); window.location='../login.php';</script>");
}

include '../includes/header_owner.php'; 
include '../includes/nav_owner.php'; 

// ไม่เอาออเดอร์ที่จ่ายผ่านโอนแล้วร้านยังไม่ได้ตรวจ/อนุมัติสลิป เข้าคิวครัว
// (กันเคสลูกค้าแนบสลิปปลอมหรือยังไม่โอนจริง ต้องให้ร้านเช็คก่อนเริ่มทำ)
$sql = "SELECT o.* FROM orders o
        WHERE o.order_status IN ('pending', 'cooking')
        AND NOT EXISTS (
            SELECT 1 FROM payment p
            WHERE p.order_id = o.order_id
            AND p.slip_image IS NOT NULL
            AND p.status != 'completed'
        )
        ORDER BY o.created_at ASC";
$res = $conn->query($sql);
$queue_count = $res ? $res->num_rows : 0;
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/owner-manage-orders.css">

<div class="main-content container-fluid pb-5 px-4 pt-4">
    <div class="d-flex justify-content-between align-items-center mb-4" style="margin-top: 60px;">
        <div>
            <h2 class="fw-bold mb-0" style="color: #1a202c;">
                <i class="bi bi-receipt text-primary me-2 shadow-sm"></i>รายการรอทำอาหาร
            </h2>
            <p class="text-muted small mb-0">ระบบจัดการคิวห้องครัวอัจฉริยะ</p>
        </div>
        <div class="text-end">
             <span class="badge bg-white text-primary border border-primary rounded-pill px-4 py-2 fs-6 shadow-sm">
                กำลังรอ <?php echo $queue_count; ?> ใบสั่ง
             </span>
        </div>
    </div>

    <div class="row g-4">
        <?php if($res && $res->num_rows > 0): while($order = $res->fetch_assoc()): 
            $oid = $order['order_id']; 
            $status = $order['order_status']; 
            $card_class = ($status == 'pending') ? 'bg-soft-pending' : 'bg-soft-cooking';
        ?>
        <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="soft-card h-100 d-flex flex-column <?php echo $card_class; ?>">
                
                <div class="card-header-soft d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted x-small fw-bold text-uppercase">ออเดอร์</div>
                        <div class="order-id">#<?php echo str_pad($oid, 4, '0', STR_PAD_LEFT);?></div>
                    </div>
                    <div class="text-end">
                        <span class="time-badge"><i class="bi bi-clock-history me-1"></i> <?php echo date('H:i', strtotime($order['created_at']));?></span>
                    </div>
                </div>

                <div class="card-body p-3 flex-grow-1">
                    <div class="order-items-list">
                        <?php 
                        $item_sql = "
                            SELECT od.order_detail_id, od.quantity, od.note, i.name AS menu_name,
                                (SELECT GROUP_CONCAT(t.topping_name SEPARATOR ', ') 
                                 FROM orderdetail_topping odt 
                                 JOIN topping t ON odt.topping_id = t.topping_id 
                                 WHERE odt.order_detail_id = od.order_detail_id) AS topping_details
                            FROM orderdetail od
                            JOIN item i ON od.item_id = i.item_id
                            WHERE od.order_id = $oid
                        ";
                        $items_res = $conn->query($item_sql);
                        if($items_res) {
                            while($i = $items_res->fetch_assoc()):?>
                                <div class="mb-4">
                                    <div class="d-flex align-items-center">
                                        <span class="order-item-qty"><?php echo $i['quantity'];?></span>
                                        <span class="order-item-name"><?php echo htmlspecialchars($i['menu_name']);?></span>
                                    </div>
                                    
                                    <?php if(!empty($i['topping_details'])):?>
                                        <div class="topping-text">+ <?php echo htmlspecialchars($i['topping_details']);?></div>
                                    <?php endif;?>

                                    <?php if(!empty($i['note'])):?>
                                        <div class="note-box mt-2">
                                            <i class="bi bi-megaphone-fill me-1"></i> <?php echo htmlspecialchars($i['note']);?>
                                        </div>
                                    <?php endif;?>
                                </div>
                            <?php endwhile; 
                        }?>
                    </div>
                </div>

                <div class="p-3">
                    <?php if($status == 'pending'):?>
                        <button onclick="changeStatus('<?php echo $oid;?>', 'cooking')" class="btn w-100 btn-start btn-action shadow-sm">
                            <i class="bi bi-play-fill me-1"></i> รับออเดอร์เข้าครัว
                        </button>
                    <?php elseif($status == 'cooking'):?>
                        <button onclick="changeStatus('<?php echo $oid;?>', 'served')" class="btn w-100 btn-done btn-action shadow-sm">
                            <i class="bi bi-check2-all me-1"></i> ปรุงเสร็จแล้ว
                        </button>
                    <?php endif;?>
                </div>

            </div>
        </div>
        <?php endwhile; else:?>
        
        <div class="col-12 text-center" style="margin-top: 15vh;">
            <div style="font-size: 6rem; color: #cbd5e0;"><i class="bi bi-receipt-cutoff"></i></div>
            <h3 class="mt-4 fw-bold" style="color: #4a5568;">ยังไม่มีใบสั่งอาหารในขณะนี้</h3>
            <p class="text-muted">ออเดอร์ใหม่จะปรากฏขึ้นที่นี่โดยอัตโนมัติ</p>
            <a href="dashboard.php" class="btn btn-outline-primary rounded-pill px-4 mt-3">
                <i class="bi bi-arrow-left me-2"></i>กลับหน้าหลัก
            </a>
        </div>

        <?php endif;?>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/owner-manage-orders.js"></script>

<?php include '../includes/footer_owner.php';?>
