<?php 
session_start();
include '../includes/db.php';

// 🔴 แก้ไขแล้ว: เช็กว่ามีการล็อกอินและใช้ Session ชื่อ 'owner_id' ให้ตรงกับหน้า login.php
if (!isset($_SESSION['owner_id'])) {
    header("Location: ../login.php");
    exit;
}

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
    $store = ['is_shop_open' => 0, 'is_online_open' => 0];
}

// --- 2. ดึงข้อมูลสถิติ ---
// ยอดขายวันนี้
$sales_sql = "SELECT SUM(total_amount) as daily_total FROM orders WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE()";
$sales_res = $conn->query($sales_sql);
$daily_total = ($sales_res && $row = $sales_res->fetch_assoc()) ? $row['daily_total'] : 0;

// ออเดอร์ที่รอทำ
$order_res = $conn->query("SELECT COUNT(*) as pending_orders FROM orders WHERE order_status = 'pending'");
$pending_orders = ($order_res && $row = $order_res->fetch_assoc()) ? $row['pending_orders'] : 0;

// ออเดอร์ที่ค้างชำระ
$unpaid_res = $conn->query("SELECT COUNT(*) as unpaid FROM orders WHERE order_status IN ('served', 'ready') AND payment_status = 'unpaid'");
$unpaid_orders = ($unpaid_res && $row = $unpaid_res->fetch_assoc()) ? $row['unpaid'] : 0;

// --- 3. ดึงรายการออเดอร์ล่าสุด 8 รายการ ---
$pending_list_sql = "SELECT * FROM orders ORDER BY created_at DESC LIMIT 8";
$pending_list_res = $conn->query($pending_list_sql);
?>

<style>
    /* 🎨 CSS เฉพาะหน้า Dashboard */
    body { background-color: #f0f4f7; }
    
    .dashboard-scope { 
        background-color: #f0f4f7; 
        min-height: 100vh; 
        font-family: 'Sarabun', sans-serif; 
        padding-top: 85px; 
        padding-bottom: 50px;
    }
    
    @media (min-width: 768px) {
        .dashboard-scope {
            padding-top: 100px; 
        }
    }
    
    /* 🌟 กรอบสถิติข้างบน */
    .dashboard-scope .stat-link { text-decoration: none !important; color: inherit; display: block; }
    .dashboard-scope .stat-card-top { 
        background: #fff; border-radius: 24px; padding: 25px; 
        border: 3px solid #dee2e6; transition: 0.3s;
    }
    .dashboard-scope .stat-card-top:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
    
    /* สีขอบกรอบสถิติ */
    .dashboard-scope .border-sales   { border-color: #2ecc71 !important; background-color: #f9fffb; } 
    .dashboard-scope .border-pending { border-color: #f1c40f !important; background-color: #fffef5; } 
    .dashboard-scope .border-unpaid  { border-color: #3498db !important; background-color: #f5faff; } 

    .dashboard-scope .stat-label { font-size: 1.05rem; font-weight: 700; color: #6c757d; display: block; margin-bottom: 5px; }
    .dashboard-scope .stat-value { font-size: 2.6rem; font-weight: 900; line-height: 1; }

    /* ปุ่มเปิด/ปิด (Toggle) */
    .dashboard-scope .btn-toggle { 
        border-radius: 15px; font-weight: 700; padding: 10px 20px; border: none; 
        transition: 0.3s; min-width: 150px; color: #fff !important; 
    }
    .dashboard-scope .bg-shop-open { background-color: #27ae60; }
    .dashboard-scope .bg-online-open { background-color: #2980b9; }
    .dashboard-scope .bg-status-closed { background-color: #c0392b; }

    /* 🌟 กรอบออเดอร์ข้างล่าง */
    .dashboard-scope .order-item-box {
        border: 3px solid #e9ecef; border-radius: 25px; background: #fff;
        overflow: hidden; transition: 0.3s; position: relative;
    }
    .dashboard-scope .order-tag {
        font-size: 0.75rem; font-weight: 800; padding: 5px 15px;
        border-radius: 0 0 12px 12px; color: #fff; display: inline-block;
    }
    .dashboard-scope .tag-onsite { background: #495057; } 
    .dashboard-scope .tag-online { background: #d63384; } 

    /* สีขอบการ์ดออเดอร์ตามสถานะ */
    .dashboard-scope .st-border-pending { border-color: #f1c40f; } 
    .dashboard-scope .st-border-cooking { border-color: #3498db; }
    .dashboard-scope .st-border-ready   { border-color: #fd7e14; }
    .dashboard-scope .st-border-paid    { border-color: #2ecc71; }

    .dashboard-scope .table-title { font-size: 1.7rem; font-weight: 800; color: #343a40; }
    .dashboard-scope .status-badge { font-size: 0.85rem; font-weight: 700; padding: 6px 16px; border-radius: 50px; }
</style>

<div class="dashboard-scope container-fluid px-4 text-dark">
    <div class="pb-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom border-secondary border-opacity-10">
        <div>
            <h2 class="fw-bold m-0 text-dark">หน้าจัดการร้านอาหาร</h2>
            <p class="text-muted small mb-0">ยินดีต้อนรับกลับมาครับ อัปเดตล่าสุด <?php echo date('H:i'); ?> น.</p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <button data-id="shop" data-type="shop_status" 
                    onclick="toggleStatus('shop', 'shop_status', <?= $store['is_shop_open']; ?>)" 
                    class="btn btn-toggle shadow-sm <?= ($store['is_shop_open'] == 1) ? 'bg-shop-open' : 'bg-status-closed'; ?>">
                <?= ($store['is_shop_open'] == 1) ? '<i class="bi bi-shop me-1"></i> ร้านเปิดอยู่' : '<i class="bi bi-shop me-1"></i> ร้านปิดอยู่' ?>
            </button>
            
            <button data-id="online" data-type="shop_status" 
                    onclick="toggleStatus('online', 'shop_status', <?= $store['is_online_open']; ?>)" 
                    class="btn btn-toggle shadow-sm <?= ($store['is_online_open'] == 1) ? 'bg-online-open' : 'bg-status-closed'; ?>">
                <?= ($store['is_online_open'] == 1) ? '<i class="bi bi-globe me-1"></i> รับออนไลน์' : '<i class="bi bi-globe me-1"></i> ปิดออนไลน์' ?>
            </button>
        </div>
    </div>

    <div class="row g-4 mb-5 text-center">
        <div class="col-md-4">
            <a href="reports.php" class="stat-link">
                <div class="stat-card-top border-sales shadow-sm">
                    <span class="stat-label">ยอดขายวันนี้</span>
                    <span class="stat-value text-success">฿<?php echo number_format($daily_total, 0); ?></span>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4">
            <a href="manage_orders.php" class="stat-link">
                <div class="stat-card-top border-pending shadow-sm">
                    <span class="stat-label">คิวที่ต้องทำ</span>
                    <span class="stat-value text-warning"><?php echo $pending_orders; ?> <small class="fs-4">คิว</small></span>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4">
            <a href="manage_payments.php" class="stat-link">
                <div class="stat-card-top border-unpaid shadow-sm">
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
                            ออเดอร์ #<?php echo str_pad($row['order_id'], 4, '0', STR_PAD_LEFT); ?>
                        </div>
                        <div class="text-muted small mb-1"><?php echo $type_display; ?></div>
                        <div class="text-muted small mb-3"><?php echo date('H:i', strtotime($row['created_at'])); ?> น.</div>
                        
                        <div class="status-badge <?php echo $st_badge; ?> d-inline-block mb-3">
                            <?php echo $st_text; ?>
                        </div>

                        <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                            <span class="text-muted small fw-bold">ยอดสุทธิ</span>
                            <span class="fs-4 fw-bold text-dark">฿<?php echo number_format($row['total_amount'], 0); ?></span>
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
