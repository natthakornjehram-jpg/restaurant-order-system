<?php
// owner/stock_transactions.php
// บันทึกรับ-จ่ายสต็อก: ประวัติทุกครั้งที่จำนวนคงเหลือของเมนู/ท็อปปิ้ง/กลุ่มสต็อกร่วมเปลี่ยน ไม่ว่าจะตัดอัตโนมัติ
// จากออเดอร์ลูกค้า หรือเจ้าของร้านปรับมือเองในหน้าจัดการคลังสินค้า (ดู includes/stock_log.php ที่บันทึกข้อมูลนี้ไว้)
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';

$date_from = $_GET['from'] ?? date('Y-m-d');
$date_to = $_GET['to'] ?? date('Y-m-d');

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

include '../includes/header_owner.php';
include '../includes/nav_owner.php';
?>

<style>
    .txn-table th { white-space: nowrap; font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
    .txn-table td { vertical-align: middle; }
    .sku-badge { font-family: 'Courier New', monospace; font-weight: bold; background: #f1f5f9; padding: 3px 10px; border-radius: 8px; font-size: 0.85rem; }
    .qty-in { color: #16a34a; font-weight: bold; }
    .qty-out { color: #dc2626; font-weight: bold; }
</style>

<div class="main-content container-fluid pb-5 px-4 pt-3 text-dark">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-white rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; border: 1px solid #edf2f7; background: #ffffff; color: #4a5568;" title="ย้อนกลับ">
                <i class="bi bi-arrow-left fs-4"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-0" style="color: #1a202c; font-size: 1.25rem;">
                    <i class="bi bi-clock-history text-primary me-2"></i>บันทึกรับ-จ่าย
                </h4>
                <p class="text-muted small mb-0">ประวัติการเข้า-ออกของสต็อกทุกครั้ง ทั้งจากออเดอร์ลูกค้าและการปรับมือ</p>
            </div>
        </div>
        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold shadow-sm" onclick="printTransactions()">
            <i class="bi bi-printer me-1"></i> พิมพ์รายการ
        </button>
    </div>

    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end mb-4">
        <div>
            <label class="small fw-bold mb-1 d-block">จากวันที่</label>
            <input type="date" name="from" class="form-control rounded-3" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div>
            <label class="small fw-bold mb-1 d-block">ถึงวันที่</label>
            <input type="date" name="to" class="form-control rounded-3" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
            <i class="bi bi-search me-1"></i> ค้นหา
        </button>
    </form>

    <?php if (empty($transactions)): ?>
        <div class="text-center py-5">
            <i class="bi bi-clock-history display-1 text-muted opacity-25"></i>
            <p class="mt-3 text-muted">ไม่มีรายการรับ-จ่ายในช่วงวันที่นี้</p>
        </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm rounded-4 p-3">
        <div class="table-responsive">
            <table class="table txn-table mb-0">
                <thead>
                    <tr>
                        <th>วันที่เวลา</th>
                        <th>SKU</th>
                        <th>ชื่อสินค้า</th>
                        <th>ประเภท</th>
                        <th class="text-end">จำนวนเข้า (In)</th>
                        <th class="text-end">จำนวนออก (Out)</th>
                        <th class="text-end">คงเหลือ</th>
                        <th>เอกสาร/ออเดอร์อ้างอิง</th>
                        <th>ผู้ทำรายการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($t['occurred_at'])) ?></td>
                        <td><span class="sku-badge"><?= htmlspecialchars($t['sku'] ?: '-') ?></span></td>
                        <td class="fw-bold"><?= htmlspecialchars($t['item_name']) ?></td>
                        <td><span class="badge bg-light text-dark rounded-pill"><?= htmlspecialchars($type_labels[$t['item_type']] ?? $t['item_type']) ?></span></td>
                        <td class="text-end qty-in"><?= $t['qty_change'] > 0 ? '+' . $t['qty_change'] : '' ?></td>
                        <td class="text-end qty-out"><?= $t['qty_change'] < 0 ? $t['qty_change'] : '' ?></td>
                        <td class="text-end fw-bold"><?= $t['qty_after'] ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($t['source_ref'] ?: ($t['note'] ?: '-')) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($t['created_by'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function printTransactions() {
    const from = document.querySelector('input[name="from"]').value;
    const to = document.querySelector('input[name="to"]').value;
    window.open('print_stock_transactions.php?from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to), '_blank', 'width=1000,height=700');
}
</script>

<?php include '../includes/footer_owner.php'; ?>
