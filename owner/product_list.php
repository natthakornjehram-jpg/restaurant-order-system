<?php
// owner/product_list.php
// รายการสินค้าทั้งหมด (เมนู/ท็อปปิ้ง/กลุ่มสต็อกร่วม) แบบตารางเรียบง่าย: SKU, ชื่อ, หมวดหมู่, ราคาขาย, จุดสั่งซื้อซ้ำ
// แยกออกมาจากหน้าจัดการคลังสินค้า (manage_stock.php) เพราะหน้านั้นเน้นปรับจำนวน/ผูกกลุ่ม ส่วนหน้านี้เน้นดู/แก้
// ข้อมูลหลักของสินค้าและพิมพ์เป็นเอกสารได้ (ดู print_product_list.php)
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';
require_once '../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $item_type = $_POST['item_type'] ?? '';
    $item_id = intval($_POST['item_id'] ?? 0);
    $sku = trim($_POST['sku'] ?? '');
    $reorder_point = max(0, intval($_POST['reorder_point'] ?? 0));
    $sku = $sku !== '' ? $sku : null;

    $table_map = ['item' => ['item', 'item_id'], 'topping' => ['topping', 'topping_id'], 'pool' => ['stock_pool', 'pool_id']];
    if (!isset($table_map[$item_type]) || $item_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']);
        exit;
    }
    [$table, $pk] = $table_map[$item_type];

    // เช็ก SKU ซ้ำเอง (คนละข้อความจาก MySQL unique key error ให้เข้าใจง่ายกว่า)
    if ($sku !== null) {
        $dup_stmt = $conn->prepare("SELECT 1 FROM item WHERE sku = ? AND item_id != ? UNION SELECT 1 FROM topping WHERE sku = ? AND topping_id != ? UNION SELECT 1 FROM stock_pool WHERE sku = ? AND pool_id != ?");
        $dummy_item_id = $item_type === 'item' ? $item_id : 0;
        $dummy_topping_id = $item_type === 'topping' ? $item_id : 0;
        $dummy_pool_id = $item_type === 'pool' ? $item_id : 0;
        $dup_stmt->bind_param("sisisi", $sku, $dummy_item_id, $sku, $dummy_topping_id, $sku, $dummy_pool_id);
        $dup_stmt->execute();
        if ($dup_stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'SKU นี้ถูกใช้ไปแล้ว กรุณาตั้งชื่ออื่น']);
            exit;
        }
    }

    $stmt = $conn->prepare("UPDATE `$table` SET sku = ?, reorder_point = ? WHERE `$pk` = ?");
    $stmt->bind_param("sii", $sku, $reorder_point, $item_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด ไม่สามารถบันทึกได้']);
    }
    exit;
}

// ดึงสินค้าทั้ง 3 ประเภทมารวมเป็นลิสต์เดียว เรียงตามประเภทแล้วตามชื่อ ให้ดูง่ายในตารางเดียว
$products = [];
$res_items = $conn->query("SELECT item_id AS id, sku, name, price, reorder_point, stock_qty, c.category_name FROM item i LEFT JOIN category c ON i.category_id = c.category_id WHERE i.use_stock = 1 ORDER BY i.name ASC");
if ($res_items) { while ($r = $res_items->fetch_assoc()) { $r['type'] = 'item'; $r['type_label'] = 'เมนูอาหาร'; $r['category_name'] = $r['category_name'] ?: 'ไม่มีหมวดหมู่'; $products[] = $r; } }

$res_toppings = $conn->query("SELECT t.topping_id AS id, t.sku, t.topping_name AS name, t.price, t.reorder_point, t.stock_qty, tc.topping_cat_name AS category_name FROM topping t LEFT JOIN topping_categories tc ON t.topping_cat_id = tc.topping_cat_id WHERE t.use_stock = 1 ORDER BY t.topping_name ASC");
if ($res_toppings) { while ($r = $res_toppings->fetch_assoc()) { $r['type'] = 'topping'; $r['type_label'] = 'ท็อปปิ้ง/วัตถุดิบเสริม'; $r['category_name'] = $r['category_name'] ?: 'ไม่มีหมวดหมู่'; $products[] = $r; } }

$res_pools = $conn->query("SELECT pool_id AS id, sku, pool_name AS name, NULL AS price, reorder_point, stock_qty, pool_category AS category_name FROM stock_pool ORDER BY pool_name ASC");
if ($res_pools) { while ($r = $res_pools->fetch_assoc()) { $r['type'] = 'pool'; $r['type_label'] = 'กลุ่มสต็อกร่วม'; $r['category_name'] = $r['category_name'] ?: 'ไม่มีหมวดหมู่'; $products[] = $r; } }

include '../includes/header_owner.php';
include '../includes/nav_owner.php';
?>

<style>
    .product-table th { white-space: nowrap; font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
    .product-table td { vertical-align: middle; }
    .sku-badge { font-family: 'Courier New', monospace; font-weight: bold; background: #f1f5f9; padding: 3px 10px; border-radius: 8px; font-size: 0.85rem; }
    .qty-warn { color: #d97706; font-weight: bold; }
    .qty-danger { color: #dc2626; font-weight: bold; }
</style>

<div class="main-content container-fluid pb-5 px-4 pt-3 text-dark">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-white rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; border: 1px solid #edf2f7; background: #ffffff; color: #4a5568;" title="ย้อนกลับ">
                <i class="bi bi-arrow-left fs-4"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-0" style="color: #1a202c; font-size: 1.25rem;">
                    <i class="bi bi-upc-scan text-primary me-2"></i>รายการสินค้า
                </h4>
                <p class="text-muted small mb-0">SKU, ชื่อสินค้า, หมวดหมู่, ราคาขาย และจุดสั่งซื้อซ้ำของทุกรายการที่ติดตามคลังสินค้า</p>
            </div>
        </div>
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold shadow-sm" onclick="window.open('print_product_list.php', '_blank', 'width=900,height=700')">
            <i class="bi bi-printer me-1"></i> พิมพ์รายการ
        </button>
    </div>

    <?php if (empty($products)): ?>
        <div class="text-center py-5">
            <i class="bi bi-upc-scan display-1 text-muted opacity-25"></i>
            <p class="mt-3 text-muted">ยังไม่มีสินค้าที่ติดตามคลังสินค้าในระบบ</p>
        </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-3">
        <div class="table-responsive">
            <table class="table product-table mb-0">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>ชื่อสินค้า</th>
                        <th>ประเภท</th>
                        <th>หมวดหมู่</th>
                        <th class="text-end">ราคาขาย</th>
                        <th class="text-end">คงเหลือ</th>
                        <th class="text-end">จุดสั่งซื้อซ้ำ</th>
                        <th class="text-center">แก้ไข</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p):
                        $qty = (int) $p['stock_qty'];
                        $reorder = (int) $p['reorder_point'];
                        $qty_class = $qty <= 0 ? 'qty-danger' : ($qty <= $reorder ? 'qty-warn' : '');
                    ?>
                    <tr>
                        <td><span class="sku-badge"><?= htmlspecialchars($p['sku'] ?: '-') ?></span></td>
                        <td class="fw-bold"><?= htmlspecialchars($p['name']) ?></td>
                        <td><span class="badge bg-light text-dark rounded-pill"><?= htmlspecialchars($p['type_label']) ?></span></td>
                        <td class="text-muted small"><?= htmlspecialchars($p['category_name']) ?></td>
                        <td class="text-end"><?= $p['price'] !== null ? '฿' . number_format((float) $p['price'], 2) : '-' ?></td>
                        <td class="text-end <?= $qty_class ?>"><?= $qty ?></td>
                        <td class="text-end"><?= $reorder ?></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" onclick='openEditProductModal(<?= json_encode([
                                'type' => $p['type'],
                                'id' => (int) $p['id'],
                                'name' => $p['name'],
                                'sku' => $p['sku'],
                                'reorder_point' => $reorder,
                            ], JSON_UNESCAPED_UNICODE) ?>)'>
                                <i class="bi bi-pencil-square"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: แก้ไข SKU / จุดสั่งซื้อซ้ำ -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 rounded-4" id="editProductForm">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="fw-bold m-0">แก้ไขข้อมูลสินค้า <span id="editProductName" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <input type="hidden" id="editProductType" name="item_type">
                <input type="hidden" id="editProductId" name="item_id">
                <div class="mb-3">
                    <label class="small fw-bold mb-2">SKU</label>
                    <input type="text" id="editProductSku" name="sku" class="form-control rounded-3" placeholder="เช่น ITM-001">
                </div>
                <div class="mb-3">
                    <label class="small fw-bold mb-2">จุดสั่งซื้อซ้ำ (แจ้งเตือน "ใกล้หมด" เมื่อคงเหลือถึงจำนวนนี้)</label>
                    <input type="number" step="1" min="0" id="editProductReorder" name="reorder_point" class="form-control rounded-3" value="5" required>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditProductModal(p) {
    document.getElementById('editProductType').value = p.type;
    document.getElementById('editProductId').value = p.id;
    document.getElementById('editProductName').textContent = '"' + p.name + '"';
    document.getElementById('editProductSku').value = p.sku || '';
    document.getElementById('editProductReorder').value = p.reorder_point;
    new bootstrap.Modal(document.getElementById('editProductModal')).show();
}

document.getElementById('editProductForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('product_list.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (!data.success) { ownerNotify(data.error || 'เกิดข้อผิดพลาด ไม่สามารถบันทึกได้', 'error'); return; }
            bootstrap.Modal.getInstance(document.getElementById('editProductModal')).hide();
            ownerNotify('บันทึกข้อมูลสินค้าเรียบร้อยแล้ว');
            setTimeout(() => window.location.reload(), 600);
        })
        .catch(() => ownerNotify('เกิดข้อผิดพลาด ไม่สามารถบันทึกได้', 'error'));
});
</script>

<?php include '../includes/footer_owner.php'; ?>
