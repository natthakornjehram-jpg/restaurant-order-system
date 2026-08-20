<?php
// owner/api_approve_payment.php (หรือ payments.php)
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// 1. เช็กสิทธิ์เจ้าของร้าน (ร้านเดี่ยว)
if (!isset($_SESSION['owner_id'])) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
// รับค่า method จากหน้าที่แล้ว (ถ้าไม่มี ส่งค่าเริ่มต้นเป็น transfer)
$method = isset($_POST['method']) ? $_POST['method'] : 'transfer'; 

if (empty($order_id)) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลออเดอร์ไม่ครบถ้วน']);
    exit;
}

// 2. ดึงข้อมูลยอดเงินรวม (total_amount) ของออเดอร์
$stmt = $conn->prepare("SELECT total_amount FROM orders WHERE order_id = ? LIMIT 1");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลออเดอร์ในระบบ']);
    exit;
}

$total_amount = $order['total_amount'];

// เริ่ม Transaction เพื่อป้องกันข้อมูลสูญหายหรืออัปเดตแค่ตารางเดียว
$conn->begin_transaction();

try {
    // 3. อัปเดตสถานะในตาราง orders
    // เปลี่ยน payment_status เป็น 'paid' (จ่ายแล้ว) และเปลี่ยน order_status เป็น 'served' (เสร็จสิ้น/เสิร์ฟแล้ว)
    $update_sql = "UPDATE orders SET order_status = 'served', payment_status = 'paid' WHERE order_id = ?";
    $stmt_update = $conn->prepare($update_sql);
    $stmt_update->bind_param("i", $order_id);
    $stmt_update->execute();

    // 4. จัดการตาราง payment (บันทึกช่องทางการจ่ายเงิน)
    // เช็กก่อนว่ามีบิลในตาราง payment หรือยัง (ลูกค้าออนไลน์แนบสลิปมา จะมีบิลค้างเป็น pending)
    $check_pay = $conn->prepare("SELECT payment_id FROM payment WHERE order_id = ? LIMIT 1");
    $check_pay->bind_param("i", $order_id);
    $check_pay->execute();
    $pay_res = $check_pay->get_result();

    if ($pay_res->num_rows > 0) {
        // ถ้ามีบิลอยู่แล้ว -> อัปเดตสถานะเป็น 'completed' และบันทึกวิธีจ่าย
        $pay_id = $pay_res->fetch_assoc()['payment_id'];
        $update_pay = $conn->prepare("UPDATE payment SET status = 'completed', method = ? WHERE payment_id = ?");
        $update_pay->bind_param("si", $method, $pay_id);
        $update_pay->execute();
    } else {
        // ถ้ายังไม่มีบิล (ลูกค้าหน้าร้านเพิ่งกินเสร็จ) -> สร้างบิลชำระเงินใหม่เลย
        $insert_pay = $conn->prepare("INSERT INTO payment (order_id, amount, method, status) VALUES (?, ?, ?, 'completed')");
        $insert_pay->bind_param("ids", $order_id, $total_amount, $method);
        $insert_pay->execute();
    }

    // ตัดระบบคำนวณและเพิ่มแต้มออกเรียบร้อยแล้วครับ

    // ยืนยันการเปลี่ยนแปลงข้อมูล
    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // ถ้าระหว่างทางมี Error โค้ดจะยกเลิกการบันทึกข้อมูลทั้งหมด (Rollback)
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดของระบบ: ' . $e->getMessage()]);
}

$conn->close();
?>