<?php
// qr_table/api_check_bill.php
// ให้หน้าลูกค้า (dine-in) โพลถามว่า "ร้านปิดบิลของโต๊ะนี้ให้แล้วหรือยัง"
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// ต้องเคยสแกนโต๊ะและเคยส่งออเดอร์แล้วอย่างน้อย 1 ครั้งในรอบนี้ ถึงจะเช็ก
// (กันเคสสแกนมาเฉยๆยังไม่สั่งอะไรเลย ไม่ให้โดนเด้งออกทันที)
if (!isset($_SESSION['table_id']) || empty($_SESSION['has_ordered'])) {
    echo json_encode(['closed' => false]);
    exit;
}

$table_id = intval($_SESSION['table_id']);

$stmt = $conn->prepare("SELECT COUNT(*) AS unpaid FROM orders WHERE table_id = ? AND payment_status = 'unpaid'");
$stmt->bind_param("i", $table_id);
$stmt->execute();
$unpaid = $stmt->get_result()->fetch_assoc()['unpaid'];

echo json_encode(['closed' => intval($unpaid) === 0]);

$stmt->close();
$conn->close();
?>
