<?php
// includes/stock_log.php
// บันทึกทุกครั้งที่จำนวนคงเหลือของเมนู/ท็อปปิ้ง/กลุ่มสต็อกร่วมเปลี่ยน ลงตาราง stock_transactions
// ให้เจ้าของร้านย้อนดูได้ว่าอะไรเปลี่ยนไปเมื่อไหร่ จากออเดอร์ไหน หรือปรับมือเอง (ดูแท็บ "บันทึกรับ-จ่าย" ใน owner/manage_stock.php)

/**
 * @param string $item_type 'item' | 'topping' | 'pool'
 * @param int $item_id item_id / topping_id / pool_id ตาม $item_type
 * @param int $qty_change บวก = รับเข้า (in), ลบ = จ่ายออก (out)
 * @param int $qty_after จำนวนคงเหลือหลังทำรายการนี้
 * @param string $source_type 'order' (ตัดสต็อกอัตโนมัติจากออเดอร์ลูกค้า) | 'manual' (เจ้าของร้านปรับเอง)
 * @param string|null $source_ref เลขที่อ้างอิง เช่น เลขที่ออเดอร์ (daily_order_no) - null ถ้าปรับมือ
 */
function log_stock_transaction(
    mysqli $conn,
    string $item_type,
    int $item_id,
    string $sku,
    string $item_name,
    int $qty_change,
    int $qty_after,
    string $source_type,
    ?string $source_ref = null,
    ?string $note = null
): void {
    $created_by = ($source_type === 'manual') ? 'เจ้าของร้าน' : 'ระบบ (ออเดอร์ลูกค้า)';
    $stmt = $conn->prepare(
        "INSERT INTO stock_transactions
            (item_type, item_id, sku, item_name, qty_change, qty_after, source_type, source_ref, note, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "sissiissss",
        $item_type,
        $item_id,
        $sku,
        $item_name,
        $qty_change,
        $qty_after,
        $source_type,
        $source_ref,
        $note,
        $created_by
    );
    $stmt->execute();
}

// อ่านตัวกรองบันทึกรับ-จ่ายจาก query string (ใช้ร่วมกันทั้งแท็บในหน้า, หน้าพิมพ์ PDF และหน้าส่งออก Excel/CSV
// เพื่อให้ผลลัพธ์ตรงกันทั้ง 3 ที่เสมอ ไม่ต้องเขียนเงื่อนไขกรองซ้ำหลายที่)
function get_stock_transaction_filters(): array
{
    return [
        'from' => $_GET['txn_from'] ?? date('Y-m-d'),
        'from_time' => $_GET['txn_from_time'] ?? '00:00',
        'to' => $_GET['txn_to'] ?? date('Y-m-d'),
        'to_time' => $_GET['txn_to_time'] ?? '23:59',
        'type' => in_array($_GET['txn_type'] ?? 'all', ['item', 'topping', 'pool'], true) ? $_GET['txn_type'] : 'all',
        'direction' => in_array($_GET['txn_direction'] ?? 'all', ['in', 'out'], true) ? $_GET['txn_direction'] : 'all',
        'q' => trim($_GET['txn_q'] ?? ''),
    ];
}

/** คืนแถวประวัติรับ-จ่ายตามตัวกรอง (สูงสุด 1000 แถวล่าสุด) */
function query_stock_transactions(mysqli $conn, array $f): array
{
    $sql = "SELECT * FROM stock_transactions WHERE occurred_at BETWEEN ? AND ?";
    $types = "ss";
    $params = [$f['from'] . ' ' . $f['from_time'] . ':00', $f['to'] . ' ' . $f['to_time'] . ':59'];

    if ($f['type'] !== 'all') {
        $sql .= " AND item_type = ?";
        $types .= "s";
        $params[] = $f['type'];
    }
    if ($f['direction'] === 'in') {
        $sql .= " AND qty_change > 0";
    } elseif ($f['direction'] === 'out') {
        $sql .= " AND qty_change < 0";
    }
    if ($f['q'] !== '') {
        $sql .= " AND (item_name LIKE ? OR sku LIKE ?)";
        $types .= "ss";
        $like = '%' . $f['q'] . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= " ORDER BY occurred_at DESC, transaction_id DESC LIMIT 1000";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
