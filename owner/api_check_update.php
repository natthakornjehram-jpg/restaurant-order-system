<?php
// owner/api_check_update.php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';

// ตั้งค่า Header ให้ส่งข้อมูลกลับเป็น JSON
header('Content-Type: application/json');

// เนื่องจากเป็นร้านเดี่ยว ทุกออเดอร์คือของร้านนี้อยู่แล้ว
// ดึงจำนวนออเดอร์ที่มีสถานะ 'pending' จากตาราง orders ได้ตรงๆ เลย
$order_stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders o
    WHERE o.order_status = 'pending'
    AND NOT EXISTS (
        SELECT 1 FROM payment p
        WHERE p.order_id = o.order_id
        AND p.slip_image IS NOT NULL
        AND p.status != 'completed'
    )");
$order_stmt->execute();
$result = $order_stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode(['pending_count' => $row['count']]);
} else {
    echo json_encode(['pending_count' => 0]);
}

$order_stmt->close();
?>
