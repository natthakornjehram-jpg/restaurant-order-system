<?php
// owner/api_update_order_queue.php
// ให้เจ้าของร้านแก้เลขคิว (daily_order_no) ของออเดอร์เองได้ เผื่อเลขที่ระบบนับอัตโนมัติผิดเพี้ยน
// หรืออยากสลับลำดับคิวในครัวเอง (เช่น ลูกค้ารอนานผิดปกติ อยากขยับขึ้นมาก่อน)
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';
require_once '../includes/csrf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$new_queue_no = filter_input(INPUT_POST, 'queue_no', FILTER_VALIDATE_INT);

if (!$order_id || !$new_queue_no || $new_queue_no < 1 || $new_queue_no > 999) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'กรุณากรอกเลขคิวเป็นตัวเลข 1-999']);
    exit;
}

// เช็คว่ามีออเดอร์นี้จริงก่อน (affected_rows จาก UPDATE เป็น 0 ได้ทั้งกรณี "ไม่พบแถว" และ "ค่าเดิมเท่ากับค่าใหม่อยู่แล้ว"
// แยกไม่ออกถ้าเช็คจาก affected_rows อย่างเดียว เลยต้องเช็คการมีอยู่จริงแยกต่างหากก่อน)
$check = $conn->prepare('SELECT order_id FROM orders WHERE order_id = ?');
$check->bind_param('i', $order_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบออเดอร์นี้']);
    exit;
}

$stmt = $conn->prepare('UPDATE orders SET daily_order_no = ? WHERE order_id = ?');
$stmt->bind_param('ii', $new_queue_no, $order_id);
$stmt->execute();

echo json_encode(['success' => true, 'queue_no' => $new_queue_no]);
