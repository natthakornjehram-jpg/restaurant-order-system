<?php
// owner/api_check_update.php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';

// ตั้งค่า Header ให้ส่งข้อมูลกลับเป็น JSON
header('Content-Type: application/json');

// เนื่องจากเป็นร้านเดี่ยว ทุกออเดอร์คือของร้านนี้อยู่แล้ว
// ดึงจำนวนออเดอร์ที่มีสถานะ 'pending' จากตาราง orders ได้ตรงๆ เลย
// (ไม่นับออเดอร์กลับบ้านที่ยังไม่ผ่านการตรวจสลิป ยังไม่ถือเป็นคิวครัวจริง กันแจ้งเตือน/นับซ้ำก่อนเจ้าของร้านยืนยันเงิน)
$order_stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending' AND (order_type = 'dine_in' OR payment_status = 'paid')");
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

// จำนวนคิวที่ยังไม่เสร็จ (pending+cooking) เทียบกับ max_queue ที่ตั้งไว้ - ให้ฝั่ง JS เตือนเจ้าของร้านตอนคิวเต็ม
// (max_queue = 0 หมายถึงไม่จำกัด ไม่ต้องเตือน)
$queue_stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE order_status IN ('pending', 'cooking') AND (order_type = 'dine_in' OR payment_status = 'paid')");
$queue_stmt->execute();
$queue_result = $queue_stmt->get_result();
$active_queue_count = ($queue_result->num_rows > 0) ? $queue_result->fetch_assoc()['count'] : 0;
$queue_stmt->close();

$max_stmt = $conn->prepare("SELECT max_queue FROM owner WHERE owner_id = 1");
$max_stmt->execute();
$max_result = $max_stmt->get_result();
$max_queue = ($max_result->num_rows > 0) ? intval($max_result->fetch_assoc()['max_queue']) : 0;
$max_stmt->close();

// สถานะโต๊ะรวม (ใช้เทียบว่ามีอะไรเปลี่ยนไหม โดยไม่ต้องส่งรายละเอียดทุกโต๊ะมาเทียบทีละตัว) - รวมทั้งจำนวนโต๊ะ
// ทั้งหมด (เผื่อเพิ่ม/ลบโต๊ะ) และจำนวนโต๊ะที่ไม่ว่าง เปลี่ยนได้จากทั้งตอนอนุมัติออเดอร์และตอนเช็คบิลปิดโต๊ะ
$tbl_stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(status != 'available') as busy FROM restauranttable");
$tbl_stmt->execute();
$tbl_row = $tbl_stmt->get_result()->fetch_assoc();
$tables_state = intval($tbl_row['total']) . '-' . intval($tbl_row['busy']);
$tbl_stmt->close();

echo json_encode([
    'pending_count' => $pending_count,
    'payments_count' => $payments_count,
    'active_queue_count' => $active_queue_count,
    'max_queue' => $max_queue,
    'tables_state' => $tables_state,
]);
?>
