<?php
// owner/print_product_list.php
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';

$stmt_rest = $conn->prepare("SELECT restaurant_name FROM owner WHERE owner_id = ? LIMIT 1");
$stmt_rest->bind_param("i", $owner_id);
$stmt_rest->execute();
$rest_data = $stmt_rest->get_result()->fetch_assoc();
$restaurant_name = !empty($rest_data['restaurant_name']) ? $rest_data['restaurant_name'] : 'ไม่ระบุชื่อร้าน';

$products = [];
$res_items = $conn->query("SELECT sku, name, price, reorder_point, stock_qty, c.category_name FROM item i LEFT JOIN category c ON i.category_id = c.category_id WHERE i.use_stock = 1 ORDER BY i.name ASC");
if ($res_items) { while ($r = $res_items->fetch_assoc()) { $r['type_label'] = 'เมนูอาหาร'; $r['category_name'] = $r['category_name'] ?: 'ไม่มีหมวดหมู่'; $products[] = $r; } }

$res_toppings = $conn->query("SELECT t.sku, t.topping_name AS name, t.price, t.reorder_point, t.stock_qty, tc.topping_cat_name AS category_name FROM topping t LEFT JOIN topping_categories tc ON t.topping_cat_id = tc.topping_cat_id WHERE t.use_stock = 1 ORDER BY t.topping_name ASC");
if ($res_toppings) { while ($r = $res_toppings->fetch_assoc()) { $r['type_label'] = 'ท็อปปิ้ง/วัตถุดิบเสริม'; $r['category_name'] = $r['category_name'] ?: 'ไม่มีหมวดหมู่'; $products[] = $r; } }

$res_pools = $conn->query("SELECT sku, pool_name AS name, NULL AS price, reorder_point, stock_qty, pool_category AS category_name FROM stock_pool ORDER BY pool_name ASC");
if ($res_pools) { while ($r = $res_pools->fetch_assoc()) { $r['type_label'] = 'กลุ่มสต็อกร่วม'; $r['category_name'] = $r['category_name'] ?: 'ไม่มีหมวดหมู่'; $products[] = $r; } }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการสินค้า - <?= htmlspecialchars($restaurant_name) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700;800&display=swap');
        body { font-family: 'Sarabun', sans-serif; color: #1e293b; padding: 30px; background: #fff; }
        .report-container { max-width: 950px; margin: 0 auto; }
        .header { border-bottom: 3px solid #f0f2f5; padding-bottom: 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 4px 0 0; color: #64748b; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
        th { background: #f8fafc; font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em; color: #64748b; }
        td.num, th.num { text-align: right; }
        .qty-warn { color: #d97706; font-weight: bold; }
        .qty-danger { color: #dc2626; font-weight: bold; }
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
                <h1>รายการสินค้า</h1>
                <p><?= htmlspecialchars($restaurant_name) ?></p>
            </div>
            <p>ทั้งหมด <?= count($products) ?> รายการ</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>ชื่อสินค้า</th>
                    <th>ประเภท</th>
                    <th>หมวดหมู่</th>
                    <th class="num">ราคาขาย</th>
                    <th class="num">คงเหลือ</th>
                    <th class="num">จุดสั่งซื้อซ้ำ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p):
                    $qty = (int) $p['stock_qty'];
                    $reorder = (int) $p['reorder_point'];
                    $qty_class = $qty <= 0 ? 'qty-danger' : ($qty <= $reorder ? 'qty-warn' : '');
                ?>
                <tr>
                    <td><?= htmlspecialchars($p['sku'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><?= htmlspecialchars($p['type_label']) ?></td>
                    <td><?= htmlspecialchars($p['category_name']) ?></td>
                    <td class="num"><?= $p['price'] !== null ? number_format((float) $p['price'], 2) : '-' ?></td>
                    <td class="num <?= $qty_class ?>"><?= $qty ?></td>
                    <td class="num"><?= $reorder ?></td>
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
