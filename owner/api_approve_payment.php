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

$conn->begin_transaction();
try {
    $check = $conn->prepare('SELECT order_id FROM orders WHERE order_id = ? FOR UPDATE');
    $check->bind_param('i', $order_id);
    $check->execute();
    if (!$check->get_result()->fetch_assoc()) {
        throw new RuntimeException('Order not found');
    }

    $payment = $conn->prepare("UPDATE payment SET status = 'completed' WHERE order_id = ?");
    $payment->bind_param('i', $order_id);
    $payment->execute();

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
