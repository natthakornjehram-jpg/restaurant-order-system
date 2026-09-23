<?php
// owner/api_cancel_order.php
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
$reason = trim($_POST['reason'] ?? '');

if (!$order_id) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

$conn->begin_transaction();
try {
    // ยกเลิกได้เฉพาะออเดอร์ที่ยังไม่เริ่มปรุง (pending) เท่านั้น กันเสียของที่ครัวทำไปแล้ว
    // ใช้ FOR UPDATE ล็อกแถวกันแอดมินสองคน/สองแท็บกดยกเลิกพร้อมกันแล้วคืนสต็อกซ้ำสอง
    $ord_stmt = $conn->prepare("SELECT order_status FROM orders WHERE order_id = ? FOR UPDATE");
    $ord_stmt->bind_param("i", $order_id);
    $ord_stmt->execute();
    $ord_row = $ord_stmt->get_result()->fetch_assoc();
    if (!$ord_row || $ord_row['order_status'] !== 'pending') {
        throw new RuntimeException('not_cancelable');
    }

    $stmt_item_info = $conn->prepare("SELECT use_stock, stock_pool_id FROM item WHERE item_id = ?");
    $stmt_topping_info = $conn->prepare("SELECT use_stock, stock_pool_id FROM topping WHERE topping_id = ?");
    $stmt_stock_item_add = $conn->prepare("UPDATE item SET stock_qty = stock_qty + ? WHERE item_id = ?");
    $stmt_stock_topping_add = $conn->prepare("UPDATE topping SET stock_qty = stock_qty + ? WHERE topping_id = ?");
    $stmt_stock_pool_add = $conn->prepare("UPDATE stock_pool SET stock_qty = stock_qty + ? WHERE pool_id = ?");

    // คืนสต็อกกลับเข้าคลัง (ของตัวเองหรือกลุ่มสต็อกร่วมแล้วแต่กรณี) - ตรงข้ามกับ $deduct_stock ใน submit_order.php
    $restore_stock = function ($qty, $use_stock, $pool_id, $own_stmt, $own_id) use ($stmt_stock_pool_add) {
        if (intval($use_stock) !== 1) return; // ไม่ได้ติดตามคลังสินค้าตัวนี้ ไม่ต้องคืนอะไร
        if (!empty($pool_id)) {
            $stmt_stock_pool_add->bind_param("ii", $qty, $pool_id);
            $stmt_stock_pool_add->execute();
            return;
        }
        $own_stmt->bind_param("ii", $qty, $own_id);
        $own_stmt->execute();
    };

    $detail_stmt = $conn->prepare("SELECT order_detail_id, item_id, quantity FROM orderdetail WHERE order_id = ?");
    $detail_stmt->bind_param("i", $order_id);
    $detail_stmt->execute();
    $details = $detail_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $top_stmt = $conn->prepare("SELECT topping_id FROM orderdetail_topping WHERE order_detail_id = ?");

    foreach ($details as $d) {
        $stmt_item_info->bind_param("i", $d['item_id']);
        $stmt_item_info->execute();
        $ii = $stmt_item_info->get_result()->fetch_assoc();
        $restore_stock($d['quantity'], $ii['use_stock'] ?? 0, $ii['stock_pool_id'] ?? null, $stmt_stock_item_add, $d['item_id']);

        $top_stmt->bind_param("i", $d['order_detail_id']);
        $top_stmt->execute();
        $toppings = $top_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($toppings as $t) {
            $stmt_topping_info->bind_param("i", $t['topping_id']);
            $stmt_topping_info->execute();
            $ti = $stmt_topping_info->get_result()->fetch_assoc();
            $restore_stock($d['quantity'], $ti['use_stock'] ?? 0, $ti['stock_pool_id'] ?? null, $stmt_stock_topping_add, $t['topping_id']);
        }
    }

    $cancel_stmt = $conn->prepare("UPDATE orders SET order_status = 'canceled', cancel_reason = ? WHERE order_id = ?");
    $cancel_stmt->bind_param("si", $reason, $order_id);
    $cancel_stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    $msg = ($e->getMessage() === 'not_cancelable')
        ? 'ยกเลิกได้เฉพาะออเดอร์ที่ยังไม่เริ่มปรุงเท่านั้น'
        : 'เกิดข้อผิดพลาด ไม่สามารถยกเลิกออเดอร์ได้';
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $msg]);
}
