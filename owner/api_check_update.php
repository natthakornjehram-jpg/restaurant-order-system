<?php
// owner/api_check_update.php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';

// ตั้งค่า Header ให้ส่งข้อมูลกลับเป็น JSON
header('Content-Type: application/json');

// ตรวจสอบว่ามีการล็อกอินในฐานะเจ้าของร้านหรือไม่ 
// (อิงตาม Session ที่คุณน่าจะเซ็ตไว้ตอน Login หน้า Owner)
if (!isset($_SESSION['owner_id'])) {
    echo json_encode(['pending_count' => 0, 'unpaid_count' => 0]);
    exit;
}

// เนื่องจากเป็นร้านเดี่ยว ทุกออเดอร์คือของร้านนี้อยู่แล้ว
// ดึงจำนวนออเดอร์ที่มีสถานะ 'pending' จากตาราง orders ได้ตรงๆ เลย
$order_stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'");
$order_stmt->execute();
$result = $order_stmt->get_result();
$pending_count = ($result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;
$order_stmt->close();

// จำนวนบิลที่รอชำระ/รอตรวจสลิป (ออเดอร์เสิร์ฟ/พร้อมแล้วแต่ยังไม่ได้จ่ายเงิน) — ให้ตรงกับหน้า Dashboard
$unpaid_stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE order_status IN ('served', 'ready') AND payment_status = 'unpaid'");
$unpaid_stmt->execute();
$unpaid_result = $unpaid_stmt->get_result();
$unpaid_count = ($unpaid_result->num_rows > 0) ? $unpaid_result->fetch_assoc()['count'] : 0;
$unpaid_stmt->close();

echo json_encode(['pending_count' => $pending_count, 'unpaid_count' => $unpaid_count]);
?>
