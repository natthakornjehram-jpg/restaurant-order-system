<?php
// owner/api_check_update.php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';

// ตั้งค่า Header ให้ส่งข้อมูลกลับเป็น JSON
header('Content-Type: application/json');

// เนื่องจากเป็นร้านเดี่ยว ทุกออเดอร์คือของร้านนี้อยู่แล้ว
// ดึงจำนวนออเดอร์ที่มีสถานะ 'pending' จากตาราง orders ได้ตรงๆ เลย
$order_stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'");
$order_stmt->execute();
$result = $order_stmt->get_result();
$pending_count = ($result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;
$order_stmt->close();

// จำนวนรายการที่รอเจ้าของร้านจัดการเรื่องเงิน (ออนไลน์รอตรวจสลิป + โต๊ะที่เสิร์ฟแล้วรอเช็คบิล)
// ใช้ตรวจว่าหน้าจัดการชำระเงินควรรีเฟรชตัวเองไหม (มีรายการใหม่เข้ามา หรือถูกจัดการจากอุปกรณ์อื่นไปแล้ว)
$pay_stmt = $conn->prepare("SELECT
    (SELECT COUNT(*) FROM orders o JOIN payment p ON o.order_id = p.order_id
        WHERE o.payment_status = 'unpaid' AND o.order_type != 'dine_in' AND p.status = 'pending')
    +
    (SELECT COUNT(*) FROM orders
        WHERE payment_status = 'unpaid' AND order_type = 'dine_in' AND order_status IN ('ready', 'served'))
    AS count");
$pay_stmt->execute();
$pay_result = $pay_stmt->get_result();
$payments_count = ($pay_result->num_rows > 0) ? $pay_result->fetch_assoc()['count'] : 0;
$pay_stmt->close();

echo json_encode(['pending_count' => $pending_count, 'payments_count' => $payments_count]);
?>
