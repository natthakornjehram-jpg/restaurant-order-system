<?php
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

// 🟢 หากเป็นการอนุมัติเข้าครัว (cooking) ให้อัปเดตสถานะโต๊ะนั้นเป็น 'occupied' พร้อมสุ่มรหัสร่วมโต๊ะ 4 หลักทันที
if ($new_status === 'cooking') {
    $o_stmt = $conn->prepare("SELECT table_id FROM orders WHERE order_id = ?");
    $o_stmt->bind_param("i", $order_id);
    $o_stmt->execute();
    $o_row = $o_stmt->get_result()->fetch_assoc();
    if ($o_row && !empty($o_row['table_id'])) {
        $tbl_id = intval($o_row['table_id']);
        $rand_pin = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $t_stmt = $conn->prepare("UPDATE restauranttable SET status = 'occupied', join_code = COALESCE(NULLIF(join_code, ''), ?) WHERE table_id = ?");
        $t_stmt->bind_param('si', $rand_pin, $tbl_id);
        $t_stmt->execute();
    }
}

echo json_encode(['success' => true]);
