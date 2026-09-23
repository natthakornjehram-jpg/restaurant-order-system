<?php
// owner/print_stock_transactions.php
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';

$date_from = $_GET['from'] ?? date('Y-m-d');
$date_to = $_GET['to'] ?? date('Y-m-d');

$stmt_rest = $conn->prepare("SELECT restaurant_name FROM owner WHERE owner_id = ? LIMIT 1");
$stmt_rest->bind_param("i", $owner_id);
$stmt_rest->execute();
$rest_data = $stmt_rest->get_result()->fetch_assoc();
$restaurant_name = !empty($rest_data['restaurant_name']) ? $rest_data['restaurant_name'] : 'ไม่ระบุชื่อร้าน';

$stmt = $conn->prepare(
    "SELECT * FROM stock_transactions
     WHERE DATE(occurred_at) BETWEEN ? AND ?
     ORDER BY occurred_at DESC, transaction_id DESC
     LIMIT 500"
);
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$type_labels = ['item' => 'เมนูอาหาร', 'topping' => 'ท็อปปิ้ง/วัตถุดิบเสริม', 'pool' => 'กลุ่มสต็อกร่วม'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บันทึกรับ-จ่าย - <?= htmlspecialchars($restaurant_name) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700;800&display=swap');
        body { font-family: 'Sarabun', sans-serif; color: #1e293b; padding: 30px; background: #fff; }
        .report-container { max-width: 1000px; margin: 0 auto; }
        .header { border-bottom: 3px solid #f0f2f5; padding-bottom: 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 4px 0 0; color: #64748b; }
        table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 7px 8px; text-align: left; }
        th { background: #f8fafc; font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.03em; color: #64748b; }
        td.num, th.num { text-align: right; }
        .qty-in { color: #16a34a; font-weight: bold; }
        .qty-out { color: #dc2626; font-weight: bold; }
        .footer-note { margin-top: 30px; padding-top: 12px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; font-size: 12px; color: #94a3b8; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="report-container">
        <div class="header">
            <div>
                <h1>บันทึกรับ-จ่ายสต็อก</h1>
                <p><?= htmlspecialchars($restaurant_name) ?> — ตั้งแต่ <?= date('d/m/Y', strtotime($date_from)) ?> ถึง <?= date('d/m/Y', strtotime($date_to)) ?></p>
            </div>
            <p>ทั้งหมด <?= count($transactions) ?> รายการ</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>วันที่เวลา</th>
                    <th>SKU</th>
                    <th>ชื่อสินค้า</th>
                    <th>ประเภท</th>
                    <th class="num">เข้า (In)</th>
                    <th class="num">ออก (Out)</th>
                    <th class="num">คงเหลือ</th>
                    <th>เอกสาร/ออเดอร์อ้างอิง</th>
                    <th>ผู้ทำรายการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($t['occurred_at'])) ?></td>
                    <td><?= htmlspecialchars($t['sku'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($t['item_name']) ?></td>
                    <td><?= htmlspecialchars($type_labels[$t['item_type']] ?? $t['item_type']) ?></td>
                    <td class="num qty-in"><?= $t['qty_change'] > 0 ? '+' . $t['qty_change'] : '' ?></td>
                    <td class="num qty-out"><?= $t['qty_change'] < 0 ? $t['qty_change'] : '' ?></td>
                    <td class="num"><?= $t['qty_after'] ?></td>
                    <td><?= htmlspecialchars($t['source_ref'] ?: ($t['note'] ?: '-')) ?></td>
                    <td><?= htmlspecialchars($t['created_by'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="footer-note">
            <span>พิมพ์โดยระบบจัดการร้านอาหาร</span>
            <span>วันที่พิมพ์: <?= date('d/m/Y H:i') ?> น.</span>
        </div>

        <div class="no-print" style="margin-top: 30px; text-align: center;">
            <button onclick="window.close()" style="padding: 10px 26px; border-radius: 50px; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; font-weight: bold; color: #475569;">ปิดหน้าต่าง</button>
        </div>
    </div>
</body>
</html>
