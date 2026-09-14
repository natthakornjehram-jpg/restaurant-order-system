<?php
// owner/print_receipt.php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id <= 0) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'>ไม่ระบุออเดอร์</div>");
}

// 1. ดึงข้อมูลร้านค้า
$store_stmt = $conn->prepare("SELECT restaurant_name, phone, address FROM owner WHERE owner_id = ? LIMIT 1");
$store_stmt->bind_param("i", $owner_id);
$store_stmt->execute();
$store = $store_stmt->get_result()->fetch_assoc();
$restaurant_name = !empty($store['restaurant_name']) ? $store['restaurant_name'] : 'ร้านอาหารของเรา';

// 2. ดึงข้อมูลออเดอร์
$order_stmt = $conn->prepare("SELECT o.*, t.table_number 
    FROM orders o 
    LEFT JOIN restauranttable t ON o.table_id = t.table_id 
    WHERE o.order_id = ? LIMIT 1");
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order = $order_stmt->get_result()->fetch_assoc();

if (!$order) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'>ไม่พบข้อมูลออเดอร์นี้</div>");
}

// 3. ดึงข้อมูลการชำระเงิน
$pay_stmt = $conn->prepare("SELECT method, status FROM payment WHERE order_id = ? LIMIT 1");
$pay_stmt->bind_param("i", $order_id);
$pay_stmt->execute();
$payment = $pay_stmt->get_result()->fetch_assoc();
$pay_method_map = ['cash' => 'เงินสด', 'transfer' => 'เงินโอน', 'qr_counter' => 'สแกน QR หน้าเคาน์เตอร์'];
$pay_method_text = $pay_method_map[$payment['method'] ?? ''] ?? 'ไม่ระบุ';

// 4. ดึงรายการอาหารพร้อมท็อปปิ้ง
$items_stmt = $conn->prepare("SELECT od.*, i.name AS item_name,
    (SELECT GROUP_CONCAT(t.topping_name SEPARATOR ', ') 
     FROM orderdetail_topping odt 
     JOIN topping t ON odt.topping_id = t.topping_id 
     WHERE odt.order_detail_id = od.order_detail_id) AS topping_names
    FROM orderdetail od
    JOIN item i ON od.item_id = i.item_id
    WHERE od.order_id = ?");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_res = $items_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบเสร็จรับเงิน - #ORD-<?= str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap');
        * { box-sizing: border-box; }
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f4f6f8;
            margin: 0;
            padding: 20px;
            color: #1a202c;
        }
        .receipt-box {
            max-width: 380px;
            margin: 0 auto;
            background: #fff;
            padding: 24px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: 700; }
        .small { font-size: 0.85rem; color: #64748b; }
        .dotted-line {
            border-bottom: 1px dashed #cbd5e1;
            margin: 12px 0;
        }
        .solid-line {
            border-bottom: 2px solid #334155;
            margin: 12px 0;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
            font-size: 0.95rem;
        }
        .item-name { flex-grow: 1; padding-right: 10px; }
        .topping-text { font-size: 0.8rem; color: #475569; margin-left: 12px; margin-bottom: 4px; }
        .note-text { font-size: 0.8rem; color: #dc2626; margin-left: 12px; margin-bottom: 4px; font-style: italic; }
        .grand-total-row {
            display: flex;
            justify-content: space-between;
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin-top: 8px;
        }
        .btn-print-action {
            display: block;
            width: 100%;
            padding: 10px;
            margin-top: 20px;
            background: #1e293b;
            color: #fff;
            border: none;
            border-radius: 25px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .receipt-box { max-width: 100%; width: 100%; box-shadow: none; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">

<div class="receipt-box">
    <div class="text-center">
        <h2 style="margin: 0 0 4px; font-size: 1.4rem;"><?= htmlspecialchars($restaurant_name) ?></h2>
        <?php if (!empty($store['phone'])): ?>
            <div class="small">โทร: <?= htmlspecialchars($store['phone']) ?></div>
        <?php endif; ?>
        <?php if (!empty($store['address'])): ?>
            <div class="small"><?= htmlspecialchars($store['address']) ?></div>
        <?php endif; ?>
    </div>

    <div class="solid-line"></div>

    <div class="small">
        <div><strong>ใบเสร็จรับเงิน / Receipt</strong></div>
        <div>เลขที่: #ORD-<?= str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) ?></div>
        <div>วันที่: <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?> น.</div>
        <div>ประเภท: <?= ($order['order_type'] === 'dine_in') ? 'ทานที่ร้าน' . (!empty($order['table_number']) ? ' (โต๊ะ ' . htmlspecialchars($order['table_number']) . ')' : '') : 'สั่งกลับบ้าน' ?></div>
        <?php if (!empty($order['online_customer_name'])): ?>
            <div>ลูกค้า: <?= htmlspecialchars($order['online_customer_name']) ?> <?= !empty($order['online_customer_phone']) ? '(' . htmlspecialchars($order['online_customer_phone']) . ')' : '' ?></div>
        <?php endif; ?>
    </div>

    <div class="dotted-line"></div>

    <div>
        <?php while ($item = $items_res->fetch_assoc()): 
            $subtotal = $item['quantity'] * $item['unit_price'];
        ?>
            <div class="item-row">
                <div class="item-name"><?= $item['quantity'] ?>x <?= htmlspecialchars($item['item_name']) ?></div>
                <div class="fw-bold">฿<?= number_format($subtotal, 2) ?></div>
            </div>
            <?php if (!empty($item['topping_names'])): ?>
                <div class="topping-text">+ <?= htmlspecialchars($item['topping_names']) ?></div>
            <?php endif; ?>
            <?php if (!empty($item['note'])): ?>
                <div class="note-text">* <?= htmlspecialchars($item['note']) ?></div>
            <?php endif; ?>
        <?php endwhile; ?>
    </div>

    <div class="dotted-line"></div>

    <div class="grand-total-row">
        <span>ยอดรวมสุทธิ</span>
        <span>฿<?= number_format($order['total_amount'], 2) ?></span>
    </div>

    <div class="small" style="margin-top: 8px;">
        <div>การชำระเงิน: <?= $pay_method_text ?></div>
        <div>สถานะ: <?= ($order['payment_status'] === 'paid') ? 'ชำระเงินแล้ว' : 'ยังไม่ชำระ' ?></div>
    </div>

    <div class="dotted-line"></div>

    <div class="text-center small" style="margin-top: 12px;">
        ขอบคุณที่มาอุดหนุนครับ 🙏<br>
        กรุณาเก็บใบเสร็จไว้เป็นหลักฐาน
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn-print-action"><i class="bi bi-printer"></i> พิมพ์ใบเสร็จ</button>
        <button onclick="window.close()" class="btn-print-action" style="background: #e2e8f0; color: #334155; margin-top: 8px;"><i class="bi bi-x-lg"></i> ปิดหน้าต่าง</button>
    </div>
</div>

</body>
</html>
