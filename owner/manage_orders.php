<?php 
// owner/manage_orders.php
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';

include '../includes/header_owner.php';
include '../includes/nav_owner.php'; 

// ดึงรายการออเดอร์พร้อมหมายเลขโต๊ะ
$sql = "SELECT o.*, t.table_number, t.status AS table_status FROM orders o
        LEFT JOIN restauranttable t ON o.table_id = t.table_id
        WHERE o.order_status IN ('pending', 'cooking')
        ORDER BY o.created_at ASC";
$res = $conn->query($sql);
$queue_count = $res ? $res->num_rows : 0;
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/owner-manage-orders.css?v=<?= time() ?>">

<div class="main-content container-fluid pb-5 px-4 pt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-white rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; border: 1px solid #edf2f7; background: #ffffff; color: #4a5568;" title="ย้อนกลับ">
                <i class="bi bi-arrow-left fs-4"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-0" style="color: #1a202c; font-size: 1.50rem;">
                    ออเดอร์ใหม่
                </h4>
            </div>
        </div>
        <div class="text-end">
             <span class="badge bg-white text-primary border border-primary rounded-pill px-3 py-2 fs-6 shadow-sm">
                กำลังรอ <?php echo $queue_count; ?> ใบสั่ง
             </span>
        </div>
    </div>

    <div class="row g-4">
        <?php if($res && $res->num_rows > 0): while($order = $res->fetch_assoc()): 
            $oid = $order['order_id']; 
            $status = $order['order_status']; 
            $table_status = $order['table_status'] ?? 'available';
            $is_open_table = ($order['order_type'] === 'dine_in' && $table_status === 'available' && $status === 'pending');
            $card_class = $is_open_table ? 'border border-3 border-danger shadow-lg' : (($status == 'pending') ? 'bg-soft-pending' : 'bg-soft-cooking');
            $table_label = (!empty($order['table_number'])) ? 'โต๊ะ ' . htmlspecialchars($order['table_number']) : 'กลับบ้าน';
        ?>
        <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="soft-card h-100 d-flex flex-column <?php echo $card_class; ?>">
                
                <div class="card-header-soft d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge <?php echo $is_open_table ? 'bg-danger' : 'bg-dark'; ?> rounded-pill px-3 mb-1" style="font-size: 0.85rem;">
                            <i class="bi bi-shop me-1"></i><?php echo $table_label; ?>
                        </span>
                        <div class="order-id text-primary fw-bold fs-5">
                            #<?php echo str_pad($order['daily_order_no'] ?: $oid, 3, '0', STR_PAD_LEFT);?>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline" title="แก้ไขเลขคิว" onclick="editQueueNo('<?php echo $oid;?>', <?php echo intval($order['daily_order_no'] ?: $oid);?>)">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="time-badge"><i class="bi bi-clock-history me-1"></i> <?php echo date('H:i', strtotime($order['created_at']));?></span>
                    </div>
                </div>

                <?php if ($order['order_type'] !== 'dine_in' && (!empty($order['online_customer_name']) || !empty($order['online_customer_phone']))): ?>
                <div class="px-3 pt-2 small text-muted">
                    <i class="bi bi-person-fill me-1"></i><?php echo htmlspecialchars($order['online_customer_name'] ?: '-'); ?>
                    <?php if (!empty($order['online_customer_phone'])): ?>
                        <span class="mx-1">|</span><i class="bi bi-telephone-fill me-1"></i><a href="tel:<?php echo htmlspecialchars($order['online_customer_phone']); ?>" class="text-muted text-decoration-none"><?php echo htmlspecialchars($order['online_customer_phone']); ?></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="card-body p-3 flex-grow-1">
                    <?php if($is_open_table): ?>
                        <div class="alert alert-danger p-2 mb-3 rounded-3 text-center fw-bold small shadow-sm" style="background-color: #fff5f5; border-color: #feb2b2; color: #c53030;">
                            <i class="bi bi-bell-fill me-1 text-danger"></i> 🔔 ออเดอร์แรก (ขอเปิดโต๊ะใหม่)
                        </div>
                    <?php endif; ?>

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
                            WHERE od.order_id = ?
                        ";
                        $item_stmt = $conn->prepare($item_sql);
                        $item_stmt->bind_param("i", $oid);
                        $item_stmt->execute();
                        $items_res = $item_stmt->get_result();
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
                    <?php if($is_open_table):?>
                        <button onclick="changeStatus('<?php echo $oid;?>', 'cooking')" class="btn w-100 btn-success py-3 fw-bold rounded-pill shadow fs-6">
                            <i class="bi bi-check-circle-fill me-1"></i> อนุมัติเปิดโต๊ะ & รับออเดอร์
                        </button>
                    <?php elseif($status == 'pending'):?>
                        <button onclick="changeStatus('<?php echo $oid;?>', 'cooking')" class="btn w-100 btn-start btn-action shadow-sm">
                            <i class="bi bi-play-fill me-1"></i> รับออเดอร์
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
            <h3 class="mt-4 fw-bold" style="color: #4a5568;">ยังไม่มีออเดอร์ในขณะนี้</h3>
            <a href="dashboard.php" class="btn btn-outline-primary rounded-pill px-4 mt-3">
                <i class="bi bi-arrow-left me-2"></i>กลับหน้าหลัก
            </a>
        </div>

        <?php endif;?>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/owner-manage-orders.js?v=<?= time() ?>"></script>

<?php include '../includes/footer_owner.php';?>
