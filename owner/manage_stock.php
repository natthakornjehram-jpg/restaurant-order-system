<?php 
// owner/manage_stock.php
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';
require_once '../includes/csrf.php';
require_once '../includes/stock_log.php';

$msg = "";

// 🟢 อัปเดตคลังสินค้ารายชิ้น หรือ ท็อปปิ้ง ผ่าน AJAX หรือ Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $is_ajax_stock = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        if ($is_ajax_stock) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        header("Location: manage_stock.php");
        exit;
    }

    if ($action === 'quick_adjust') {
        $item_id = intval($_POST['item_id']);
        $change = intval($_POST['change']);

        // ดึงจำนวนก่อนปรับไว้ด้วย เพราะ GREATEST(0, ...) ด้านล่างอาจหักลบได้ไม่ครบตามที่ขอ (เช่น เหลือ 3 กด "-10"
        // จะลบได้จริงแค่ 3) ต้องคำนวณส่วนต่างจริงจากเลขก่อน/หลัง ไม่ใช่เชื่อค่า $change ตรงๆ ตอนบันทึกลง log
        $before_stmt = $conn->prepare("SELECT name, sku, stock_qty FROM item WHERE item_id = ?");
        $before_stmt->bind_param("i", $item_id);
        $before_stmt->execute();
        $before_row = $before_stmt->get_result()->fetch_assoc();

        $stmt = $conn->prepare("UPDATE item SET stock_qty = GREATEST(0, stock_qty + ?) WHERE item_id = ?");
        $stmt->bind_param("ii", $change, $item_id);
        $stmt->execute();

        $qty_stmt = $conn->prepare("SELECT stock_qty FROM item WHERE item_id = ?");
        $qty_stmt->bind_param("i", $item_id);
        $qty_stmt->execute();
        $new_qty = (int) ($qty_stmt->get_result()->fetch_assoc()['stock_qty'] ?? 0);
        $actual_change = $new_qty - (int) ($before_row['stock_qty'] ?? 0);
        if ($actual_change !== 0) {
            log_stock_transaction($conn, 'item', $item_id, $before_row['sku'] ?? '', $before_row['name'] ?? '', $actual_change, $new_qty, 'manual', null, 'ปรับจำนวนด้วยตนเองในหน้าจัดการคลังสินค้า');
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'new_qty' => $new_qty]);
            exit;
        }
    } elseif ($action === 'quick_adjust_topping') {
        $topping_id = intval($_POST['topping_id']);
        $change = intval($_POST['change']);

        $before_stmt = $conn->prepare("SELECT topping_name AS name, sku, stock_qty FROM topping WHERE topping_id = ?");
        $before_stmt->bind_param("i", $topping_id);
        $before_stmt->execute();
        $before_row = $before_stmt->get_result()->fetch_assoc();

        $stmt = $conn->prepare("UPDATE topping SET stock_qty = GREATEST(0, stock_qty + ?) WHERE topping_id = ?");
        $stmt->bind_param("ii", $change, $topping_id);
        $stmt->execute();

        $qty_stmt = $conn->prepare("SELECT stock_qty FROM topping WHERE topping_id = ?");
        $qty_stmt->bind_param("i", $topping_id);
        $qty_stmt->execute();
        $new_qty = (int) ($qty_stmt->get_result()->fetch_assoc()['stock_qty'] ?? 0);
        $actual_change = $new_qty - (int) ($before_row['stock_qty'] ?? 0);
        if ($actual_change !== 0) {
            log_stock_transaction($conn, 'topping', $topping_id, $before_row['sku'] ?? '', $before_row['name'] ?? '', $actual_change, $new_qty, 'manual', null, 'ปรับจำนวนด้วยตนเองในหน้าจัดการคลังสินค้า');
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'new_qty' => $new_qty]);
            exit;
        }
    } elseif ($action === 'toggle_topping') {
        $topping_id = intval($_POST['topping_id']);
        $current_status = intval($_POST['current_status']);
        $new_status = ($current_status == 1) ? 0 : 1;

        $stmt = $conn->prepare("UPDATE topping SET is_active = ? WHERE topping_id = ?");
        $stmt->bind_param("ii", $new_status, $topping_id);
        $stmt->execute();

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'new_status' => $new_status]);
            exit;
        }
        $msg = "อัปเดตสถานะท็อปปิ้งเรียบร้อยแล้ว";
    } elseif ($action === 'add_topping') {
        // เพิ่มท็อปปิ้ง/วัตถุดิบใหม่โดยตรงจากหน้าคลังสินค้า (ตั้งค่าติดตามคลังสินค้าเป็นเปิดเสมอ เพราะหน้านี้มีไว้จัดการของที่ต้องนับคลังสินค้าเท่านั้น)
        header('Content-Type: application/json');
        $name = trim($_POST['topping_name'] ?? '');
        $cat_id = intval($_POST['topping_cat_id'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $stock_qty = max(0, intval($_POST['stock_qty'] ?? 50));

        if ($name === '' || $cat_id <= 0 || $price < 0) {
            echo json_encode(['success' => false, 'error' => 'กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO topping (topping_name, topping_cat_id, price, use_stock, stock_qty, is_active) VALUES (?, ?, ?, 1, ?, 1)");
        $stmt->bind_param("sidi", $name, $cat_id, $price, $stock_qty);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล']);
        }
        exit;
    } elseif ($action === 'save_pool') {
        // สร้าง/แก้ไข "กลุ่มสต็อกร่วม" - ให้หลายเมนู/ท็อปปิ้งที่ใช้วัตถุดิบตัวเดียวกันจริง หักสต็อกจากกองเดียวกัน
        // (ดูเหตุผลที่ includes/db.php) pool_id > 0 คือแก้ไขของเดิม, = 0 คือสร้างใหม่
        header('Content-Type: application/json');
        $pool_id = intval($_POST['pool_id'] ?? 0);
        $pool_name = trim($_POST['pool_name'] ?? '');
        $pool_category = trim($_POST['pool_category'] ?? '') ?: null; // ว่าง = ไม่มีหมวดหมู่ (จัดกลุ่มรวมท้ายสุด)
        $pool_qty = max(0, intval($_POST['stock_qty'] ?? 0));

        if ($pool_name === '') {
            echo json_encode(['success' => false, 'error' => 'กรุณากรอกชื่อกลุ่มสต็อก']);
            exit;
        }

        if ($pool_id > 0) {
            $stmt = $conn->prepare("UPDATE stock_pool SET pool_name = ?, pool_category = ?, stock_qty = ? WHERE pool_id = ?");
            $stmt->bind_param("ssii", $pool_name, $pool_category, $pool_qty, $pool_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO stock_pool (pool_name, pool_category, stock_qty) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $pool_name, $pool_category, $pool_qty);
        }

        // ไม่ต้อง render การ์ดกลับมาเองแล้ว (เดิมทำแบบนั้นตอนยังไม่มีหมวดหมู่) เพราะตอนนี้กลุ่มอาจย้ายไปอยู่คนละ
        // หมวดหมู่กับที่โชว์อยู่บนจอ ให้ฝั่ง JS สั่ง soft-refresh ทั้งแท็บแทน จะได้จัดกลุ่มใหม่ถูกต้องเสมอ
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'pool_id' => $pool_id > 0 ? $pool_id : $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล']);
        }
        exit;
    } elseif ($action === 'delete_pool') {
        // กันลบกลุ่มที่ยังมีเมนู/ท็อปปิ้งผูกอยู่ (เหมือนตอนลบตัวเลือกเสริมที่เคยถูกใช้ในคำสั่งซื้อแล้ว)
        // เพื่อไม่ให้เมนูที่ผูกไว้เหลือ stock_pool_id ชี้ไปยังกลุ่มที่ไม่มีอยู่แล้วโดยไม่ตั้งใจ
        header('Content-Type: application/json');
        $pool_id = intval($_POST['pool_id'] ?? 0);

        $check_item = $conn->prepare("SELECT COUNT(*) AS c FROM item WHERE stock_pool_id = ?");
        $check_item->bind_param("i", $pool_id);
        $check_item->execute();
        $used_item = (int) $check_item->get_result()->fetch_assoc()['c'];

        $check_top = $conn->prepare("SELECT COUNT(*) AS c FROM topping WHERE stock_pool_id = ?");
        $check_top->bind_param("i", $pool_id);
        $check_top->execute();
        $used_top = (int) $check_top->get_result()->fetch_assoc()['c'];

        if (($used_item + $used_top) > 0) {
            echo json_encode(['success' => false, 'error' => 'ลบไม่ได้ เพราะยังมีเมนู/ท็อปปิ้งผูกกับกลุ่มนี้อยู่ กรุณายกเลิกการผูกก่อน']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM stock_pool WHERE pool_id = ?");
        $stmt->bind_param("i", $pool_id);
        echo json_encode(['success' => $stmt->execute()]);
        exit;
    } elseif ($action === 'quick_adjust_pool') {
        $pool_id = intval($_POST['pool_id']);
        $change = intval($_POST['change']);

        $before_stmt = $conn->prepare("SELECT pool_name, sku, stock_qty FROM stock_pool WHERE pool_id = ?");
        $before_stmt->bind_param("i", $pool_id);
        $before_stmt->execute();
        $before_row = $before_stmt->get_result()->fetch_assoc();

        $stmt = $conn->prepare("UPDATE stock_pool SET stock_qty = GREATEST(0, stock_qty + ?) WHERE pool_id = ?");
        $stmt->bind_param("ii", $change, $pool_id);
        $stmt->execute();

        $qty_stmt = $conn->prepare("SELECT stock_qty FROM stock_pool WHERE pool_id = ?");
        $qty_stmt->bind_param("i", $pool_id);
        $qty_stmt->execute();
        $new_qty = (int) ($qty_stmt->get_result()->fetch_assoc()['stock_qty'] ?? 0);
        $actual_change = $new_qty - (int) ($before_row['stock_qty'] ?? 0);
        if ($actual_change !== 0) {
            log_stock_transaction($conn, 'pool', $pool_id, $before_row['sku'] ?? '', $before_row['pool_name'] ?? '', $actual_change, $new_qty, 'manual', null, 'ปรับจำนวนด้วยตนเองในหน้าจัดการคลังสินค้า');
        }

        if ($is_ajax_stock) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'new_qty' => $new_qty]);
            exit;
        }
    } elseif ($action === 'link_item_pool') {
        // ผูก/ยกเลิกผูกเมนูกับกลุ่มสต็อกร่วม (pool_id = 0 หมายถึงยกเลิกผูก กลับไปนับสต็อกของตัวเองตามปกติ)
        header('Content-Type: application/json');
        $item_id = intval($_POST['item_id'] ?? 0);
        $pool_id = intval($_POST['pool_id'] ?? 0);
        $pool_val = $pool_id > 0 ? $pool_id : null;

        $stmt = $conn->prepare("UPDATE item SET stock_pool_id = ? WHERE item_id = ?");
        $stmt->bind_param("ii", $pool_val, $item_id);
        $ok = $stmt->execute();

        // ส่งข้อมูลล่าสุดกลับไปด้วย ให้ฝั่ง JS อัปเดตหน้าการ์ดในตัวได้เลยโดยไม่ต้องรีโหลดหน้าทั้งหน้า
        $resp = ['success' => $ok, 'is_pooled' => $pool_val !== null];
        if ($ok && $pool_val !== null) {
            $p_stmt = $conn->prepare("SELECT pool_name, stock_qty FROM stock_pool WHERE pool_id = ?");
            $p_stmt->bind_param("i", $pool_val);
            $p_stmt->execute();
            $p_row = $p_stmt->get_result()->fetch_assoc();
            $resp['pool_name'] = $p_row['pool_name'] ?? '';
            $resp['qty'] = (int) ($p_row['stock_qty'] ?? 0);
        } elseif ($ok) {
            $q_stmt = $conn->prepare("SELECT stock_qty FROM item WHERE item_id = ?");
            $q_stmt->bind_param("i", $item_id);
            $q_stmt->execute();
            $resp['qty'] = (int) ($q_stmt->get_result()->fetch_assoc()['stock_qty'] ?? 0);
        }
        echo json_encode($resp);
        exit;
    } elseif ($action === 'link_topping_pool') {
        header('Content-Type: application/json');
        $topping_id = intval($_POST['topping_id'] ?? 0);
        $pool_id = intval($_POST['pool_id'] ?? 0);
        $pool_val = $pool_id > 0 ? $pool_id : null;

        $stmt = $conn->prepare("UPDATE topping SET stock_pool_id = ? WHERE topping_id = ?");
        $stmt->bind_param("ii", $pool_val, $topping_id);
        $ok = $stmt->execute();

        $resp = ['success' => $ok, 'is_pooled' => $pool_val !== null];
        if ($ok && $pool_val !== null) {
            $p_stmt = $conn->prepare("SELECT pool_name, stock_qty FROM stock_pool WHERE pool_id = ?");
            $p_stmt->bind_param("i", $pool_val);
            $p_stmt->execute();
            $p_row = $p_stmt->get_result()->fetch_assoc();
            $resp['pool_name'] = $p_row['pool_name'] ?? '';
            $resp['qty'] = (int) ($p_row['stock_qty'] ?? 0);
        } elseif ($ok) {
            $q_stmt = $conn->prepare("SELECT stock_qty FROM topping WHERE topping_id = ?");
            $q_stmt->bind_param("i", $topping_id);
            $q_stmt->execute();
            $resp['qty'] = (int) ($q_stmt->get_result()->fetch_assoc()['stock_qty'] ?? 0);
        }
        echo json_encode($resp);
        exit;
    } elseif ($action === 'save_product_meta') {
        // แก้ SKU/จุดสั่งซื้อซ้ำ จากแท็บ "รายการสินค้า" (ใช้ร่วมกันทั้งเมนู/ท็อปปิ้ง/กลุ่มสต็อกร่วม)
        header('Content-Type: application/json');
        $item_type = $_POST['item_type'] ?? '';
        $item_id = intval($_POST['item_id'] ?? 0);
        $sku = trim($_POST['sku'] ?? '');
        $reorder_point = max(0, intval($_POST['reorder_point'] ?? 0));
        $sku = $sku !== '' ? $sku : null;

        $table_map = ['item' => ['item', 'item_id'], 'topping' => ['topping', 'topping_id'], 'pool' => ['stock_pool', 'pool_id']];
        if (!isset($table_map[$item_type]) || $item_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']);
            exit;
        }
        [$table, $pk] = $table_map[$item_type];

        // เช็ก SKU ซ้ำเอง (คนละข้อความจาก MySQL unique key error ให้เข้าใจง่ายกว่า)
        if ($sku !== null) {
            $dup_stmt = $conn->prepare("SELECT 1 FROM item WHERE sku = ? AND item_id != ? UNION SELECT 1 FROM topping WHERE sku = ? AND topping_id != ? UNION SELECT 1 FROM stock_pool WHERE sku = ? AND pool_id != ?");
            $dummy_item_id = $item_type === 'item' ? $item_id : 0;
            $dummy_topping_id = $item_type === 'topping' ? $item_id : 0;
            $dummy_pool_id = $item_type === 'pool' ? $item_id : 0;
            $dup_stmt->bind_param("sisisi", $sku, $dummy_item_id, $sku, $dummy_topping_id, $sku, $dummy_pool_id);
            $dup_stmt->execute();
            if ($dup_stmt->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'error' => 'SKU นี้ถูกใช้ไปแล้ว กรุณาตั้งชื่ออื่น']);
                exit;
            }
        }

        $stmt = $conn->prepare("UPDATE `$table` SET sku = ?, reorder_point = ? WHERE `$pk` = ?");
        $stmt->bind_param("sii", $sku, $reorder_point, $item_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด ไม่สามารถบันทึกได้']);
        }
        exit;
    }
}

// นับจำนวนของหมด/ใกล้หมดในแต่ละหมวด ไว้โชว์เป็น badge เตือนบนหัวข้อหมวดที่พับเก็บอยู่
// จะได้ไม่ต้องกางทุกหมวดออกมาดูก็ยังรู้ว่าหมวดไหนต้องรีบเข้าไปดู
function stock_count_alerts_items($rows) {
    $out = 0; $low = 0;
    foreach ($rows as $m) {
        if (empty($m['use_stock'])) continue;
        // เมนูที่ผูกกับกลุ่มสต็อกร่วมไว้ ให้นับตามจำนวนคงเหลือของกลุ่มแทนของตัวเอง
        $q = !empty($m['stock_pool_id']) ? (int) $m['pool_stock_qty'] : (int) $m['stock_qty'];
        if ($q <= 0) $out++;
        elseif ($q <= 5) $low++;
    }
    return [$out, $low];
}

function stock_count_alerts_toppings($rows) {
    $out = 0; $low = 0;
    foreach ($rows as $t) {
        $q = !empty($t['stock_pool_id']) ? (int) $t['pool_stock_qty'] : (int) $t['stock_qty'];
        if (empty($t['is_active']) || $q <= 0) $out++;
        elseif ($q <= 5) $low++;
    }
    return [$out, $low];
}

// เมนูเลือกกลุ่มสต็อกร่วม (ใช้ซ้ำทั้งการ์ดเมนูและการ์ดท็อปปิ้ง) - $current_pool_id = 0/NULL คือยังไม่ผูก
function render_pool_link_select($current_pool_id, $all_pools, $onchange_js) {
    if (empty($all_pools)) return; // ยังไม่มีกลุ่มสต็อกร่วมในระบบเลย ไม่ต้องโชว์ dropdown เปล่าๆ
    ?>
    <div class="mt-2 text-start">
        <label class="small text-muted mb-1 d-block"><i class="bi bi-link-45deg"></i> กลุ่มสต็อกร่วม</label>
        <select class="form-select form-select-sm rounded-3" onchange="<?= $onchange_js ?>">
            <option value="">— ไม่ผูก (นับของตัวเอง) —</option>
            <?php foreach ($all_pools as $p): ?>
                <option value="<?= $p['pool_id'] ?>" <?= (intval($current_pool_id) === intval($p['pool_id'])) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['pool_name']) ?> (คงเหลือ <?= (int) $p['stock_qty'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
}

// การ์ดเมนูอาหาร 1 ใบ (แยกเป็นฟังก์ชันเพื่อเรียกซ้ำได้ทั้งตอนจัดกลุ่มตามหมวดหมู่ และกลุ่ม "ไม่มีหมวดหมู่")
function render_stock_item_card($m, $all_pools = []) {
    $m_id = $m['item_id'];
    $use_stock = $m['use_stock'];
    $is_pooled = !empty($m['stock_pool_id']);
    // เมนูที่ผูกกับกลุ่มสต็อกร่วมไว้ ให้แสดง/อิงจำนวนคงเหลือของกลุ่มแทนของตัวเอง (ดูเหตุผลที่ includes/db.php)
    $stock = $is_pooled ? intval($m['pool_stock_qty']) : intval($m['stock_qty']);
    $img_path = !empty($m['image_url']) ? "../assets/images/items/" . $m['image_url'] : "../assets/images/items/default_food.jpg";

    $stock_badge = "bg-success";
    $stock_text = "มีของพอใช้";
    if ($use_stock && $stock <= 0) {
        $stock_badge = "bg-danger";
        $stock_text = "ของหมด!";
    } elseif ($use_stock && $stock <= 5) {
        $stock_badge = "bg-warning text-dark";
        $stock_text = "ใกล้หมด";
    }
    ?>
    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
        <div class="card stock-card p-3 h-100 bg-white">
            <div class="d-flex align-items-center mb-3">
                <img src="<?= htmlspecialchars($img_path) ?>" class="rounded-4 me-3 shadow-sm" style="width: 75px; height: 75px; object-fit: cover;" onerror="this.src='../assets/images/items/default_food.jpg'">
                <div class="flex-grow-1 overflow-hidden">
                    <h6 class="fw-bold mb-1 text-truncate"><?= htmlspecialchars($m['name']) ?></h6>
                    <div class="fw-bold text-success">฿<?= number_format($m['price'], 0) ?></div>
                </div>
                <button type="button" id="star-btn-<?= $m_id ?>"
                        class="star-btn <?= (!empty($m['is_featured'])) ? 'star-active' : '' ?>"
                        onclick="toggleFeatured(<?= $m_id ?>, <?= (!empty($m['is_featured'])) ? 0 : 1 ?>)"
                        title="<?= (!empty($m['is_featured'])) ? 'เมนูแนะนำ (กดเพื่อยกเลิก)' : 'ติดดาวเป็นเมนูแนะนำ' ?>">★</button>
            </div>

            <div class="bg-light rounded-4 p-3 mb-3 text-center">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-muted fw-bold">คงเหลือในคลังสินค้า</span>
                    <span class="badge rounded-pill <?= $stock_badge ?> px-3 py-1" id="item_badge_<?= $m_id ?>"><?= $stock_text ?></span>
                </div>

                <div id="item_pooled_box_<?= $m_id ?>" style="<?= $is_pooled ? '' : 'display:none;' ?>">
                    <div class="small text-primary fw-bold mb-1"><i class="bi bi-link-45deg"></i> ใช้ร่วมกับกลุ่ม "<span id="item_pool_name_<?= $m_id ?>"><?= htmlspecialchars($m['pool_name'] ?? '') ?></span>"</div>
                    <div class="h2 fw-bold m-0" id="item_pool_qty_<?= $m_id ?>"><?= $is_pooled ? $stock : 0 ?></div>
                    <div class="form-text mb-0">ปรับจำนวนได้ที่แท็บ "กลุ่มสต็อกร่วม"</div>
                </div>
                <div id="item_own_box_<?= $m_id ?>" style="<?= $is_pooled ? 'display:none;' : '' ?>">
                    <div class="d-flex align-items-center justify-content-center gap-3 my-2">
                        <button type="button" class="btn btn-outline-danger btn-qty shadow-sm" onclick="adjustStock(<?= $m_id ?>, -1)">-</button>
                        <span class="h2 fw-bold m-0" id="stock_display_<?= $m_id ?>" style="min-width: 60px;"><?= $is_pooled ? 0 : $stock ?></span>
                        <button type="button" class="btn btn-outline-success btn-qty shadow-sm" onclick="adjustStock(<?= $m_id ?>, 1)">+</button>
                    </div>
                </div>
            </div>

            <div class="mt-auto">
                <div class="d-flex gap-2 mb-2" id="item_stepper_extra_<?= $m_id ?>" style="<?= $is_pooled ? 'display:none;' : '' ?>">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill flex-grow-1" onclick="adjustStock(<?= $m_id ?>, 10)">+10</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill flex-grow-1" onclick="adjustStock(<?= $m_id ?>, 50)">+50</button>
                </div>
                <?php render_pool_link_select($m['stock_pool_id'] ?? null, $all_pools, "linkItemPool($m_id, this.value)"); ?>
            </div>
        </div>
    </div>
    <?php
}

// การ์ดท็อปปิ้ง/วัตถุดิบเสริม 1 ใบ
function render_stock_topping_card($top, $all_pools = []) {
    $t_id = $top['topping_id'];
    $is_active = $top['is_active'];
    $is_pooled = !empty($top['stock_pool_id']);
    $t_stock = $is_pooled ? intval($top['pool_stock_qty']) : intval($top['stock_qty']);

    $top_badge = "bg-success";
    $top_text = "เปิดขาย";
    if ($is_active == 0 || $t_stock <= 0) {
        $top_badge = "bg-danger";
        $top_text = ($is_active == 0) ? "ปิดขาย (สวิตช์ปิด)" : "ของหมด!";
    } elseif ($t_stock <= 5) {
        $top_badge = "bg-warning text-dark";
        $top_text = "ใกล้หมด";
    }
    ?>
    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
        <div class="card stock-card p-3 h-100 bg-white">
            <div class="d-flex justify-content-end mb-2">
                <span class="badge rounded-pill <?= $top_badge ?> px-3 py-1" id="top_badge_<?= $t_id ?>" data-active="<?= (int) $is_active ?>"><?= $top_text ?></span>
            </div>
            <h5 class="fw-bold mb-1"><?= htmlspecialchars($top['topping_name']) ?></h5>
            <div class="fw-bold text-success mb-2">+ ฿<?= number_format($top['price'], 0) ?></div>

            <div class="bg-light rounded-4 p-3 mb-3 text-center">
                <div class="small text-muted fw-bold mb-1">คลังสินค้าคงเหลือ (ชุด/จาน)</div>
                <div id="top_pooled_box_<?= $t_id ?>" style="<?= $is_pooled ? '' : 'display:none;' ?>">
                    <div class="small text-primary fw-bold mb-1"><i class="bi bi-link-45deg"></i> ใช้ร่วมกับกลุ่ม "<span id="top_pool_name_<?= $t_id ?>"><?= htmlspecialchars($top['pool_name'] ?? '') ?></span>"</div>
                    <div class="h2 fw-bold m-0" id="top_pool_qty_<?= $t_id ?>"><?= $is_pooled ? $t_stock : 0 ?></div>
                    <div class="form-text mb-0">ปรับจำนวนได้ที่แท็บ "กลุ่มสต็อกร่วม"</div>
                </div>
                <div id="top_own_box_<?= $t_id ?>" style="<?= $is_pooled ? 'display:none;' : '' ?>">
                    <div class="d-flex align-items-center justify-content-center gap-3 my-1">
                        <button type="button" class="btn btn-outline-danger btn-qty shadow-sm" onclick="adjustToppingStock(<?= $t_id ?>, -1)">-</button>
                        <span class="h2 fw-bold m-0" id="top_stock_display_<?= $t_id ?>" style="min-width: 60px;"><?= $is_pooled ? 0 : $t_stock ?></span>
                        <button type="button" class="btn btn-outline-success btn-qty shadow-sm" onclick="adjustToppingStock(<?= $t_id ?>, 1)">+</button>
                    </div>
                </div>
            </div>

            <div class="mt-auto">
                <div class="d-flex gap-2 mb-2" id="top_stepper_extra_<?= $t_id ?>" style="<?= $is_pooled ? 'display:none;' : '' ?>">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill flex-grow-1" onclick="adjustToppingStock(<?= $t_id ?>, 10)">+10</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill flex-grow-1" onclick="adjustToppingStock(<?= $t_id ?>, 50)">+50</button>
                </div>
                <button type="button" id="top_btn_<?= $t_id ?>" onclick="toggleToppingStock(<?= $t_id ?>, <?= $is_active ?>)" class="btn w-100 rounded-pill py-2 fw-bold shadow-sm <?= ($is_active == 1) ? 'btn-outline-danger' : 'btn-success' ?>">
                    <?= ($is_active == 1) ? '<i class="bi bi-pause-circle me-1"></i> กดปิดขาย (ปิดชั่วคราว)' : '<i class="bi bi-play-circle me-1"></i> กดเปิดขาย (เปิดใช้งาน)' ?>
                </button>
                <?php render_pool_link_select($top['stock_pool_id'] ?? null, $all_pools, "linkToppingPool($t_id, this.value)"); ?>
            </div>
        </div>
    </div>
    <?php
}

// การ์ดกลุ่มสต็อกร่วม 1 กลุ่ม - ทำเป็นแถวพับ/กางได้ (accordion) แบบเดียวกับแท็บเมนู/ท็อปปิ้ง วางซ้อนอยู่ในหมวดหมู่
// (accordion ของหมวดหมู่) อีกทีหนึ่ง $parent_sel คือ id ของ accordion หมวดหมู่ที่กลุ่มนี้อยู่ (ใช้ทำ data-bs-parent)
function render_stock_pool_card($p, $linked_names, $parent_sel = 'poolsGridRow') {
    $p_id = $p['pool_id'];
    $qty = (int) $p['stock_qty'];
    $badge = "bg-success"; $text = "มีของพอใช้";
    if ($qty <= 0) { $badge = "bg-danger"; $text = "ของหมด!"; }
    elseif ($qty <= 5) { $badge = "bg-warning text-dark"; $text = "ใกล้หมด"; }
    $names_json = htmlspecialchars(json_encode($p['pool_name'], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
    $cat_json = htmlspecialchars(json_encode($p['pool_category'] ?? '', JSON_UNESCAPED_UNICODE), ENT_QUOTES);
    ?>
    <div class="accordion-item border-0 shadow-sm rounded-4 overflow-hidden mb-3" id="pool-col-<?= $p_id ?>">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#poolCollapse<?= $p_id ?>">
                <i class="bi bi-boxes text-primary me-2"></i>
                <?= htmlspecialchars($p['pool_name']) ?>
                <span class="badge bg-light text-dark rounded-pill ms-2"><?= count($linked_names) ?> เมนู/ท็อปปิ้ง</span>
                <span class="badge rounded-pill <?= $badge ?> ms-1" id="pool_badge_<?= $p_id ?>"><?= $text ?></span>
            </button>
        </h2>
        <div id="poolCollapse<?= $p_id ?>" class="accordion-collapse collapse" data-bs-parent="#<?= $parent_sel ?>">
            <div class="accordion-body bg-white">
                <div class="small text-muted mb-3">
                    <?= !empty($linked_names) ? 'ใช้ร่วมกับ: ' . htmlspecialchars(implode(', ', $linked_names)) : 'ยังไม่มีเมนู/ท็อปปิ้งผูกกับกลุ่มนี้' ?>
                </div>

                <div class="bg-light rounded-4 p-3 mb-3 text-center">
                    <div class="d-flex align-items-center justify-content-center gap-3 my-1">
                        <button type="button" class="btn btn-outline-danger btn-qty shadow-sm" onclick="adjustPoolStock(<?= $p_id ?>, -1)">-</button>
                        <span class="h2 fw-bold m-0" id="pool_stock_display_<?= $p_id ?>" style="min-width: 60px;"><?= $qty ?></span>
                        <button type="button" class="btn btn-outline-success btn-qty shadow-sm" onclick="adjustPoolStock(<?= $p_id ?>, 1)">+</button>
                    </div>
                </div>

                <div class="d-flex gap-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill flex-grow-1" onclick="adjustPoolStock(<?= $p_id ?>, 10)">+10</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill flex-grow-1" onclick="adjustPoolStock(<?= $p_id ?>, 50)">+50</button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill flex-grow-1" onclick='openPoolModal(<?= $p_id ?>, <?= $names_json ?>, <?= $qty ?>, <?= $cat_json ?>)'><i class="bi bi-pencil-square"></i> แก้ไข</button>
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill flex-grow-1" onclick='deletePool(<?= $p_id ?>, <?= $names_json ?>)'><i class="bi bi-trash"></i> ลบ</button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

include '../includes/header_owner.php';
include '../includes/nav_owner.php';
?>

<style>
    .stock-card { border-radius: 20px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: 0.3s; }
    .stock-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    .btn-qty { width: 45px; height: 45px; border-radius: 50%; font-size: 1.3rem; font-weight: bold; display: flex; align-items: center; justify-content: center; }
    .star-btn { width: 34px; height: 34px; border-radius: 50%; border: 1px solid #e5e7eb; background-color: #f9fafb; color: #d1d5db; font-size: 1.1rem; line-height: 1; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; align-self: flex-start; }
    .star-btn.star-active { background-color: #fff7e0; border-color: #f5b301; color: #f5b301; }

    /* อนิเมชันตอนอัปเดตค่าแบบเรียลไทม์ (สต็อก/ป้ายสถานะ/กลุ่มร่วม) ไม่ต้องรีหน้า ให้เห็นชัดว่าค่าเพิ่งเปลี่ยน */
    @keyframes stockFlash {
        0%   { background-color: rgba(255, 193, 7, 0.55); }
        100% { background-color: transparent; }
    }
    .stock-flash { animation: stockFlash 0.7s ease-out; border-radius: 8px; }
    .h2.fw-bold { transition: transform 0.15s ease; }
    .stock-pop { transform: scale(1.18); }

    .product-table th { white-space: nowrap; font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
    .product-table td { vertical-align: middle; }
    .sku-badge { font-family: 'Courier New', monospace; font-weight: bold; background: #f1f5f9; padding: 3px 10px; border-radius: 8px; font-size: 0.85rem; }
    .qty-warn { color: #d97706; font-weight: bold; }
    .qty-danger { color: #dc2626; font-weight: bold; }
</style>

<div class="main-content container-fluid pb-5 px-4 pt-3 text-dark">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-white rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; border: 1px solid #edf2f7; background: #ffffff; color: #4a5568;" title="ย้อนกลับ">
                <i class="bi bi-arrow-left fs-4"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-0" style="color: #1a202c; font-size: 1.25rem;">
                    <i class="bi bi-box-seam text-warning me-2"></i>จัดการคลังสินค้า & วัตถุดิบ
                </h4>
                <p class="text-muted small mb-0">ตรวจสอบจำนวนคงเหลือคงคลังและปรับสถานะเปิด-ปิดของหมดได้อย่างง่ายดาย</p>
            </div>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success rounded-4 border-0 shadow-sm mb-4">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <ul class="nav nav-pills mb-4 gap-2" id="stockTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-3 px-4 py-3 fw-bold shadow-sm" id="items-tab" data-bs-toggle="pill" data-bs-target="#items-pane" type="button" role="tab">
                <i class="bi bi-egg-fried me-1"></i> เมนูอาหารหลัก
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 px-4 py-3 fw-bold shadow-sm" id="toppings-tab" data-bs-toggle="pill" data-bs-target="#toppings-pane" type="button" role="tab">
                <i class="bi bi-plus-circle-dotted me-1"></i> ท็อปปิ้ง & วัตถุดิบเสริม (หมูกรอบ ฯลฯ)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 px-4 py-3 fw-bold shadow-sm" id="pools-tab" data-bs-toggle="pill" data-bs-target="#pools-pane" type="button" role="tab">
                <i class="bi bi-boxes me-1"></i> กลุ่มสต็อกร่วม
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 px-4 py-3 fw-bold shadow-sm" id="products-tab" data-bs-toggle="pill" data-bs-target="#products-pane" type="button" role="tab">
                <i class="bi bi-upc-scan me-1"></i> รายการสินค้า
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 px-4 py-3 fw-bold shadow-sm" id="transactions-tab" data-bs-toggle="pill" data-bs-target="#transactions-pane" type="button" role="tab">
                <i class="bi bi-clock-history me-1"></i> บันทึกรับ-จ่าย
            </button>
        </li>
    </ul>

    <?php
    // ดึงรายชื่อกลุ่มสต็อกร่วมทั้งหมดไว้ล่วงหน้าครั้งเดียว ใช้ทั้ง dropdown ผูกกลุ่มในการ์ดเมนู/ท็อปปิ้ง และแท็บ "กลุ่มสต็อกร่วม"
    $all_pools = [];
    $pools_res = $conn->query("SELECT * FROM stock_pool ORDER BY pool_name ASC");
    if ($pools_res) { while ($p = $pools_res->fetch_assoc()) { $all_pools[] = $p; } }

    // ดึงสินค้าทั้ง 3 ประเภทมารวมเป็นลิสต์เดียวสำหรับแท็บ "รายการสินค้า" (SKU/ราคา/จุดสั่งซื้อซ้ำ) เรียงตามชื่อ
    $products = [];
    $res_items = $conn->query("SELECT item_id AS id, sku, name, price, reorder_point, stock_qty, c.category_name FROM item i LEFT JOIN category c ON i.category_id = c.category_id WHERE i.use_stock = 1 ORDER BY i.name ASC");
    if ($res_items) { while ($r = $res_items->fetch_assoc()) { $r['type'] = 'item'; $r['type_label'] = 'เมนูอาหาร'; $r['category_name'] = $r['category_name'] ?: 'ไม่มีหมวดหมู่'; $products[] = $r; } }
    $res_toppings = $conn->query("SELECT t.topping_id AS id, t.sku, t.topping_name AS name, t.price, t.reorder_point, t.stock_qty, tc.topping_cat_name AS category_name FROM topping t LEFT JOIN topping_categories tc ON t.topping_cat_id = tc.topping_cat_id WHERE t.use_stock = 1 ORDER BY t.topping_name ASC");
    if ($res_toppings) { while ($r = $res_toppings->fetch_assoc()) { $r['type'] = 'topping'; $r['type_label'] = 'ท็อปปิ้ง/วัตถุดิบเสริม'; $r['category_name'] = $r['category_name'] ?: 'ไม่มีหมวดหมู่'; $products[] = $r; } }
    $res_pools_meta = $conn->query("SELECT pool_id AS id, sku, pool_name AS name, NULL AS price, reorder_point, stock_qty, pool_category AS category_name FROM stock_pool ORDER BY pool_name ASC");
    if ($res_pools_meta) { while ($r = $res_pools_meta->fetch_assoc()) { $r['type'] = 'pool'; $r['type_label'] = 'กลุ่มสต็อกร่วม'; $r['category_name'] = $r['category_name'] ?: 'ไม่มีหมวดหมู่'; $products[] = $r; } }

    // ประวัติรับ-จ่ายสต็อกสำหรับแท็บ "บันทึกรับ-จ่าย" กรองตามช่วงวันที่ (ค่าเริ่มต้น = วันนี้)
    $txn_date_from = $_GET['txn_from'] ?? date('Y-m-d');
    $txn_date_to = $_GET['txn_to'] ?? date('Y-m-d');
    $txn_stmt = $conn->prepare(
        "SELECT * FROM stock_transactions WHERE DATE(occurred_at) BETWEEN ? AND ? ORDER BY occurred_at DESC, transaction_id DESC LIMIT 500"
    );
    $txn_stmt->bind_param("ss", $txn_date_from, $txn_date_to);
    $txn_stmt->execute();
    $transactions = $txn_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $txn_type_labels = ['item' => 'เมนูอาหาร', 'topping' => 'ท็อปปิ้ง/วัตถุดิบเสริม', 'pool' => 'กลุ่มสต็อกร่วม'];
    ?>

    <div class="tab-content" id="stockTabsContent">
        <!-- 🟢 TAB 1: คลังสินค้าเมนูอาหารหลัก แยกเป็นหมวดหมู่ (เรียงคลังสินค้าน้อยก่อนภายในแต่ละหมวด) -->
        <div class="tab-pane fade show active" id="items-pane" role="tabpanel">
            <?php
            $items_by_cat_stock = [];
            $items_uncat_stock = [];
            // LEFT JOIN stock_pool: เมนูที่ผูกกับกลุ่มสต็อกร่วมไว้ (i.stock_pool_id) ดึงชื่อกลุ่ม+จำนวนคงเหลือของกลุ่มมาด้วย
            $items_all_res = $conn->query("SELECT i.*, c.category_name, sp.pool_name, sp.stock_qty AS pool_stock_qty FROM item i LEFT JOIN category c ON i.category_id = c.category_id LEFT JOIN stock_pool sp ON sp.pool_id = i.stock_pool_id ORDER BY i.stock_qty ASC, i.item_id DESC");
            if ($items_all_res) {
                while ($m = $items_all_res->fetch_assoc()) {
                    if (!empty($m['category_id'])) {
                        $items_by_cat_stock[$m['category_id']][] = $m;
                    } else {
                        $items_uncat_stock[] = $m;
                    }
                }
            }
            $item_cats_stock = [];
            $item_cats_res = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name ASC");
            if ($item_cats_res) { while ($c = $item_cats_res->fetch_assoc()) { $item_cats_stock[] = $c; } }
            $has_any_item = !empty($items_by_cat_stock) || !empty($items_uncat_stock);
            ?>
            <?php if (!$has_any_item): ?>
                <div class="text-center py-5">
                    <i class="bi bi-box display-1 text-muted opacity-25"></i>
                    <p class="mt-3 text-muted">ยังไม่มีรายการเมนูในระบบ</p>
                </div>
            <?php else: ?>
            <div class="accordion" id="stockItemsAccordion">
                <?php foreach ($item_cats_stock as $c):
                    $cid = $c['category_id'];
                    if (empty($items_by_cat_stock[$cid])) continue;
                    [$out, $low] = stock_count_alerts_items($items_by_cat_stock[$cid]);
                ?>
                <div class="accordion-item border-0 shadow-sm rounded-4 overflow-hidden mb-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#stockItemsCat<?= $cid ?>">
                            <i class="bi bi-folder2-open text-primary me-2"></i>
                            <?= htmlspecialchars($c['category_name']) ?>
                            <span class="badge bg-light text-dark rounded-pill ms-2"><?= count($items_by_cat_stock[$cid]) ?> รายการ</span>
                            <?php if ($out > 0): ?><span class="badge bg-danger rounded-pill ms-1">หมด <?= $out ?></span><?php endif; ?>
                            <?php if ($low > 0): ?><span class="badge bg-warning text-dark rounded-pill ms-1">ใกล้หมด <?= $low ?></span><?php endif; ?>
                        </button>
                    </h2>
                    <div id="stockItemsCat<?= $cid ?>" class="accordion-collapse collapse" data-bs-parent="#stockItemsAccordion">
                        <div class="accordion-body bg-white">
                            <div class="row g-4">
                                <?php foreach ($items_by_cat_stock[$cid] as $m) { render_stock_item_card($m, $all_pools); } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (!empty($items_uncat_stock)):
                    [$out, $low] = stock_count_alerts_items($items_uncat_stock);
                ?>
                <div class="accordion-item border-0 shadow-sm rounded-4 overflow-hidden mb-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#stockItemsUncat">
                            <i class="bi bi-folder2 text-secondary me-2"></i>
                            ไม่มีหมวดหมู่
                            <span class="badge bg-light text-dark rounded-pill ms-2"><?= count($items_uncat_stock) ?> รายการ</span>
                            <?php if ($out > 0): ?><span class="badge bg-danger rounded-pill ms-1">หมด <?= $out ?></span><?php endif; ?>
                            <?php if ($low > 0): ?><span class="badge bg-warning text-dark rounded-pill ms-1">ใกล้หมด <?= $low ?></span><?php endif; ?>
                        </button>
                    </h2>
                    <div id="stockItemsUncat" class="accordion-collapse collapse" data-bs-parent="#stockItemsAccordion">
                        <div class="accordion-body bg-white">
                            <div class="row g-4">
                                <?php foreach ($items_uncat_stock as $m) { render_stock_item_card($m, $all_pools); } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- 🟢 TAB 2: คลังสินค้าท็อปปิ้ง & วัตถุดิบเสริม (มีจำนวนคงเหลือ + ปุ่มเปิด/ปิด) -->
        <div class="tab-pane fade" id="toppings-pane" role="tabpanel">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3 gap-2">
                <p class="text-muted small mb-0">
                    แสดงเฉพาะของที่ติดตามคลังสินค้าไว้
                    <i class="bi bi-info-circle ms-1" title="ตัวเลือกที่ไม่มีวันหมด เช่น ระดับความเผ็ด ขนาดจาน จะไม่แสดงในหน้านี้"></i>
                </p>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm flex-shrink-0" onclick="openAddToppingStockModal()">
                    <i class="bi bi-plus-circle me-1"></i> เพิ่มท็อปปิ้ง/วัตถุดิบ
                </button>
            </div>
            <?php
            $toppings_by_cat_stock = [];
            $toppings_all_res = $conn->query("SELECT t.*, sp.pool_name, sp.stock_qty AS pool_stock_qty FROM topping t LEFT JOIN stock_pool sp ON sp.pool_id = t.stock_pool_id WHERE t.use_stock = 1 ORDER BY t.stock_qty ASC, t.topping_id DESC");
            if ($toppings_all_res) {
                while ($top = $toppings_all_res->fetch_assoc()) {
                    $toppings_by_cat_stock[$top['topping_cat_id']][] = $top;
                }
            }
            $topping_cats_stock = [];
            $topping_cats_stock_res = $conn->query("SELECT topping_cat_id, topping_cat_name FROM topping_categories ORDER BY sort_order ASC, topping_cat_name ASC");
            if ($topping_cats_stock_res) { while ($tc = $topping_cats_stock_res->fetch_assoc()) { $topping_cats_stock[] = $tc; } }
            ?>
            <?php if (empty($toppings_by_cat_stock)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-egg-fried display-1 text-muted opacity-25"></i>
                    <p class="mt-3 text-muted">ยังไม่มีรายการท็อปปิ้งในระบบ</p>
                </div>
            <?php else: ?>
            <div class="accordion" id="stockToppingsAccordion">
                <?php foreach ($topping_cats_stock as $tc):
                    $cid = $tc['topping_cat_id'];
                    if (empty($toppings_by_cat_stock[$cid])) continue;
                    [$out, $low] = stock_count_alerts_toppings($toppings_by_cat_stock[$cid]);
                ?>
                <div class="accordion-item border-0 shadow-sm rounded-4 overflow-hidden mb-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#stockToppingsCat<?= $cid ?>">
                            <i class="bi bi-folder2-open text-primary me-2"></i>
                            <?= htmlspecialchars($tc['topping_cat_name']) ?>
                            <span class="badge bg-light text-dark rounded-pill ms-2"><?= count($toppings_by_cat_stock[$cid]) ?> รายการ</span>
                            <?php if ($out > 0): ?><span class="badge bg-danger rounded-pill ms-1">หมด <?= $out ?></span><?php endif; ?>
                            <?php if ($low > 0): ?><span class="badge bg-warning text-dark rounded-pill ms-1">ใกล้หมด <?= $low ?></span><?php endif; ?>
                        </button>
                    </h2>
                    <div id="stockToppingsCat<?= $cid ?>" class="accordion-collapse collapse" data-bs-parent="#stockToppingsAccordion">
                        <div class="accordion-body bg-white">
                            <div class="row g-4">
                                <?php foreach ($toppings_by_cat_stock[$cid] as $top) { render_stock_topping_card($top, $all_pools); } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- 🟢 TAB 3: กลุ่มสต็อกร่วม - ให้หลายเมนู/ท็อปปิ้งที่ใช้วัตถุดิบตัวเดียวกันจริงหักสต็อกจากกองเดียวกัน -->
        <div class="tab-pane fade" id="pools-pane" role="tabpanel">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3 gap-2">
                <p class="text-muted small mb-0">
                    เมนู/ท็อปปิ้งที่ใช้วัตถุดิบร่วมกันจริง (เช่น "ข้าวผัดไก่" กับ "กระเพราไก่" ใช้ไก่ก้อนเดียวกัน) ผูกเข้ากลุ่มเดียวกันได้ที่นี่ ขายอันไหนก็ตัดสต็อกกองเดียวกันหมด
                    <i class="bi bi-info-circle ms-1" title="ไม่ผูกก็ยังนับสต็อกแยกของตัวเองได้ตามปกติ ไม่บังคับ"></i>
                </p>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm flex-shrink-0" onclick="openPoolModal()">
                    <i class="bi bi-plus-circle me-1"></i> สร้างกลุ่มใหม่
                </button>
            </div>
            <?php if (empty($all_pools)): ?>
                <div class="text-center py-5" id="poolsEmptyState">
                    <i class="bi bi-boxes display-1 text-muted opacity-25"></i>
                    <p class="mt-3 text-muted">ยังไม่มีกลุ่มสต็อกร่วมในระบบ</p>
                </div>
            <?php else: ?>
                <?php
                // หาชื่อเมนู/ท็อปปิ้งที่ผูกกับแต่ละกลุ่มไว้ (โชว์ในการ์ดให้เห็นว่ากลุ่มนี้ใช้กับอะไรบ้าง)
                $pool_links = [];
                $link_items_res = $conn->query("SELECT stock_pool_id, name FROM item WHERE stock_pool_id IS NOT NULL");
                if ($link_items_res) { while ($r = $link_items_res->fetch_assoc()) { $pool_links[$r['stock_pool_id']][] = $r['name']; } }
                $link_tops_res = $conn->query("SELECT stock_pool_id, topping_name AS name FROM topping WHERE stock_pool_id IS NOT NULL");
                if ($link_tops_res) { while ($r = $link_tops_res->fetch_assoc()) { $pool_links[$r['stock_pool_id']][] = $r['name']; } }

                // จัดกลุ่มสต็อกร่วมเป็นหมวดหมู่ (เลือกตอนสร้าง/แก้ไขกลุ่ม) แบบเดียวกับแท็บเมนู/ท็อปปิ้งข้างบน
                // เรียงหมวดที่ใช้บ่อยตาม POOL_CATEGORY_PRESETS ก่อน หมวดอื่นเรียงตามตัวอักษรต่อท้าย และ
                // "ไม่มีหมวดหมู่" (ยังไม่ได้เลือก/พิมพ์เองไม่ผ่านลิสต์) ไว้ท้ายสุดเสมอ
                $pool_category_order = ['เนื้อสัตว์', 'ผัก', 'เส้น/แป้ง', 'เครื่องปรุง/ซอส'];
                $pools_by_cat = [];
                foreach ($all_pools as $p) {
                    $cat = trim($p['pool_category'] ?? '');
                    $pools_by_cat[$cat !== '' ? $cat : 'ไม่มีหมวดหมู่'][] = $p;
                }
                uksort($pools_by_cat, function ($a, $b) use ($pool_category_order) {
                    if ($a === 'ไม่มีหมวดหมู่') return 1;
                    if ($b === 'ไม่มีหมวดหมู่') return -1;
                    $ia = array_search($a, $pool_category_order);
                    $ib = array_search($b, $pool_category_order);
                    if ($ia === false && $ib === false) return strcmp($a, $b);
                    if ($ia === false) return 1;
                    if ($ib === false) return -1;
                    return $ia <=> $ib;
                });
                ?>
                <div class="accordion" id="poolsGridRow">
                    <?php $cat_idx = 0; foreach ($pools_by_cat as $cat_name => $cat_pools):
                        $cat_idx++;
                        $cat_slug = 'poolCat' . $cat_idx;
                        $is_uncat = ($cat_name === 'ไม่มีหมวดหมู่');
                    ?>
                    <div class="accordion-item border-0 shadow-sm rounded-4 overflow-hidden mb-3">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $cat_slug ?>">
                                <i class="bi <?= $is_uncat ? 'bi-folder2 text-secondary' : 'bi-folder2-open text-primary' ?> me-2"></i>
                                <?= htmlspecialchars($cat_name) ?>
                                <span class="badge bg-light text-dark rounded-pill ms-2"><?= count($cat_pools) ?> กลุ่ม</span>
                            </button>
                        </h2>
                        <div id="<?= $cat_slug ?>" class="accordion-collapse collapse" data-bs-parent="#poolsGridRow">
                            <div class="accordion-body bg-white">
                                <div class="accordion" id="<?= $cat_slug ?>Inner">
                                    <?php foreach ($cat_pools as $p) { render_stock_pool_card($p, $pool_links[$p['pool_id']] ?? [], $cat_slug . 'Inner'); } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 🟢 TAB 4: รายการสินค้า - SKU/ราคาขาย/จุดสั่งซื้อซ้ำของทุกรายการที่ติดตามคลังสินค้า ดูง่าย พิมพ์ได้ -->
        <div class="tab-pane fade" id="products-pane" role="tabpanel">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3 gap-2">
                <p class="text-muted small mb-0">SKU, ชื่อสินค้า, หมวดหมู่, ราคาขาย และจุดสั่งซื้อซ้ำของทุกรายการที่ติดตามคลังสินค้า</p>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold shadow-sm flex-shrink-0" onclick="window.open('print_product_list.php', '_blank', 'width=900,height=700')">
                    <i class="bi bi-printer me-1"></i> พิมพ์รายการ
                </button>
            </div>
            <?php if (empty($products)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-upc-scan display-1 text-muted opacity-25"></i>
                    <p class="mt-3 text-muted">ยังไม่มีสินค้าที่ติดตามคลังสินค้าในระบบ</p>
                </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="table-responsive">
                    <table class="table product-table mb-0">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>ชื่อสินค้า</th>
                                <th>ประเภท</th>
                                <th>หมวดหมู่</th>
                                <th class="text-end">ราคาขาย</th>
                                <th class="text-end">คงเหลือ</th>
                                <th class="text-end">จุดสั่งซื้อซ้ำ</th>
                                <th class="text-center">แก้ไข</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p):
                                $qty = (int) $p['stock_qty'];
                                $reorder = (int) $p['reorder_point'];
                                $qty_class = $qty <= 0 ? 'qty-danger' : ($qty <= $reorder ? 'qty-warn' : '');
                            ?>
                            <tr>
                                <td><span class="sku-badge"><?= htmlspecialchars($p['sku'] ?: '-') ?></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($p['name']) ?></td>
                                <td><span class="badge bg-light text-dark rounded-pill"><?= htmlspecialchars($p['type_label']) ?></span></td>
                                <td class="text-muted small"><?= htmlspecialchars($p['category_name']) ?></td>
                                <td class="text-end"><?= $p['price'] !== null ? '฿' . number_format((float) $p['price'], 2) : '-' ?></td>
                                <td class="text-end <?= $qty_class ?>"><?= $qty ?></td>
                                <td class="text-end"><?= $reorder ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" onclick='openEditProductModal(<?= json_encode([
                                        'type' => $p['type'],
                                        'id' => (int) $p['id'],
                                        'name' => $p['name'],
                                        'sku' => $p['sku'],
                                        'reorder_point' => $reorder,
                                    ], JSON_UNESCAPED_UNICODE) ?>)'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- 🟢 TAB 5: บันทึกรับ-จ่าย - ประวัติการเข้า-ออกของสต็อกทุกครั้ง ทั้งจากออเดอร์ลูกค้าและปรับมือ -->
        <div class="tab-pane fade" id="transactions-pane" role="tabpanel">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3 gap-2">
                <p class="text-muted small mb-0">ประวัติการเข้า-ออกของสต็อกทุกครั้ง ทั้งจากออเดอร์ลูกค้าและการปรับมือ</p>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold shadow-sm flex-shrink-0" onclick="printTransactions()">
                    <i class="bi bi-printer me-1"></i> พิมพ์รายการ
                </button>
            </div>

            <form method="GET" id="txnFilterForm" class="d-flex flex-wrap gap-2 align-items-end mb-4">
                <div>
                    <label class="small fw-bold mb-1 d-block">จากวันที่</label>
                    <input type="date" name="txn_from" class="form-control rounded-3" value="<?= htmlspecialchars($txn_date_from) ?>">
                </div>
                <div>
                    <label class="small fw-bold mb-1 d-block">ถึงวันที่</label>
                    <input type="date" name="txn_to" class="form-control rounded-3" value="<?= htmlspecialchars($txn_date_to) ?>">
                </div>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                    <i class="bi bi-search me-1"></i> ค้นหา
                </button>
            </form>

            <?php if (empty($transactions)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-clock-history display-1 text-muted opacity-25"></i>
                    <p class="mt-3 text-muted">ไม่มีรายการรับ-จ่ายในช่วงวันที่นี้</p>
                </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <div class="table-responsive">
                    <table class="table product-table mb-0">
                        <thead>
                            <tr>
                                <th>วันที่เวลา</th>
                                <th>SKU</th>
                                <th>ชื่อสินค้า</th>
                                <th>ประเภท</th>
                                <th class="text-end">จำนวนเข้า (In)</th>
                                <th class="text-end">จำนวนออก (Out)</th>
                                <th class="text-end">คงเหลือ</th>
                                <th>เอกสาร/ออเดอร์อ้างอิง</th>
                                <th>ผู้ทำรายการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($t['occurred_at'])) ?></td>
                                <td><span class="sku-badge"><?= htmlspecialchars($t['sku'] ?: '-') ?></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($t['item_name']) ?></td>
                                <td><span class="badge bg-light text-dark rounded-pill"><?= htmlspecialchars($txn_type_labels[$t['item_type']] ?? $t['item_type']) ?></span></td>
                                <td class="text-end qty-in" style="color:#16a34a;font-weight:bold;"><?= $t['qty_change'] > 0 ? '+' . $t['qty_change'] : '' ?></td>
                                <td class="text-end qty-out" style="color:#dc2626;font-weight:bold;"><?= $t['qty_change'] < 0 ? $t['qty_change'] : '' ?></td>
                                <td class="text-end fw-bold"><?= $t['qty_after'] ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($t['source_ref'] ?: ($t['note'] ?: '-')) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($t['created_by'] ?: '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: แก้ไขข้อมูลสินค้า (SKU / จุดสั่งซื้อซ้ำ) -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 rounded-4" id="editProductForm">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="fw-bold m-0">แก้ไขข้อมูลสินค้า <span id="editProductName" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <input type="hidden" id="editProductType" name="item_type">
                <input type="hidden" id="editProductId" name="item_id">
                <div class="mb-3">
                    <label class="small fw-bold mb-2">SKU</label>
                    <input type="text" id="editProductSku" name="sku" class="form-control rounded-3" placeholder="เช่น ITM-001">
                </div>
                <div class="mb-3">
                    <label class="small fw-bold mb-2">จุดสั่งซื้อซ้ำ (แจ้งเตือน "ใกล้หมด" เมื่อคงเหลือถึงจำนวนนี้)</label>
                    <input type="number" step="1" min="0" id="editProductReorder" name="reorder_point" class="form-control rounded-3" value="5" required>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: สร้าง/แก้ไขกลุ่มสต็อกร่วม -->
<div class="modal fade" id="poolModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 rounded-4" id="poolForm">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="fw-bold m-0" id="poolModalTitle">สร้างกลุ่มสต็อกร่วมใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <input type="hidden" name="pool_id" id="pool_id" value="0">
                <div class="mb-3" id="poolCategoryPickerWrap">
                    <label class="small fw-bold mb-2">เลือกจากหมวดหมู่วัตถุดิบ <span class="text-muted fw-normal">(ไม่ต้องพิมพ์เอง ถ้ามีในลิสต์)</span></label>
                    <div class="row g-2">
                        <div class="col-6">
                            <select id="poolCategorySelect" name="pool_category" class="form-select rounded-3">
                                <option value="">หมวดหมู่...</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <select id="poolSubCategorySelect" class="form-select rounded-3" disabled>
                                <option value="">ชนิด...</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold mb-2">ชื่อกลุ่ม</label>
                    <input type="text" name="pool_name" id="pool_name" class="form-control rounded-3" placeholder="เช่น ไก่, หมู, กุ้ง (หรือเลือกจากหมวดหมู่ด้านบน)" required>
                    <div class="invalid-feedback">กรุณากรอกชื่อกลุ่มสต็อก</div>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold mb-2">จำนวนคงเหลือ</label>
                    <input type="number" step="1" min="0" name="stock_qty" id="pool_qty" class="form-control rounded-3" value="0" required>
                    <div class="invalid-feedback">จำนวนต้องเป็นจำนวนเต็มและห้ามติดลบ</div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: เพิ่มท็อปปิ้ง/วัตถุดิบใหม่ (เฉพาะของที่ต้องนับคลังสินค้า แยกจากการเพิ่มเมนูอาหารหลัก) -->
<div class="modal fade" id="addToppingStockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 rounded-4" id="newToppingStockForm">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="fw-bold m-0">เพิ่มท็อปปิ้ง/วัตถุดิบใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <p class="text-muted small">รายการที่เพิ่มจากหน้านี้จะเปิดติดตามคลังสินค้าให้อัตโนมัติ (ถ้าต้องการตัวเลือกที่ไม่มีวันหมด เช่น ระดับความเผ็ด ให้ไปเพิ่มที่หน้า "จัดการตัวเลือกเสริม" แทน)</p>

                <div class="mb-3">
                    <label class="small fw-bold mb-2">ชื่อท็อปปิ้ง/วัตถุดิบ</label>
                    <input type="text" name="topping_name" id="new_topping_name" class="form-control rounded-3" required>
                    <div class="invalid-feedback">กรุณากรอกชื่อท็อปปิ้ง/วัตถุดิบ</div>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold mb-2">กลุ่ม</label>
                    <select name="topping_cat_id" id="new_topping_cat_id" class="form-select rounded-3" required>
                        <?php
                        $stock_cats = $conn->query("SELECT * FROM topping_categories ORDER BY sort_order ASC, topping_cat_name ASC");
                        while ($sc = $stock_cats->fetch_assoc()):
                        ?>
                            <option value="<?= $sc['topping_cat_id'] ?>"><?= htmlspecialchars($sc['topping_cat_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <div class="invalid-feedback">กรุณาเลือกกลุ่ม</div>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold mb-2">ราคาที่บวกเพิ่ม (฿)</label>
                    <input type="number" step="0.01" min="0" name="price" id="new_topping_price" class="form-control rounded-3" value="0.00" required>
                    <div class="invalid-feedback">ราคาต้องเป็นตัวเลขและห้ามติดลบ</div>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold mb-2">จำนวนเริ่มต้นในคลังสินค้า</label>
                    <input type="number" step="1" min="0" name="stock_qty" id="new_topping_stock_qty" class="form-control rounded-3" value="50" required>
                    <div class="invalid-feedback">จำนวนต้องเป็นจำนวนเต็มและห้ามติดลบ</div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<script>
// กระพริบไฮไลต์สั้นๆ บนอีลีเมนต์ที่เพิ่งอัปเดตค่า ให้เห็นชัดว่าเปลี่ยนแบบเรียลไทม์ (ไม่ใช่แค่โผล่มาเฉยๆ)
function flashUpdate(el) {
    if (!el) return;
    el.classList.remove('stock-flash', 'stock-pop');
    void el.offsetWidth; // reflow เพื่อ retrigger อนิเมชันได้แม้เพิ่งเล่นไปหมาดๆ
    el.classList.add('stock-flash', 'stock-pop');
    setTimeout(() => el.classList.remove('stock-flash', 'stock-pop'), 700);
}

// อัปเดตป้ายสถานะ (หมด/ใกล้หมด/มีของพอใช้) ของเมนูให้ตรงกับจำนวนล่าสุดทันที โดยไม่ต้องโหลดหน้าใหม่
// (เดิมอัปเดตแค่ตัวเลขอย่างเดียว ป้ายสียังค้างค่าตอนโหลดหน้าครั้งแรกอยู่ ทำให้ดูไม่ real-time)
function updateItemBadge(itemId, qty) {
    const badge = document.getElementById('item_badge_' + itemId);
    if (!badge) return;
    badge.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'text-dark');
    if (qty <= 0) {
        badge.classList.add('bg-danger');
        badge.textContent = 'ของหมด!';
    } else if (qty <= 5) {
        badge.classList.add('bg-warning', 'text-dark');
        badge.textContent = 'ใกล้หมด';
    } else {
        badge.classList.add('bg-success');
        badge.textContent = 'มีของพอใช้';
    }
    flashUpdate(badge);
}

function adjustStock(itemId, change) {
    const display = document.getElementById('stock_display_' + itemId);
    let current = parseInt(display.innerText) || 0;
    let nextVal = Math.max(0, current + change);
    display.innerText = nextVal;
    flashUpdate(display);
    updateItemBadge(itemId, nextVal);

    const formData = new FormData();
    formData.append('action', 'quick_adjust');
    formData.append('item_id', itemId);
    formData.append('change', change);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('manage_stock.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            display.innerText = data.new_qty;
            updateItemBadge(itemId, data.new_qty);
        } else {
            display.innerText = current;
            updateItemBadge(itemId, current);
        }
    })
    .catch(err => { console.error(err); display.innerText = current; updateItemBadge(itemId, current); });
}

// เหมือน updateItemBadge แต่ต้องเช็ค data-active ก่อนด้วย เพราะป้ายของท็อปปิ้งมี 2 เงื่อนไขซ้อนกัน
// (ปิดขายด้วยสวิตช์ อยู่เหนือกว่าเงื่อนไขจำนวนคงเหลือเสมอ - ต่อให้เพิ่งเติมของจนพอใช้แล้ว แต่ยังปิดขายอยู่ก็ต้องโชว์ "ปิดขาย" ไม่ใช่ "มีของพอใช้")
function updateToppingBadge(toppingId, qty) {
    const badge = document.getElementById('top_badge_' + toppingId);
    if (!badge) return;
    if (badge.dataset.active === '0') return; // ปิดขายอยู่ ไม่ต้องยุ่งกับป้าย ปล่อยให้ toggleToppingStock จัดการเอง
    badge.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'text-dark');
    if (qty <= 0) {
        badge.classList.add('bg-danger');
        badge.textContent = 'ของหมด!';
    } else if (qty <= 5) {
        badge.classList.add('bg-warning', 'text-dark');
        badge.textContent = 'ใกล้หมด';
    } else {
        badge.classList.add('bg-success');
        badge.textContent = 'เปิดขาย';
    }
    flashUpdate(badge);
}

function adjustToppingStock(toppingId, change) {
    const display = document.getElementById('top_stock_display_' + toppingId);
    let current = parseInt(display.innerText) || 0;
    let nextVal = Math.max(0, current + change);
    display.innerText = nextVal;
    flashUpdate(display);
    updateToppingBadge(toppingId, nextVal);

    const formData = new FormData();
    formData.append('action', 'quick_adjust_topping');
    formData.append('topping_id', toppingId);
    formData.append('change', change);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('manage_stock.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            display.innerText = data.new_qty;
            updateToppingBadge(toppingId, data.new_qty);
        } else {
            display.innerText = current;
            updateToppingBadge(toppingId, current);
        }
    })
    .catch(err => { console.error(err); display.innerText = current; updateToppingBadge(toppingId, current); });
}

function toggleFeatured(itemId, newFeatured) {
    const formData = new FormData();
    formData.append('update_featured_id', itemId);
    formData.append('new_featured_val', newFeatured);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('api_toggle_featured.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(data => {
        if (data.trim() === 'success') {
            const btn = document.getElementById('star-btn-' + itemId);
            if (newFeatured === 1) {
                btn.className = 'star-btn star-active';
                btn.setAttribute('onclick', 'toggleFeatured(' + itemId + ', 0)');
                btn.title = 'เมนูแนะนำ (กดเพื่อยกเลิก)';
            } else {
                btn.className = 'star-btn';
                btn.setAttribute('onclick', 'toggleFeatured(' + itemId + ', 1)');
                btn.title = 'ติดดาวเป็นเมนูแนะนำ';
            }
        } else {
            ownerNotify('เกิดข้อผิดพลาด บันทึกไม่สำเร็จ', 'error');
        }
    })
    .catch(err => console.error(err));
}

function toggleToppingStock(toppingId, currentStatus) {
    const formData = new FormData();
    formData.append('action', 'toggle_topping');
    formData.append('topping_id', toppingId);
    formData.append('current_status', currentStatus);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('manage_stock.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) { ownerNotify('เกิดข้อผิดพลาด ไม่สามารถเปลี่ยนสถานะได้', 'error'); return; }

        const newStatus = data.new_status;
        const btn = document.getElementById('top_btn_' + toppingId);
        if (btn) {
            btn.setAttribute('onclick', 'toggleToppingStock(' + toppingId + ', ' + newStatus + ')');
            btn.classList.toggle('btn-outline-danger', newStatus == 1);
            btn.classList.toggle('btn-success', newStatus != 1);
            btn.innerHTML = (newStatus == 1)
                ? '<i class="bi bi-pause-circle me-1"></i> กดปิดขาย (ปิดชั่วคราว)'
                : '<i class="bi bi-play-circle me-1"></i> กดเปิดขาย (เปิดใช้งาน)';
        }

        const badge = document.getElementById('top_badge_' + toppingId);
        if (badge) {
            badge.dataset.active = newStatus;
            if (newStatus == 1) {
                const pooledBox = document.getElementById('top_pooled_box_' + toppingId);
                const isPooled = pooledBox && pooledBox.style.display !== 'none';
                const qtyEl = document.getElementById(isPooled ? 'top_pool_qty_' + toppingId : 'top_stock_display_' + toppingId);
                updateToppingBadge(toppingId, parseInt(qtyEl ? qtyEl.textContent : 0, 10) || 0);
            } else {
                badge.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'text-dark');
                badge.classList.add('bg-danger');
                badge.textContent = 'ปิดขาย (สวิตช์ปิด)';
                flashUpdate(badge);
            }
        }
    })
    .catch(err => console.error(err));
}

// เปิดหลังจากรีโหลด ให้กลับไปอยู่แท็บเดิมที่เพิ่งทำรายการอยู่ต่อ (ไม่กระโดดกลับไปแท็บเมนูอาหารเสมอ)
document.addEventListener('DOMContentLoaded', function () {
    const activeTab = sessionStorage.getItem('stockActiveTab');
    if (activeTab) {
        sessionStorage.removeItem('stockActiveTab');
        const trigger = document.getElementById(activeTab + '-tab');
        if (trigger) new bootstrap.Tab(trigger).show();
    }
});

/**
 * กลุ่มสต็อกร่วม (Stock Pool) - +/- ปรับจำนวน, สร้าง/แก้ไข, ลบ, และผูก/ยกเลิกผูกเมนู-ท็อปปิ้งเข้ากลุ่ม
 */
// อัปเดตป้ายสถานะ (หมด/ใกล้หมด/มีของพอใช้) ให้ตรงกับจำนวนล่าสุดทันทีที่ตัวเลขเปลี่ยน โดยไม่ต้องโหลดหน้าใหม่
// (เดิมอัปเดตแค่ตัวเลขอย่างเดียว ป้ายสียังค้างค่าตอนโหลดหน้าครั้งแรกอยู่ ทำให้ดูไม่ real-time)
function updatePoolBadge(poolId, qty) {
    const badge = document.getElementById('pool_badge_' + poolId);
    if (!badge) return;
    badge.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'text-dark');
    if (qty <= 0) {
        badge.classList.add('bg-danger');
        badge.textContent = 'ของหมด!';
    } else if (qty <= 5) {
        badge.classList.add('bg-warning', 'text-dark');
        badge.textContent = 'ใกล้หมด';
    } else {
        badge.classList.add('bg-success');
        badge.textContent = 'มีของพอใช้';
    }
    flashUpdate(badge);
}

function adjustPoolStock(poolId, change) {
    const display = document.getElementById('pool_stock_display_' + poolId);
    let current = parseInt(display.innerText) || 0;
    const optimisticQty = Math.max(0, current + change);
    display.innerText = optimisticQty;
    flashUpdate(display);
    updatePoolBadge(poolId, optimisticQty);

    const formData = new FormData();
    formData.append('action', 'quick_adjust_pool');
    formData.append('pool_id', poolId);
    formData.append('change', change);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('manage_stock.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            display.innerText = data.new_qty;
            updatePoolBadge(poolId, data.new_qty);
        } else {
            // เซิร์ฟเวอร์ปฏิเสธ (เช่นค่าที่ปรับไม่ผ่านเงื่อนไข) ต้องเด้งตัวเลข/ป้ายกลับค่าเดิมก่อนกดคืน ไม่งั้นจะค้างค่าที่ผิดไว้บนจอ
            display.innerText = current;
            updatePoolBadge(poolId, current);
        }
    })
    .catch(err => { console.error(err); display.innerText = current; updatePoolBadge(poolId, current); });
}

// หมวดหมู่วัตถุดิบที่ใช้บ่อย ช่วยให้เลือกแทนพิมพ์ชื่อกลุ่มซ้ำๆ เอง (เช่น "ไก่", "หมู" ที่ต้องพิมพ์ทุกครั้งที่สร้างกลุ่มใหม่)
// เลือกหมวดหมู่ -> เลือกชนิด -> เติมชื่อกลุ่มให้อัตโนมัติ (ยังแก้ไขในช่องข้อความได้ตามปกติ ถ้าไม่มีในลิสต์ก็พิมพ์เองได้เหมือนเดิม)
const POOL_CATEGORY_PRESETS = {
    'เนื้อสัตว์': ['ไก่', 'หมู', 'หมูกรอบ', 'วัว', 'กุ้ง', 'ปลา', 'ปลาหมึก', 'ไข่'],
    'ผัก': ['ผักกาด', 'กะหล่ำปลี', 'ต้นหอม', 'ผักบุ้ง', 'แครอท', 'พริก', 'มะเขือเทศ'],
    'เส้น/แป้ง': ['เส้นหมี่', 'เส้นใหญ่', 'เส้นเล็ก', 'วุ้นเส้น', 'ข้าว'],
    'เครื่องปรุง/ซอส': ['น้ำจิ้ม', 'ซอสพริก', 'ซีอิ๊ว', 'น้ำปลา', 'น้ำมันหอย'],
};

function initPoolCategoryPicker() {
    const catSelect = document.getElementById('poolCategorySelect');
    const subSelect = document.getElementById('poolSubCategorySelect');
    Object.keys(POOL_CATEGORY_PRESETS).forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat;
        opt.textContent = cat;
        catSelect.appendChild(opt);
    });

    catSelect.addEventListener('change', function () {
        subSelect.innerHTML = '<option value="">ชนิด...</option>';
        const items = POOL_CATEGORY_PRESETS[this.value];
        if (items && items.length) {
            items.forEach(name => {
                const opt = document.createElement('option');
                opt.value = name;
                opt.textContent = name;
                subSelect.appendChild(opt);
            });
            subSelect.disabled = false;
        } else {
            subSelect.disabled = true;
        }
    });

    subSelect.addEventListener('change', function () {
        if (this.value) {
            document.getElementById('pool_name').value = this.value;
        }
    });
}
initPoolCategoryPicker();

function openPoolModal(poolId, poolName, stockQty, poolCategory) {
    const form = document.getElementById('poolForm');
    form.reset();
    form.classList.remove('was-validated');
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

    const catSelect = document.getElementById('poolCategorySelect');
    const subSelect = document.getElementById('poolSubCategorySelect');
    // ถ้าหมวดหมู่เดิมของกลุ่มนี้อยู่ในลิสต์พรีเซ็ต ให้เลือกไว้ให้เลย (ถ้าไม่มีในลิสต์ เช่น พิมพ์เองก่อนหน้า จะโชว์
    // เป็น "หมวดหมู่..." เฉยๆ แต่ค่าเดิมจะไม่หายไปไหน เพราะไม่ได้แก้ชื่อกลุ่ม/หมวดหมู่จนกว่าจะกดบันทึกจริง)
    catSelect.value = poolCategory || '';
    subSelect.innerHTML = '<option value="">ชนิด...</option>';
    subSelect.disabled = true;

    const isEdit = !!poolId;
    document.getElementById('poolModalTitle').textContent = isEdit ? 'แก้ไขกลุ่มสต็อกร่วม' : 'สร้างกลุ่มสต็อกร่วมใหม่';
    document.getElementById('pool_id').value = poolId || 0;
    document.getElementById('pool_name').value = poolName || '';
    document.getElementById('pool_qty').value = (stockQty !== undefined) ? stockQty : 0;
    new bootstrap.Modal(document.getElementById('poolModal')).show();
}

document.getElementById('poolForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const nameInput = document.getElementById('pool_name');
    const qtyInput = document.getElementById('pool_qty');
    let valid = true;
    if (!nameInput.value.trim()) { nameInput.classList.add('is-invalid'); valid = false; } else { nameInput.classList.remove('is-invalid'); }
    if (qtyInput.value === '' || !Number.isInteger(Number(qtyInput.value)) || Number(qtyInput.value) < 0) { qtyInput.classList.add('is-invalid'); valid = false; } else { qtyInput.classList.remove('is-invalid'); }
    if (!valid) return;

    const formData = new FormData(this);
    formData.append('action', 'save_pool');
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('manage_stock.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            ownerNotify(data.error || 'เกิดข้อผิดพลาด ไม่สามารถบันทึกได้', 'error');
            return;
        }
        bootstrap.Modal.getInstance(document.getElementById('poolModal')).hide();
        ownerNotify('บันทึกกลุ่มสต็อกร่วมเรียบร้อยแล้ว');

        // สั่ง soft-refresh ทั้งแท็บแทนการแทรก/แทนที่การ์ดเองด้วยมือ เพราะตอนนี้กลุ่มถูกจัดเรียงตามหมวดหมู่
        // ด้วย (แก้ไขแล้วอาจย้ายไปอยู่คนละหมวดกับที่โชว์อยู่บนจอ) รีเฟรชจากเซิร์ฟเวอร์แม่นกว่าคำนวณเองฝั่ง JS
        ownerSoftRefresh(['#pools-pane']);
    })
    .catch(() => ownerNotify('เกิดข้อผิดพลาด ไม่สามารถบันทึกได้', 'error'));
});

function deletePool(poolId, poolName) {
    ownerConfirm('ยืนยันลบกลุ่มสต็อกร่วม "' + poolName + '" ?').then(function (ok) {
        if (!ok) return;
        const formData = new FormData();
        formData.append('action', 'delete_pool');
        formData.append('pool_id', poolId);
        formData.append('csrf_token', CSRF_TOKEN);

        fetch('manage_stock.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) { ownerNotify(data.error || 'ลบไม่สำเร็จ', 'error'); return; }
            ownerNotify('ลบกลุ่มสต็อกร่วมเรียบร้อยแล้ว');
            ownerSoftRefresh(['#pools-pane']);
        })
        .catch(() => ownerNotify('เกิดข้อผิดพลาด ไม่สามารถลบได้', 'error'));
    });
}

// ผูก/ยกเลิกผูกเมนู-ท็อปปิ้งเข้ากับกลุ่มสต็อกร่วม (เลือกจาก dropdown ในการ์ดสต็อกแต่ละใบ) — อัปเดตการ์ดทันทีไม่รีหน้า
function linkItemPool(itemId, poolId) {
    const formData = new FormData();
    formData.append('action', 'link_item_pool');
    formData.append('item_id', itemId);
    formData.append('pool_id', poolId);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('manage_stock.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                ownerNotify('เกิดข้อผิดพลาด ไม่สามารถผูกกลุ่มสต็อกได้', 'error');
                return;
            }
            const pooledBox = document.getElementById('item_pooled_box_' + itemId);
            const ownBox = document.getElementById('item_own_box_' + itemId);
            const extraBtns = document.getElementById('item_stepper_extra_' + itemId);
            if (data.is_pooled) {
                document.getElementById('item_pool_name_' + itemId).textContent = data.pool_name;
                document.getElementById('item_pool_qty_' + itemId).textContent = data.qty;
                pooledBox.style.display = '';
                ownBox.style.display = 'none';
                extraBtns.style.display = 'none';
                flashUpdate(pooledBox);
            } else {
                document.getElementById('stock_display_' + itemId).textContent = data.qty;
                pooledBox.style.display = 'none';
                ownBox.style.display = '';
                extraBtns.style.display = '';
                flashUpdate(ownBox);
            }
            updateItemBadge(itemId, data.qty);
        })
        .catch(() => ownerNotify('เกิดข้อผิดพลาด ไม่สามารถผูกกลุ่มสต็อกได้', 'error'));
}

function linkToppingPool(toppingId, poolId) {
    const formData = new FormData();
    formData.append('action', 'link_topping_pool');
    formData.append('topping_id', toppingId);
    formData.append('pool_id', poolId);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('manage_stock.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                ownerNotify('เกิดข้อผิดพลาด ไม่สามารถผูกกลุ่มสต็อกได้', 'error');
                return;
            }
            const pooledBox = document.getElementById('top_pooled_box_' + toppingId);
            const ownBox = document.getElementById('top_own_box_' + toppingId);
            const extraBtns = document.getElementById('top_stepper_extra_' + toppingId);
            if (data.is_pooled) {
                document.getElementById('top_pool_name_' + toppingId).textContent = data.pool_name;
                document.getElementById('top_pool_qty_' + toppingId).textContent = data.qty;
                pooledBox.style.display = '';
                ownBox.style.display = 'none';
                extraBtns.style.display = 'none';
                flashUpdate(pooledBox);
            } else {
                document.getElementById('top_stock_display_' + toppingId).textContent = data.qty;
                pooledBox.style.display = 'none';
                ownBox.style.display = '';
                extraBtns.style.display = '';
                flashUpdate(ownBox);
            }
            updateToppingBadge(toppingId, data.qty);
        })
        .catch(() => ownerNotify('เกิดข้อผิดพลาด ไม่สามารถผูกกลุ่มสต็อกได้', 'error'));
}

function openAddToppingStockModal() {
    const form = document.getElementById('newToppingStockForm');
    form.reset();
    form.classList.remove('was-validated');
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    document.getElementById('new_topping_price').value = '0.00';
    document.getElementById('new_topping_stock_qty').value = 50;
    new bootstrap.Modal(document.getElementById('addToppingStockModal')).show();
}

document.getElementById('newToppingStockForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const nameInput = document.getElementById('new_topping_name');
    const catInput = document.getElementById('new_topping_cat_id');
    const priceInput = document.getElementById('new_topping_price');
    const qtyInput = document.getElementById('new_topping_stock_qty');
    let valid = true;

    if (!nameInput.value.trim()) { nameInput.classList.add('is-invalid'); valid = false; } else { nameInput.classList.remove('is-invalid'); }
    if (!catInput.value) { catInput.classList.add('is-invalid'); valid = false; } else { catInput.classList.remove('is-invalid'); }
    if (priceInput.value === '' || parseFloat(priceInput.value) < 0) { priceInput.classList.add('is-invalid'); valid = false; } else { priceInput.classList.remove('is-invalid'); }
    if (qtyInput.value === '' || !Number.isInteger(Number(qtyInput.value)) || Number(qtyInput.value) < 0) { qtyInput.classList.add('is-invalid'); valid = false; } else { qtyInput.classList.remove('is-invalid'); }

    if (!valid) return;

    const formData = new FormData(this);
    formData.append('action', 'add_topping');
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('manage_stock.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addToppingStockModal')).hide();
            sessionStorage.setItem('stockActiveTab', 'toppings');
            ownerNotify('เพิ่มท็อปปิ้ง/วัตถุดิบเรียบร้อยแล้ว');
            setTimeout(() => window.location.reload(), 700);
        } else {
            ownerNotify(data.error || 'เกิดข้อผิดพลาด ไม่สามารถบันทึกได้', 'error');
        }
    })
    .catch(() => ownerNotify('เกิดข้อผิดพลาด ไม่สามารถบันทึกได้', 'error'));
});

/*
 * แท็บ "รายการสินค้า" - แก้ SKU/จุดสั่งซื้อซ้ำ
 */
function openEditProductModal(p) {
    document.getElementById('editProductType').value = p.type;
    document.getElementById('editProductId').value = p.id;
    document.getElementById('editProductName').textContent = '"' + p.name + '"';
    document.getElementById('editProductSku').value = p.sku || '';
    document.getElementById('editProductReorder').value = p.reorder_point;
    new bootstrap.Modal(document.getElementById('editProductModal')).show();
}

document.getElementById('editProductForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'save_product_meta');
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('manage_stock.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (!data.success) { ownerNotify(data.error || 'เกิดข้อผิดพลาด ไม่สามารถบันทึกได้', 'error'); return; }
            bootstrap.Modal.getInstance(document.getElementById('editProductModal')).hide();
            ownerNotify('บันทึกข้อมูลสินค้าเรียบร้อยแล้ว');
            sessionStorage.setItem('stockActiveTab', 'products');
            setTimeout(() => window.location.reload(), 600);
        })
        .catch(() => ownerNotify('เกิดข้อผิดพลาด ไม่สามารถบันทึกได้', 'error'));
});

/*
 * แท็บ "บันทึกรับ-จ่าย"
 */
// รีโหลดหน้าตามช่วงวันที่ที่เลือก (ฟอร์ม GET ธรรมดา) แต่จำแท็บนี้ไว้ก่อนรีโหลด ไม่งั้นหลังค้นหาจะกระโดดกลับ
// ไปแท็บ "เมนูอาหารหลัก" เหมือนโหลดหน้าใหม่ปกติ
document.getElementById('txnFilterForm').addEventListener('submit', function () {
    sessionStorage.setItem('stockActiveTab', 'transactions');
});

function printTransactions() {
    const from = document.querySelector('input[name="txn_from"]').value;
    const to = document.querySelector('input[name="txn_to"]').value;
    window.open('print_stock_transactions.php?from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to), '_blank', 'width=1000,height=700');
}
</script>

<?php include '../includes/footer_owner.php'; ?>
