<?php 
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';

include '../includes/header_owner.php'; 
include '../includes/nav_owner.php'; 

// --- 1. ดึงข้อมูลสถานะร้าน ---
$stmt = $conn->prepare("SELECT * FROM owner WHERE owner_id = ? LIMIT 1");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$store_res = $stmt->get_result();

if ($store_res && $store_res->num_rows > 0) {
    $store = $store_res->fetch_assoc();
} else {
    // กรณีเพิ่งสมัคร
    $store = ['is_shop_open' => 0];
}

// --- 2. ดึงข้อมูลสถิติ ---
// ยอดขายวันนี้
$sales_sql = "SELECT SUM(total_amount) as daily_total FROM orders WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE()";
$sales_res = $conn->query($sales_sql);
$daily_total = ($sales_res && $row = $sales_res->fetch_assoc()) ? floatval($row['daily_total'] ?? 0) : 0;

// ออเดอร์ที่รอทำ
$order_res = $conn->query("SELECT COUNT(*) as pending_orders FROM orders WHERE order_status = 'pending'");
$pending_orders = ($order_res && $row = $order_res->fetch_assoc()) ? $row['pending_orders'] : 0;

// ออเดอร์ที่ค้างชำระ
$unpaid_res = $conn->query("SELECT COUNT(*) as unpaid FROM orders WHERE order_status IN ('served', 'ready') AND payment_status = 'unpaid'");
$unpaid_orders = ($unpaid_res && $row = $unpaid_res->fetch_assoc()) ? $row['unpaid'] : 0;

// --- 3. ดึงรายการออเดอร์ล่าสุด 8 รายการ ---
$pending_list_sql = "SELECT * FROM orders WHERE DATE(created_at) = CURDATE() ORDER BY created_at DESC LIMIT 8";
$pending_list_res = $conn->query($pending_list_sql);

// --- 4. เช็คว่าคิวเต็มหรือยัง (เหมือนกับที่เช็คฝั่งลูกค้าใน qr_table/menu_dinein.php) ---
// ใช้ตัดสินว่าปุ่ม "รับทานที่ร้าน"/"รับสั่งกลับบ้าน" ควรโชว์เป็นสถานะ "คิวเต็ม" (สีเหลือง แยกจาก "งดรับ" สีเทา)
// หรือไม่ - ให้เจ้าของร้านแยกออกว่าปุ่มปิดเพราะกดปิดเองหรือเพราะคิวเต็มอัตโนมัติ (ค่าจริงในฐานข้อมูลไม่ถูกแก้
// อัตโนมัติ แค่ปรับหน้าตาปุ่มให้ตรงกับสถานะจริงที่ลูกค้าเจอ)
$max_queue = intval($store['max_queue'] ?? 0);
$active_queue_count = 0;
if ($max_queue > 0) {
    $q_res = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE order_status IN ('pending', 'cooking')");
    $active_queue_count = ($q_res && $q_row = $q_res->fetch_assoc()) ? intval($q_row['c']) : 0;
}
$queue_is_full = ($max_queue > 0 && $active_queue_count >= $max_queue);

// สถานะจริงของปุ่ม "ทานที่ร้าน"/"กลับบ้าน" ให้ดูตามลำดับ: ร้านปิดทั้งร้าน > กดปิดเอง > คิวเต็ม > เปิดรับปกติ
// (ร้านปิดทั้งร้านหรือกดปิดเอง ให้ผลเป็น "งดรับ" เหมือนกัน เพราะเป็นการตัดสินใจของเจ้าของร้านทั้งคู่)
function toggle_visual_state($manual_open, $shop_open, $queue_is_full) {
    if (!$shop_open || !$manual_open) return 'closed';
    if ($queue_is_full) return 'queue_full';
    return 'open';
}
$dinein_state = toggle_visual_state(!empty($store['is_dinein_open']), $store['is_shop_open'] == 1, $queue_is_full);
$takeaway_state = toggle_visual_state(!empty($store['is_takeaway_open']), $store['is_shop_open'] == 1, $queue_is_full);
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/owner-dashboard.css?v=<?= time() ?>">

<div class="dashboard-scope container-fluid px-4 text-dark">
    <div class="pb-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom border-secondary border-opacity-10">
        <div>
            <h4 class="fw-bold m-0 text-dark">หน้าจัดการร้านอาหาร</h4>
            <p class="text-muted small mb-0">ยินดีต้อนรับกลับมาครับ อัปเดตล่าสุด <?php echo date('H:i'); ?> น.</p>
        </div>
        <div class="d-flex flex-column gap-2 mt-3 mt-md-0">
            <button data-id="shop" data-type="shop_status"
                    onclick="toggleStatus('shop', 'shop_status', <?= $store['is_shop_open']; ?>)"
                    class="btn btn-toggle shadow-sm bg-toggle-shop <?= ($store['is_shop_open'] == 1) ? '' : 'is-closed'; ?>">
                <?= ($store['is_shop_open'] == 1) ? '<i class="bi bi-shop me-1"></i> ร้านเปิดอยู่ (รับออเดอร์)' : '<i class="bi bi-shop me-1"></i> ร้านปิดอยู่' ?>
            </button>
            <div class="d-flex gap-2 toggle-row-split">
                <button data-id="dinein" data-type="dinein_status"
                        onclick="toggleStatus('dinein', 'dinein_status', <?= $store['is_dinein_open']; ?>)"
                        class="btn btn-toggle shadow-sm bg-toggle-dinein <?= $dinein_state !== 'open' ? 'is-' . str_replace('_', '-', $dinein_state) : ''; ?>">
                    <?php if ($dinein_state === 'queue_full'): ?>
                        <i class="bi bi-hourglass-split me-1"></i> คิวเต็ม (ทานที่ร้าน)
                    <?php elseif ($dinein_state === 'closed'): ?>
                        <i class="bi bi-cup-hot me-1"></i> งดรับทานที่ร้าน
                    <?php else: ?>
                        <i class="bi bi-cup-hot me-1"></i> รับทานที่ร้าน
                    <?php endif; ?>
                </button>
                <button data-id="takeaway" data-type="takeaway_status"
                        onclick="toggleStatus('takeaway', 'takeaway_status', <?= $store['is_takeaway_open']; ?>)"
                        class="btn btn-toggle shadow-sm bg-toggle-takeaway <?= $takeaway_state !== 'open' ? 'is-' . str_replace('_', '-', $takeaway_state) : ''; ?>">
                    <?php if ($takeaway_state === 'queue_full'): ?>
                        <i class="bi bi-hourglass-split me-1"></i> คิวเต็ม (กลับบ้าน)
                    <?php elseif ($takeaway_state === 'closed'): ?>
                        <i class="bi bi-bag-check me-1"></i> งดรับสั่งกลับบ้าน
                    <?php else: ?>
                        <i class="bi bi-bag-check me-1"></i> รับสั่งกลับบ้าน
                    <?php endif; ?>
                </button>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5 text-center">
        <div class="col-md-4">
            <a href="reports.php" class="stat-link">
                <div class="stat-card-top border-sales shadow-sm">
                    <span class="stat-icon"><i class="bi bi-graph-up-arrow"></i></span>
                    <span class="stat-label">ยอดขายวันนี้</span>
                    <span class="stat-value text-success">฿<?php echo number_format($daily_total, 0); ?></span>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4">
            <a href="manage_orders.php" class="stat-link">
                <div class="stat-card-top border-pending shadow-sm">
                    <span class="stat-icon"><i class="bi bi-receipt-cutoff"></i></span>
                    <span class="stat-label">คิวที่ต้องทำ</span>
                    <span class="stat-value text-warning"><?php echo $pending_orders; ?> <small class="fs-4">คิว</small></span>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4">
            <a href="manage_payments.php" class="stat-link">
                <div class="stat-card-top border-unpaid shadow-sm">
                    <span class="stat-icon"><i class="bi bi-wallet2"></i></span>
                    <span class="stat-label">รอชำระเงิน</span>
                    <span class="stat-value text-primary"><?php echo $unpaid_orders; ?> <small class="fs-4">คิว</small></span>
                </div>
            </a>
        </div>
    </div>

    <h3 class="fw-bold mb-4 text-dark">สถานะออเดอร์ล่าสุด</h3>
    
    <div class="row g-3">
        <?php if ($pending_list_res && $pending_list_res->num_rows > 0): ?>
            <?php while($row = $pending_list_res->fetch_assoc()): 
                $status = $row['order_status'];
                $pay_status = $row['payment_status'];
                
                $order_type = $row['order_type'];
                // 🔴 แก้ไข: ร้านไม่มีเดลิเวอรี่ มีแค่สั่งกลับบ้าน ถือว่าเป็นออนไลน์
                $is_online = ($order_type == 'takeaway'); 
                
                $st_border = "st-border-pending"; $st_text = "รอรับออเดอร์"; $st_badge = "bg-warning text-dark";

                if($pay_status == 'paid') {
                    $st_border = "st-border-paid"; $st_text = "เสร็จสิ้น"; $st_badge = "bg-success text-white";
                } elseif($status == 'canceled') {
                    $st_border = "st-border-pending"; $st_text = "ถูกปฏิเสธ/ยกเลิก"; $st_badge = "bg-secondary text-white";
                } elseif($status == 'cooking') {
                    $st_border = "st-border-cooking"; $st_text = "กำลังทำ"; $st_badge = "bg-primary text-white";
                } elseif($status == 'served' || $status == 'ready') {
                    $st_border = "st-border-ready"; $st_text = "รอจ่ายเงิน"; $st_badge = "bg-danger text-white";
                }

                $type_display = '';
                if ($order_type == 'dine_in') $type_display = '<i class="bi bi-cup-hot"></i> ทานที่ร้าน';
                elseif ($order_type == 'takeaway') $type_display = '<i class="bi bi-bag"></i> สั่งกลับบ้าน';
            ?>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="order-item-box shadow-sm <?php echo $st_border; ?>">
                    <div class="text-center">
                        <span class="order-tag <?php echo $is_online ? 'tag-online' : 'tag-onsite'; ?>">
                            <?php echo $is_online ? 'TAKEAWAY (กลับบ้าน)' : 'DINE-IN (หน้าร้าน)'; ?>
                        </span>
                    </div>

                    <div class="p-4 text-center">
                        <div class="table-title mb-1">
                            ออเดอร์ #<?php echo str_pad($row['daily_order_no'] ?: $row['order_id'], 3, '0', STR_PAD_LEFT); ?>
                        </div>
                        <div class="text-muted small mb-1"><?php echo $type_display; ?></div>
                        <div class="text-muted small mb-3"><?php echo date('H:i', strtotime($row['created_at'])); ?> น.</div>

                        <div class="status-badge <?php echo $st_badge; ?> d-inline-block mb-3">
                            <?php echo $st_text; ?>
                        </div>

                        <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                            <span class="text-muted small fw-bold">ยอดสุทธิ</span>
                            <span class="fs-4 fw-bold text-dark">฿<?php echo number_format(floatval($row['total_amount'] ?? 0), 0); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5"><h4 class="text-muted opacity-50">ยังไม่มีรายการออเดอร์...</h4></div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer_owner.php'; ?>
