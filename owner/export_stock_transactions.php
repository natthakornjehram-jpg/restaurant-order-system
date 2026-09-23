<?php
// owner/export_stock_transactions.php
// ส่งออกบันทึกรับ-จ่ายสต็อกเป็นไฟล์ CSV (เปิดได้ตรงใน Excel) ตามตัวกรองเดียวกับที่แสดงอยู่ในแท็บ "บันทึกรับ-จ่าย"
// ไม่ได้ใช้ไลบรารีสร้างไฟล์ .xlsx จริง (โปรเจกต์นี้ไม่ได้ตั้ง Composer ไว้) แต่ CSV ที่มี UTF-8 BOM เปิดใน
// Excel ได้ปกติ ตัวหนังสือไทยไม่เพี้ยน ถือว่าตอบโจทย์ "ดาวน์โหลดเป็น Excel" ได้โดยไม่ต้องเพิ่มความซับซ้อน
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';
require_once '../includes/stock_log.php';

$txn_filters = get_stock_transaction_filters();
$transactions = query_stock_transactions($conn, $txn_filters);
$type_labels = ['item' => 'เมนูอาหาร', 'topping' => 'ท็อปปิ้ง/วัตถุดิบเสริม', 'pool' => 'กลุ่มสต็อกร่วม'];

$filename = 'stock_transactions_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM กัน Excel เปิดแล้วภาษาไทยเป็นตัวอักษรมั่ว

fputcsv($out, ['วันที่เวลา', 'SKU', 'ชื่อสินค้า', 'ประเภท', 'จำนวนเข้า (In)', 'จำนวนออก (Out)', 'คงเหลือ', 'เอกสาร/ออเดอร์อ้างอิง', 'ผู้ทำรายการ']);

foreach ($transactions as $t) {
    fputcsv($out, [
        date('d/m/Y H:i', strtotime($t['occurred_at'])),
        $t['sku'] ?: '-',
        $t['item_name'],
        $type_labels[$t['item_type']] ?? $t['item_type'],
        $t['qty_change'] > 0 ? $t['qty_change'] : '',
        $t['qty_change'] < 0 ? $t['qty_change'] : '',
        $t['qty_after'],
        $t['source_ref'] ?: ($t['note'] ?: '-'),
        $t['created_by'] ?: '-',
    ]);
}

fclose($out);
exit;
