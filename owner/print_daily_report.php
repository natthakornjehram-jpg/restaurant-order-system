<?php
// owner/print_daily_report.php
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// 2. ดึงข้อมูลร้านค้าจากตาราง owner (เพื่อเอาชื่อร้านมาแสดง)
$stmt_rest = $conn->prepare("SELECT restaurant_name FROM owner WHERE owner_id = ? LIMIT 1");
$stmt_rest->bind_param("i", $owner_id);
$stmt_rest->execute();
$rest_data = $stmt_rest->get_result()->fetch_assoc();

$restaurant_name = !empty($rest_data['restaurant_name']) ? $rest_data['restaurant_name'] : 'ไม่ระบุชื่อร้าน';

// 3. ดึงข้อมูลสรุปยอดขาย โดย JOIN ระหว่าง orders กับ payment
// ใช้ total_amount แทน total_price และดึงวิธีจ่าย (method) จากตาราง payment
$summary_sql = "SELECT 
    SUM(CASE WHEN p.method = 'cash' THEN o.total_amount ELSE 0 END) as cash,
    SUM(CASE WHEN p.method = 'transfer' THEN o.total_amount ELSE 0 END) as transfer,
    SUM(o.total_amount) as total,
    COUNT(o.order_id) as count
    FROM orders o
    LEFT JOIN payment p ON o.order_id = p.order_id 
    WHERE o.payment_status = 'paid' AND DATE(o.created_at) = ?";

$stmt_sum = $conn->prepare($summary_sql);

if (!$stmt_sum) {
    die("<div class='alert alert-danger'>SQL Error: " . $conn->error . "</div>");
}

$stmt_sum->bind_param("s", $date);
$stmt_sum->execute();
$summary = $stmt_sum->get_result()->fetch_assoc();

// กำหนดค่าเริ่มต้นถ้าไม่มีข้อมูล
$summary = $summary ?: ['cash'=>0, 'transfer'=>0, 'total'=>0, 'count'=>0];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานสรุปรายได้ - <?php echo htmlspecialchars($restaurant_name); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700;800&display=swap');
        body { font-family: 'Sarabun', sans-serif; color: #333; padding: 40px; background: #fff; line-height: 1.6; }
        .report-container { max-width: 700px; margin: 0 auto; }
        .header { border-bottom: 3px solid #f0f2f5; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 28px; color: #1e293b; }
        .header p { margin: 5px 0 0; color: #64748b; font-size: 18px; }
        .row-top { display: flex; gap: 20px; margin-bottom: 20px; }
        .card { border-radius: 20px; padding: 25px; text-align: center; flex: 1; border: 1px solid transparent; }
        .card-cash { background-color: #f0fdf4; border-color: #dcfce7; color: #16a34a; }
        .card-transfer { background-color: #f0f9ff; border-color: #e0f2fe; color: #0284c7; }
        .card-grand { 
            background-color: #f8fafc; 
            border: 2px solid #e2e8f0; 
            padding: 30px; 
            border-radius: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        .label { font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; display: block; }
        .value { font-size: 28px; font-weight: 800; margin: 0; }
        .badge-count { 
            background: #fff; 
            border: 1px solid #e2e8f0; 
            padding: 10px 25px; 
            border-radius: 50px; 
            font-weight: bold;
            color: #475569;
        }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .report-container { width: 100%; max-width: none; }
            .card, .card-grand { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="report-container">
        <div class="header">
            <h1>รายงานสรุปรายได้</h1>
            <p><?php echo htmlspecialchars($restaurant_name); ?></p>
            <div style="margin-top: 10px; font-weight: bold; color: #1e293b;">
                ประจำวันที่ <?php echo date('d/m/Y', strtotime($date)); ?>
            </div>
        </div>

        <div class="row-top">
            <div class="card card-cash">
                <span class="label">ยอดเงินสด</span>
                <p class="value">฿<?php echo number_format($summary['cash'] ?? 0, 2); ?></p>
            </div>

            <div class="card card-transfer">
                <span class="label">ยอดเงินโอน</span>
                <p class="value">฿<?php echo number_format($summary['transfer'] ?? 0, 2); ?></p>
            </div>
        </div>

        <div class="card-grand">
            <div>
                <span class="label" style="color: #64748b;">ยอดขายรวมสุทธิ</span>
                <p class="value" style="font-size: 40px; color: #0f172a;">฿<?php echo number_format($summary['total'] ?? 0, 2); ?></p>
            </div>
            <div class="badge-count">
                <i class="bi bi-receipt"></i> <?php echo number_format($summary['count'] ?? 0); ?> ออเดอร์
            </div>
        </div>

        <div style="margin-top: 50px; padding-top: 20px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; font-size: 13px; color: #94a3b8;">
            <span>ตรวจสอบโดยระบบจัดการร้านอาหาร</span>
            <span>วันที่พิมพ์: <?php echo date('d/m/Y H:i'); ?> น.</span>
        </div>

        <div class="no-print" style="margin-top: 40px; text-align: center;">
            <button onclick="window.close()" style="padding: 12px 30px; border-radius: 50px; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; font-weight: bold; color: #475569;">
                <i class="bi bi-x-lg me-1"></i> ปิดหน้าต่าง
            </button>
        </div>
    </div>
</body>
</html>