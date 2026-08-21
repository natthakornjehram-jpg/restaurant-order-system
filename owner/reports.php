<?php 
// owner/reports.php
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';

include '../includes/header_owner.php';
include '../includes/nav_owner.php'; 

// 2. รับค่าวันที่เลือก (Default คือวันนี้)
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// 3. ดึงข้อมูลสรุปยอด (JOIN ตาราง payment เพื่อหาวิธีจ่ายเงิน cash / transfer)
$summary_sql = "SELECT 
    SUM(CASE WHEN p.method = 'cash' THEN o.total_amount ELSE 0 END) as cash_total,
    SUM(CASE WHEN p.method = 'transfer' THEN o.total_amount ELSE 0 END) as transfer_total,
    SUM(o.total_amount) as grand_total,
    COUNT(o.order_id) as order_count
    FROM orders o
    LEFT JOIN payment p ON o.order_id = p.order_id 
    WHERE o.payment_status = 'paid' AND DATE(o.created_at) = ?";
    
$stmt_sum = $conn->prepare($summary_sql);
$stmt_sum->bind_param("s", $date_filter);
$stmt_sum->execute();
$summary = $stmt_sum->get_result()->fetch_assoc();

// 4. ดึงรายการออเดอร์ของวันนั้นๆ
$reports_sql = "SELECT o.*, p.method as pay_method 
                FROM orders o 
                LEFT JOIN payment p ON o.order_id = p.order_id
                WHERE o.payment_status = 'paid' AND DATE(o.created_at) = ? 
                ORDER BY o.created_at DESC";
                
$stmt_rep = $conn->prepare($reports_sql);
$stmt_rep->bind_param("s", $date_filter);
$stmt_rep->execute();
$reports_res = $stmt_rep->get_result();
?>

<style>
    .main-content { background-color: #fcfcfc; min-height: 100vh; font-family: 'Sarabun', sans-serif; }
    .card-custom { border: none; border-radius: 20px !important; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
    .bg-soft-green { background-color: #f0fdf4; border: 1px solid #dcfce7; }
    .bg-soft-blue { background-color: #f0f9ff; border: 1px solid #e0f2fe; }
    .bg-soft-gray { background-color: #f9fafb; border: 1px solid #f3f4f6; }
    .text-green { color: #16a34a; }
    .text-blue { color: #0284c7; }
    .table thead th { background-color: transparent; border: none; color: #888; font-size: 0.85rem; padding: 15px; }
    .table tbody td { border-bottom: 1px solid #f1f1f1; padding: 15px; vertical-align: middle; }
</style>

<div class="main-content container-fluid pb-5">
    <div class="pt-4 pb-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom">
        <div>
            <h3 class="fw-bold mb-1">รายงานการขาย</h3>
            <p class="text-muted small mb-0">ข้อมูลประจำวันที่ <?php echo date('d/m/Y', strtotime($date_filter)); ?></p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <button onclick="printDailyReport('<?php echo $date_filter; ?>')" class="btn btn-dark rounded-pill px-4 shadow-sm fw-bold">
                <i class="bi bi-printer me-2"></i>พิมพ์สรุปรายงาน
            </button>
            <form method="GET">
                <input type="date" name="date" class="form-control rounded-pill border-0 shadow-sm px-3" 
                        value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4 text-center">
        <div class="col-6">
            <div class="card card-custom bg-soft-green p-3 shadow-sm">
                <div class="small fw-bold text-green mb-1 text-uppercase">เงินสด</div>
                <h3 class="fw-bold mb-0 text-green">฿<?php echo number_format($summary['cash_total'] ?? 0, 0); ?></h3>
            </div>
        </div>
        <div class="col-6">
            <div class="card card-custom bg-soft-blue p-3 shadow-sm">
                <div class="small fw-bold text-blue mb-1 text-uppercase">เงินโอน</div>
                <h3 class="fw-bold mb-0 text-blue">฿<?php echo number_format($summary['transfer_total'] ?? 0, 0); ?></h3>
            </div>
        </div>
        
        <div class="col-12">
            <div class="card card-custom bg-soft-gray p-4 shadow-sm border-0">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-start">
                        <div class="small fw-bold text-muted mb-1">ยอดขายรวมสุทธิทั้งหมด</div>
                        <h1 class="fw-bold mb-0 text-dark">฿<?php echo number_format($summary['grand_total'] ?? 0, 0); ?></h1>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-white text-dark border rounded-pill px-4 py-2 fw-bold shadow-sm" style="font-size: 1rem;">
                            <i class="bi bi-bag-check me-1 text-success"></i> <?php echo $summary['order_count'] ?? 0; ?> ออเดอร์
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-custom bg-white overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">เวลา</th>
                        <th>หมายเลขออเดอร์</th>
                        <th>ประเภท</th>
                        <th>ยอดชำระ</th>
                        <th>วิธีจ่าย</th>
                        <th class="text-end pe-4">พิมพ์</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($reports_res->num_rows > 0): while($row = $reports_res->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4 small text-muted"><?php echo date('H:i', strtotime($row['created_at'])); ?> น.</td>
                        <td><span class="fw-bold text-dark">#ORD-<?php echo str_pad($row['order_id'], 4, '0', STR_PAD_LEFT); ?></span></td>
                        <td>
                            <span class="small text-muted fw-bold">
                                <?php 
                                    // ตัดเดลิเวอรี่ออก เหลือแค่ 2 ประเภทตามที่คุณต้องการ
                                    if($row['order_type'] == 'dine_in') {
                                        echo '<span class="text-primary"><i class="bi bi-shop"></i> ทานที่ร้าน</span>';
                                    } else {
                                        echo '<span class="text-warning text-dark"><i class="bi bi-bag"></i> กลับบ้าน</span>';
                                    }
                                ?>
                            </span>
                        </td>
                        <td class="fw-bold text-dark">฿<?php echo number_format($row['total_amount'], 0); ?></td>
                        <td>
                            <?php if($row['pay_method'] == 'cash'): ?>
                                <span class="badge bg-soft-green text-green px-3 py-2 rounded-pill fw-bold" style="font-size: 0.75rem;">เงินสด</span>
                            <?php else: ?>
                                <span class="badge bg-soft-blue text-blue px-3 py-2 rounded-pill fw-bold" style="font-size: 0.75rem;">เงินโอน</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <button onclick="printSingleReceipt(<?php echo $row['order_id']; ?>)" class="btn btn-sm btn-outline-secondary rounded-circle">
                                <i class="bi bi-printer"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted small">ไม่มีข้อมูลการขายในวันที่เลือก</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function printDailyReport(date) { window.open('print_daily_report.php?date=' + date, '_blank', 'width=800,height=600'); }
function printSingleReceipt(id) { window.open('print_receipt.php?id=' + id, '_blank', 'width=400,height=600'); }
</script>

<?php include '../includes/footer_owner.php'; ?>