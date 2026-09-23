<?php
// includes/stock_log.php
// บันทึกทุกครั้งที่จำนวนคงเหลือของเมนู/ท็อปปิ้ง/กลุ่มสต็อกร่วมเปลี่ยน ลงตาราง stock_transactions
// ให้เจ้าของร้านย้อนดูได้ว่าอะไรเปลี่ยนไปเมื่อไหร่ จากออเดอร์ไหน หรือปรับมือเอง (ดูหน้า owner/stock_transactions.php)

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
