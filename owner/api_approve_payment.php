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
if (!$order_id) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid order']);
    exit;
}

$method = $_POST['method'] ?? 'cash';
if (!in_array($method, ['cash', 'transfer', 'qr_counter'], true)) {
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

    $order = $conn->prepare(
        "UPDATE orders 
        SET payment_status = 'paid' 
        WHERE order_id = ?"
    );

    $order->bind_param('i', $order_id);
    $order->execute();

    // 🟢 ถ้าเป็นออเดอร์ที่มี table_id ให้เช็กว่าโต๊ะนี้จ่ายเงินหมดทุกออเดอร์แล้วหรือยัง หากหมดแล้วให้คืนสถานะโต๊ะเป็น 'available' (ว่าง)
    $t_check = $conn->prepare('SELECT table_id FROM orders WHERE order_id = ?');
    $t_check->bind_param('i', $order_id);
    $t_check->execute();
    $t_res = $t_check->get_result()->fetch_assoc();
    if ($t_res && !empty($t_res['table_id'])) {
        $tbl_id = intval($t_res['table_id']);
        // ออเดอร์ที่ถูกปฏิเสธ (canceled) จะค้างเป็น unpaid ตลอดไปเพราะไม่มีทางถูกจ่ายเงิน จึงไม่ควรนับเป็นตัวกันโต๊ะรีเซ็ต
        $unpaid_check = $conn->prepare("SELECT COUNT(*) as unpaid FROM orders WHERE table_id = ? AND payment_status = 'unpaid' AND order_status != 'canceled' AND order_id != ?");
        $unpaid_check->bind_param('ii', $tbl_id, $order_id);
        $unpaid_check->execute();
        $unpaid_count = $unpaid_check->get_result()->fetch_assoc()['unpaid'];
        if (intval($unpaid_count) === 0) {
            $reset_table = $conn->prepare("UPDATE restauranttable SET status = 'available', join_code = NULL WHERE table_id = ?");
            $reset_table->bind_param('i', $tbl_id);
            $reset_table->execute();
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unable to approve payment']);
}
