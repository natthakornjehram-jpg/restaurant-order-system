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
$new_status = trim($_POST['new_status'] ?? '');
$allowed = ['cooking', 'served'];

if (!$order_id || !in_array($new_status, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid order status']);
    exit;
}

// A kitchen cannot skip or reverse the normal workflow.
$current_status = ($new_status === 'cooking') ? 'pending' : 'cooking';
$stmt = $conn->prepare(
    'UPDATE orders SET order_status = ? WHERE order_id = ? AND order_status = ?'
);
$stmt->bind_param('sis', $new_status, $order_id, $current_status);
$stmt->execute();

if ($stmt->affected_rows !== 1) {
    echo json_encode(['success' => false, 'error' => 'Order was changed already or cannot be updated']);
    exit;
}

echo json_encode(['success' => true]);
