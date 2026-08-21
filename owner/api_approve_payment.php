<?php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid order']);
    exit;
}

$method = $_POST['method'] ?? 'cash';
if (!in_array($method, ['cash', 'transfer'], true)) {
    $method = 'cash';
}

$conn->begin_transaction();
try {
    $check = $conn->prepare('SELECT total_amount FROM orders WHERE order_id = ? FOR UPDATE');
    $check->bind_param('i', $order_id);
    $check->execute();
    $order_row = $check->get_result()->fetch_assoc();
    if (!$order_row) {
        throw new RuntimeException('Order not found');
    }

    // เช็คก่อนว่ามีบิลในตาราง payment อยู่แล้วหรือยัง
    // (ลูกค้าออนไลน์แนบสลิปมาก่อนแล้ว จะมีแถวรออยู่; ลูกค้าหน้าร้าน/โต๊ะเพิ่งกินเสร็จ ยังไม่เคยมีแถวเลย)
    $pay_check = $conn->prepare('SELECT payment_id FROM payment WHERE order_id = ? LIMIT 1');
    $pay_check->bind_param('i', $order_id);
    $pay_check->execute();
    $pay_row = $pay_check->get_result()->fetch_assoc();

    if ($pay_row) {
        $payment = $conn->prepare("UPDATE payment SET status = 'completed', method = ? WHERE order_id = ?");
        $payment->bind_param('si', $method, $order_id);
        $payment->execute();
    } else {
        $total_amount = $order_row['total_amount'];
        $payment = $conn->prepare("INSERT INTO payment (order_id, amount, method, status) VALUES (?, ?, ?, 'completed')");
        $payment->bind_param('ids', $order_id, $total_amount, $method);
        $payment->execute();
    }

    $order = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE order_id = ?");
    $order->bind_param('i', $order_id);
    $order->execute();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unable to approve payment']);
}
