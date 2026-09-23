<?php
// owner/api_cancel_order.php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';
require_once '../includes/csrf.php';
require_once '../includes/stock_log.php';

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
    $ord_stmt = $conn->prepare("SELECT order_status, daily_order_no FROM orders WHERE order_id = ? FOR UPDATE");
    $ord_stmt->bind_param("i", $order_id);
    $ord_stmt->execute();
    $ord_row = $ord_stmt->get_result()->fetch_assoc();
    if (!$ord_row || $ord_row['order_status'] !== 'pending') {
        throw new RuntimeException('not_cancelable');
    }
    $source_ref = 'ยกเลิกออเดอร์ #' . str_pad((string) $ord_row['daily_order_no'], 3, '0', STR_PAD_LEFT);

    $stmt_item_info = $conn->prepare("SELECT name, sku, use_stock, stock_pool_id FROM item WHERE item_id = ?");
    $stmt_topping_info = $conn->prepare("SELECT topping_name AS name, sku, use_stock, stock_pool_id FROM topping WHERE topping_id = ?");
    $stmt_stock_item_add = $conn->prepare("UPDATE item SET stock_qty = stock_qty + ? WHERE item_id = ?");
    $stmt_stock_topping_add = $conn->prepare("UPDATE topping SET stock_qty = stock_qty + ? WHERE topping_id = ?");
    $stmt_stock_pool_add = $conn->prepare("UPDATE stock_pool SET stock_qty = stock_qty + ? WHERE pool_id = ?");
    $stmt_qty_item = $conn->prepare("SELECT stock_qty FROM item WHERE item_id = ?");
    $stmt_qty_topping = $conn->prepare("SELECT stock_qty FROM topping WHERE topping_id = ?");
    $stmt_qty_pool = $conn->prepare("SELECT pool_name, sku, stock_qty FROM stock_pool WHERE pool_id = ?");

    // คืนสต็อกกลับเข้าคลัง (ของตัวเองหรือกลุ่มสต็อกร่วมแล้วแต่กรณี) - ตรงข้ามกับ $deduct_stock ใน submit_order.php
    // และบันทึกลง stock_transactions เป็นรายการ "รับเข้า" (qty_change เป็นบวก) ทุกครั้งที่คืนสำเร็จ
    $restore_stock = function ($qty, $info, $item_type, $own_id, $own_stmt, $stmt_qty_own) use ($conn, $stmt_stock_pool_add, $stmt_qty_pool, $source_ref) {
        if (intval($info['use_stock'] ?? 0) !== 1) return; // ไม่ได้ติดตามคลังสินค้าตัวนี้ ไม่ต้องคืนอะไร
        $pool_id = $info['stock_pool_id'] ?? null;

        if (!empty($pool_id)) {
            $stmt_stock_pool_add->bind_param("ii", $qty, $pool_id);
            $stmt_stock_pool_add->execute();

            $stmt_qty_pool->bind_param("i", $pool_id);
            $stmt_qty_pool->execute();
            $pool_row = $stmt_qty_pool->get_result()->fetch_assoc();
            log_stock_transaction($conn, 'pool', (int) $pool_id, $pool_row['sku'] ?? '', $pool_row['pool_name'] ?? '', $qty, (int) ($pool_row['stock_qty'] ?? 0), 'order', $source_ref);
            return;
        }

        $own_stmt->bind_param("ii", $qty, $own_id);
        $own_stmt->execute();

        $stmt_qty_own->bind_param("i", $own_id);
        $stmt_qty_own->execute();
        $qty_after = (int) ($stmt_qty_own->get_result()->fetch_assoc()['stock_qty'] ?? 0);
        log_stock_transaction($conn, $item_type, (int) $own_id, $info['sku'] ?? '', $info['name'] ?? '', $qty, $qty_after, 'order', $source_ref);
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
        $restore_stock($d['quantity'], $ii, 'item', $d['item_id'], $stmt_stock_item_add, $stmt_qty_item);

        $top_stmt->bind_param("i", $d['order_detail_id']);
        $top_stmt->execute();
        $toppings = $top_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($toppings as $t) {
            $stmt_topping_info->bind_param("i", $t['topping_id']);
            $stmt_topping_info->execute();
            $ti = $stmt_topping_info->get_result()->fetch_assoc();
            $restore_stock($d['quantity'], $ti, 'topping', $t['topping_id'], $stmt_stock_topping_add, $stmt_qty_topping);
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
