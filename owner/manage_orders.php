<?php 
// owner/manage_orders.php
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';

include '../includes/header_owner.php';
include '../includes/nav_owner.php'; 

// ดึงรายการออเดอร์พร้อมหมายเลขโต๊ะ
// ออเดอร์กลับบ้าน (ต้องโอนเงิน+แนบสลิปมาก่อนเสมอ) จะยังไม่โผล่ในคิวครัวจนกว่าเจ้าของร้านจะตรวจสลิปแล้วกดยืนยัน
// ที่หน้าจัดการชำระเงิน (payment_status เปลี่ยนเป็น paid) กันครัวเริ่มทำอาหารทั้งที่ยังไม่รู้ว่าลูกค้าจ่ายจริงหรือส่งสลิปปลอมมาเล่นๆ
// ส่วนออเดอร์ทานที่ร้านไม่ต้องรอ เพราะจ่ายตอนปิดบิลทีหลังอยู่แล้ว ไม่ใช่จ่ายก่อนเหมือนกลับบ้าน
$sql = "SELECT o.*, t.table_number, t.status AS table_status FROM orders o
        LEFT JOIN restauranttable t ON o.table_id = t.table_id
        WHERE o.order_status IN ('pending', 'cooking') AND (o.order_type = 'dine_in' OR o.payment_status = 'paid')
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
                กำลังรอ <span id="queueCountBadge"><?php echo $queue_count; ?></span> ใบสั่ง
             </span>
        </div>
    </div>

    <div class="row g-4" id="ordersListContainer">
        <?php if($res && $res->num_rows > 0): while($order = $res->fetch_assoc()): 
            $oid = $order['order_id']; 
            $status = $order['order_status']; 
            $table_status = $order['table_status'] ?? 'available';
            $is_open_table = ($order['order_type'] === 'dine_in' && $table_status === 'available' && $status === 'pending');
            $card_class = $is_open_table ? 'border border-3 border-danger shadow-lg' : (($status == 'pending') ? 'bg-soft-pending' : 'bg-soft-cooking');
            $table_label = (!empty($order['table_number'])) ? 'โต๊ะ ' . htmlspecialchars($order['table_number']) : 'กลับบ้าน';
        ?>
        <div class="col-md-6 col-lg-4 col-xl-3" id="order-col-<?php echo $oid;?>">
            <div class="soft-card h-100 d-flex flex-column <?php echo $card_class; ?>" id="order-card-<?php echo $oid;?>">

                <div class="card-header-soft d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge <?php echo $is_open_table ? 'bg-danger' : 'bg-dark'; ?> rounded-pill px-3 mb-1" style="font-size: 0.85rem;">
                            <i class="bi bi-shop me-1"></i><?php echo $table_label; ?>
                        </span>
                        <div class="order-id text-primary fw-bold fs-5">#<?php echo str_pad($order['daily_order_no'] ?: $oid, 3, '0', STR_PAD_LEFT);?></div>
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
                        <div class="alert alert-danger p-2 mb-3 rounded-3 text-center fw-bold small shadow-sm" id="order-openbanner-<?php echo $oid;?>" style="background-color: #fff5f5; border-color: #feb2b2; color: #c53030;">
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
                        // เก็บบัฟเฟอร์ไว้เป็นอาร์เรย์ เพราะต้องวนซ้ำสองรอบ (แสดงผลการ์ด + สร้างหน้าต่างแก้ไขรายการด้านล่าง)
                        // ผลลัพธ์จาก mysqli วนได้แค่รอบเดียว ถ้าไม่บัฟเฟอร์ไว้ก่อนจะวนซ้ำไม่ได้
                        $order_items_buffer = $items_res ? $items_res->fetch_all(MYSQLI_ASSOC) : [];
                        foreach($order_items_buffer as $i):?>
                                <div class="mb-4" id="order-item-row-<?php echo $i['order_detail_id'];?>" data-detail-id="<?php echo $i['order_detail_id'];?>">
                                    <div class="d-flex align-items-center">
                                        <span class="order-item-qty" id="order-item-qty-<?php echo $i['order_detail_id'];?>"><?php echo $i['quantity'];?></span>
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
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="p-3">
                    <div id="order-actionbtn-<?php echo $oid;?>">
                    <?php if($is_open_table):?>
                        <button onclick="changeStatus('<?php echo $oid;?>', 'cooking')" class="btn w-100 btn-success py-3 fw-bold rounded-pill shadow fs-6 mb-2">
                            <i class="bi bi-check-circle-fill me-1"></i> อนุมัติเปิดโต๊ะ & รับออเดอร์
                        </button>
                    <?php elseif($status == 'pending'):?>
                        <button onclick="changeStatus('<?php echo $oid;?>', 'cooking')" class="btn w-100 btn-start btn-action shadow-sm mb-2">
                            <i class="bi bi-play-fill me-1"></i> รับออเดอร์
                        </button>
                    <?php elseif($status == 'cooking'):?>
                        <button onclick="changeStatus('<?php echo $oid;?>', 'served')" class="btn w-100 btn-done btn-action shadow-sm">
                            <i class="bi bi-check2-all me-1"></i> ปรุงเสร็จแล้ว
                        </button>
                    <?php endif;?>
                    </div>

                    <?php if($status == 'pending'):?>
                        <div class="d-flex gap-2" id="order-editcancel-<?php echo $oid;?>">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill flex-fill" data-bs-toggle="modal" data-bs-target="#editOrderModal<?php echo $oid;?>">
                                <i class="bi bi-pencil-square me-1"></i> แก้ไขรายการ
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill flex-fill" onclick="cancelOrder(<?php echo $oid;?>)">
                                <i class="bi bi-x-circle me-1"></i> ยกเลิกออเดอร์
                            </button>
                        </div>
                    <?php endif;?>
                </div>

            </div>
        </div>

        <?php if($status == 'pending'):?>
        <div class="modal fade text-dark" id="editOrderModal<?php echo $oid;?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1"></i> แก้ไขรายการออเดอร์ #<?php echo str_pad($order['daily_order_no'] ?: $oid, 3, '0', STR_PAD_LEFT);?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-3">ปรับจำนวนหรือลบรายการที่ลูกค้าไม่ต้องการแล้วได้ ระบบจะคืนวัตถุดิบในคลังให้อัตโนมัติ (แก้ไขได้เฉพาะก่อนกด "รับออเดอร์" เท่านั้น)</p>
                        <div class="edit-order-lines" data-order-id="<?php echo $oid;?>">
                            <?php foreach($order_items_buffer as $i): ?>
                            <div class="d-flex align-items-center justify-content-between border-bottom py-2 edit-order-line" data-detail-id="<?php echo $i['order_detail_id'];?>">
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?php echo htmlspecialchars($i['menu_name']);?></div>
                                    <?php if(!empty($i['topping_details'])):?>
                                        <div class="small text-muted">+ <?php echo htmlspecialchars($i['topping_details']);?></div>
                                    <?php endif;?>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle" onclick="editLineQty(this, -1)">-</button>
                                    <span class="fw-bold edit-line-qty" style="min-width: 24px; text-align:center;"><?php echo $i['quantity'];?></span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle" onclick="editLineQty(this, 1)">+</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" title="ลบรายการนี้" onclick="removeEditLine(this)"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">ปิด</button>
                        <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" onclick="saveOrderEdit(<?php echo $oid;?>)">บันทึกการแก้ไข</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
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

<script src="<?= BASE_URL ?>assets/js/owner-manage-orders.js"></script>

<?php include '../includes/footer_owner.php';?>
