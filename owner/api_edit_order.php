<?php
// owner/api_edit_order.php
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
$lines_raw = json_decode($_POST['lines'] ?? '[]', true);

if (!$order_id || !is_array($lines_raw) || empty($lines_raw)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

$conn->begin_transaction();
try {
    // แก้ไขได้เฉพาะออเดอร์ที่ยังไม่เริ่มปรุง (pending) เท่านั้น กันแก้ทีหลังจากครัวเริ่มทำไปแล้ว
    $ord_stmt = $conn->prepare("SELECT order_status, daily_order_no FROM orders WHERE order_id = ? FOR UPDATE");
    $ord_stmt->bind_param("i", $order_id);
    $ord_stmt->execute();
    $ord_row = $ord_stmt->get_result()->fetch_assoc();
    if (!$ord_row || $ord_row['order_status'] !== 'pending') {
        throw new RuntimeException('not_editable');
    }
    $source_ref = 'แก้ไขออเดอร์ #' . str_pad((string) $ord_row['daily_order_no'], 3, '0', STR_PAD_LEFT);

    $stmt_item_info = $conn->prepare("SELECT name, sku, use_stock, stock_pool_id FROM item WHERE item_id = ?");
    $stmt_topping_info = $conn->prepare("SELECT topping_name AS name, sku, use_stock, stock_pool_id FROM topping WHERE topping_id = ?");
    $stmt_stock_item_add = $conn->prepare("UPDATE item SET stock_qty = stock_qty + ? WHERE item_id = ?");
    $stmt_stock_topping_add = $conn->prepare("UPDATE topping SET stock_qty = stock_qty + ? WHERE topping_id = ?");
    $stmt_stock_pool_add = $conn->prepare("UPDATE stock_pool SET stock_qty = stock_qty + ? WHERE pool_id = ?");
    $stmt_stock_item_sub = $conn->prepare("UPDATE item SET stock_qty = stock_qty - ? WHERE item_id = ? AND stock_qty >= ?");
    $stmt_stock_topping_sub = $conn->prepare("UPDATE topping SET stock_qty = stock_qty - ? WHERE topping_id = ? AND stock_qty >= ?");
    $stmt_stock_pool_sub = $conn->prepare("UPDATE stock_pool SET stock_qty = stock_qty - ? WHERE pool_id = ? AND stock_qty >= ?");
    $stmt_qty_item = $conn->prepare("SELECT stock_qty FROM item WHERE item_id = ?");
    $stmt_qty_topping = $conn->prepare("SELECT stock_qty FROM topping WHERE topping_id = ?");
    $stmt_qty_pool = $conn->prepare("SELECT pool_name, sku, stock_qty FROM stock_pool WHERE pool_id = ?");

    // ปรับสต็อกตามส่วนต่างจำนวน (delta) - delta บวก = สั่งเพิ่มต้องตัดสต็อกเพิ่ม (เช็คของพอไหมด้วย)
    // delta ลบ = ลดจำนวน/ลบรายการ ต้องคืนสต็อกกลับเข้าคลัง (คืนได้เสมอ ไม่ต้องเช็คเงื่อนไข)
    // ทุกครั้งที่ปรับสำเร็จ บันทึกลง stock_transactions ด้วย (qty_change ตรงกับทิศทางที่ปรับจริง)
    $adjust_stock = function ($delta, $info, $item_type, $add_stmt, $sub_stmt, $own_id, $stmt_qty_own) use ($conn, $stmt_stock_pool_add, $stmt_stock_pool_sub, $stmt_qty_pool, $source_ref) {
        if (intval($info['use_stock'] ?? 0) !== 1 || $delta === 0) return;
        $pool_id = $info['stock_pool_id'] ?? null;
        $sku = $info['sku'] ?? '';
        $name = $info['name'] ?? '';

        if ($delta > 0) {
            if (!empty($pool_id)) {
                $stmt_stock_pool_sub->bind_param("iii", $delta, $pool_id, $delta);
                $stmt_stock_pool_sub->execute();
                if ($stmt_stock_pool_sub->affected_rows === 0) throw new RuntimeException('stock_insufficient');
                $stmt_qty_pool->bind_param("i", $pool_id);
                $stmt_qty_pool->execute();
                $pool_row = $stmt_qty_pool->get_result()->fetch_assoc();
                log_stock_transaction($conn, 'pool', (int) $pool_id, $pool_row['sku'] ?? '', $pool_row['pool_name'] ?? '', -$delta, (int) ($pool_row['stock_qty'] ?? 0), 'order', $source_ref);
                return;
            }
            $sub_stmt->bind_param("iii", $delta, $own_id, $delta);
            $sub_stmt->execute();
            if ($sub_stmt->affected_rows === 0) throw new RuntimeException('stock_insufficient');
            $stmt_qty_own->bind_param("i", $own_id);
            $stmt_qty_own->execute();
            $qty_after = (int) ($stmt_qty_own->get_result()->fetch_assoc()['stock_qty'] ?? 0);
            log_stock_transaction($conn, $item_type, (int) $own_id, $sku, $name, -$delta, $qty_after, 'order', $source_ref);
            return;
        }

        $restore = abs($delta);
        if (!empty($pool_id)) {
            $stmt_stock_pool_add->bind_param("ii", $restore, $pool_id);
            $stmt_stock_pool_add->execute();
            $stmt_qty_pool->bind_param("i", $pool_id);
            $stmt_qty_pool->execute();
            $pool_row = $stmt_qty_pool->get_result()->fetch_assoc();
            log_stock_transaction($conn, 'pool', (int) $pool_id, $pool_row['sku'] ?? '', $pool_row['pool_name'] ?? '', $restore, (int) ($pool_row['stock_qty'] ?? 0), 'order', $source_ref);
            return;
        }
        $add_stmt->bind_param("ii", $restore, $own_id);
        $add_stmt->execute();
        $stmt_qty_own->bind_param("i", $own_id);
        $stmt_qty_own->execute();
        $qty_after = (int) ($stmt_qty_own->get_result()->fetch_assoc()['stock_qty'] ?? 0);
        log_stock_transaction($conn, $item_type, (int) $own_id, $sku, $name, $restore, $qty_after, 'order', $source_ref);
    };

    $stmt_get_detail = $conn->prepare("SELECT item_id, quantity FROM orderdetail WHERE order_detail_id = ? AND order_id = ?");
    $stmt_get_toppings = $conn->prepare("SELECT topping_id FROM orderdetail_topping WHERE order_detail_id = ?");
    $stmt_upd_qty = $conn->prepare("UPDATE orderdetail SET quantity = ? WHERE order_detail_id = ?");
    $stmt_del_top = $conn->prepare("DELETE FROM orderdetail_topping WHERE order_detail_id = ?");
    $stmt_del_detail = $conn->prepare("DELETE FROM orderdetail WHERE order_detail_id = ?");

    foreach ($lines_raw as $line) {
        $detail_id = intval($line['detail_id'] ?? 0);
        $new_qty = intval($line['quantity'] ?? -1);
        if ($detail_id <= 0 || $new_qty < 0) continue;

        $stmt_get_detail->bind_param("ii", $detail_id, $order_id);
        $stmt_get_detail->execute();
        $detail_row = $stmt_get_detail->get_result()->fetch_assoc();
        if (!$detail_row) continue; // ไม่ใช่รายการของออเดอร์นี้ (ผิดปกติ/ถูกลบไปแล้ว) ข้าม

        $old_qty = intval($detail_row['quantity']);
        $item_id = intval($detail_row['item_id']);
        if ($new_qty === $old_qty) continue; // ไม่มีอะไรเปลี่ยน

        $stmt_get_toppings->bind_param("i", $detail_id);
        $stmt_get_toppings->execute();
        $topping_ids = array_column($stmt_get_toppings->get_result()->fetch_all(MYSQLI_ASSOC), 'topping_id');

        if ($new_qty === 0) {
            // ลบรายการนี้ทิ้งทั้งหมด คืนสต็อกเต็มจำนวนเดิม (เท่ากับ delta = -$old_qty)
            $stmt_item_info->bind_param("i", $item_id);
            $stmt_item_info->execute();
            $ii = $stmt_item_info->get_result()->fetch_assoc();
            $adjust_stock(-$old_qty, $ii, 'item', $stmt_stock_item_add, $stmt_stock_item_sub, $item_id, $stmt_qty_item);

            foreach ($topping_ids as $tid) {
                $stmt_topping_info->bind_param("i", $tid);
                $stmt_topping_info->execute();
                $ti = $stmt_topping_info->get_result()->fetch_assoc();
                $adjust_stock(-$old_qty, $ti, 'topping', $stmt_stock_topping_add, $stmt_stock_topping_sub, $tid, $stmt_qty_topping);
            }

            $stmt_del_top->bind_param("i", $detail_id);
            $stmt_del_top->execute();
            $stmt_del_detail->bind_param("i", $detail_id);
            $stmt_del_detail->execute();
        } else {
            $delta = $new_qty - $old_qty;

            $stmt_item_info->bind_param("i", $item_id);
            $stmt_item_info->execute();
            $ii = $stmt_item_info->get_result()->fetch_assoc();
            $adjust_stock($delta, $ii, 'item', $stmt_stock_item_add, $stmt_stock_item_sub, $item_id, $stmt_qty_item);

            foreach ($topping_ids as $tid) {
                $stmt_topping_info->bind_param("i", $tid);
                $stmt_topping_info->execute();
                $ti = $stmt_topping_info->get_result()->fetch_assoc();
                $adjust_stock($delta, $ti, 'topping', $stmt_stock_topping_add, $stmt_stock_topping_sub, $tid, $stmt_qty_topping);
            }

            $stmt_upd_qty->bind_param("ii", $new_qty, $detail_id);
            $stmt_upd_qty->execute();
        }
    }

    // เช็คว่ายังเหลือรายการอาหารในออเดอร์นี้ไหม ถ้าลบจนหมดแล้วให้ถือว่ายกเลิกออเดอร์ทั้งใบไปด้วยเลย
    // (ไม่ปล่อยบิลว่างเปล่าค้างอยู่ในคิว - หน้า manage_orders.php ไม่รองรับออเดอร์ที่ไม่มีรายการอาหารเลย)
    $left_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM orderdetail WHERE order_id = ?");
    $left_stmt->bind_param("i", $order_id);
    $left_stmt->execute();
    $left_count = intval($left_stmt->get_result()->fetch_assoc()['c']);

    if ($left_count === 0) {
        $auto_reason = 'ลบรายการอาหารออกจนหมด (ยกเลิกอัตโนมัติ)';
        $cancel_stmt = $conn->prepare("UPDATE orders SET order_status = 'canceled', cancel_reason = ? WHERE order_id = ?");
        $cancel_stmt->bind_param("si", $auto_reason, $order_id);
        $cancel_stmt->execute();
    } else {
        $sum_stmt = $conn->prepare("SELECT SUM(quantity * unit_price) AS total FROM orderdetail WHERE order_id = ?");
        $sum_stmt->bind_param("i", $order_id);
        $sum_stmt->execute();
        $new_total = floatval($sum_stmt->get_result()->fetch_assoc()['total'] ?? 0);

        $upd_total = $conn->prepare("UPDATE orders SET total_amount = ? WHERE order_id = ?");
        $upd_total->bind_param("di", $new_total, $order_id);
        $upd_total->execute();

        // ออเดอร์กลับบ้านมีแถว payment ผูกยอดอยู่ด้วย ต้องอัปเดตให้ตรงกัน ไม่งั้นหน้าจัดการชำระเงินจะโชว์ยอดเก่าค้างไว้
        $upd_pay = $conn->prepare("UPDATE payment SET amount = ? WHERE order_id = ?");
        $upd_pay->bind_param("di", $new_total, $order_id);
        $upd_pay->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    $msg = 'เกิดข้อผิดพลาด ไม่สามารถบันทึกการแก้ไขได้';
    if ($e->getMessage() === 'stock_insufficient') {
        $msg = 'วัตถุดิบในคลังไม่พอสำหรับจำนวนที่เพิ่มขึ้น';
    } elseif ($e->getMessage() === 'not_editable') {
        $msg = 'แก้ไขได้เฉพาะออเดอร์ที่ยังไม่เริ่มปรุงเท่านั้น';
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $msg]);
}
