<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/csrf.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// สร้างตะกร้าว่างๆ ถ้ายังไม่เคยมี
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ปลายทางที่อนุญาตให้เด้งกลับหลังแก้ตะกร้า (จำกัดเฉพาะ path เดิม + querystring ต่อท้ายเท่านั้น กัน open redirect)
function cart_action_return_url() {
    $return_url = $_POST['return_url'] ?? 'cart.php';
    $allowed_paths = ['cart.php', '../qr_table/cart_dinein.php', '../qr_table/menu_dinein.php'];
    foreach ($allowed_paths as $path) {
        $len = strlen($path);
        if (substr($return_url, 0, $len) === $path
            && (strlen($return_url) === $len || $return_url[$len] === '?')) {
            return $return_url;
        }
    }
    return 'cart.php';
}

// 🟢 กรณี: กดปุ่ม "เพิ่มลงตะกร้า" จากหน้า menu.php
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? '')) {
    $item_id = intval($_POST['item_id']);
    // จำกัดสูงสุด 20 จานเหมือนปุ่ม +/- ในตะกร้า (เดิมจำกัดแค่ฝั่ง client ผ่าน max="20" ของ input เท่านั้น)
    $quantity = max(1, min(20, intval($_POST['quantity'])));
    $note = trim($_POST['note']);
    $toppings = $_POST['toppings'] ?? []; // รับ array ของ topping_id ที่ลูกค้าติ๊กเลือก

    // ดึงข้อมูลอาหารหลัก
    $stmt = $conn->prepare("SELECT name, price, image_url FROM item WHERE item_id = ? AND is_active = 1");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();

    if ($item) {
        $base_price = $item['price'];
        $topping_price_total = 0;
        $topping_names_array = [];
        $topping_ids_array = [];

        // ถ้ามีการเลือกท็อปปิ้ง ให้ไปดึงชื่อและราคาของท็อปปิ้งมาบวกเพิ่ม
        // ต้องเช็คผ่าน menu_toppings ว่าท็อปปิ้งนั้นผูกกับเมนูนี้จริงและยังเปิดใช้งานอยู่ ป้องกันลูกค้าส่ง topping_id ของเมนูอื่นเข้ามา
        if (!empty($toppings)) {
            $toppings = array_map('intval', $toppings);
            $placeholders = implode(',', array_fill(0, count($toppings), '?'));
            $types = str_repeat('i', count($toppings));

            $stmt_top = $conn->prepare("SELECT t.topping_id, t.topping_name, t.price
                FROM topping t
                JOIN menu_toppings mt ON mt.topping_id = t.topping_id
                WHERE mt.item_id = ? AND t.is_active = 1 AND t.topping_id IN ($placeholders)");
            $stmt_top->bind_param("i" . $types, $item_id, ...$toppings);
            $stmt_top->execute();
            $res_top = $stmt_top->get_result();
            
            while ($t = $res_top->fetch_assoc()) {
                $topping_price_total += $t['price'];
                $topping_names_array[] = $t['topping_name'];
                $topping_ids_array[] = $t['topping_id'];
            }
        }

        // คำนวณราคาต่อจาน (ราคาอาหาร + ราคาท็อปปิ้งทั้งหมดที่เลือก)
        $unit_price = $base_price + $topping_price_total;

        // แพ็คข้อมูลลงกล่อง (Array) เตรียมใส่ตะกร้า
        $cart_item = [
            'item_id' => $item_id,
            'name' => $item['name'],
            'image' => $item['image_url'],
            'price' => $unit_price, // ราคาต่อจานที่รวมท็อปปิ้งแล้ว
            'quantity' => $quantity,
            'note' => $note,
            'topping_names' => implode(', ', $topping_names_array), // รวมชื่อท็อปปิ้งเป็นข้อความยาวๆ โชว์ให้ลูกค้าดู
            'topping_ids' => $topping_ids_array // เก็บ ID ท็อปปิ้งไว้เผื่อตอนส่งออเดอร์ลง Database
        ];

        // หย่อนกล่องลงตะกร้า Session
        $_SESSION['cart'][] = $cart_item;

        // เด้งกลับไปหน้าเมนู เพื่อให้สั่งอย่างอื่นต่อ
        // ถ้าเป็นแขกที่สแกน QR โต๊ะอยู่ ต้องกลับไปหน้าเมนูของโต๊ะ (ไม่งั้น menu.php จะล้าง session โต๊ะทิ้ง)
        if (isset($_SESSION['table_id'])) {
            header("Location: ../qr_table/menu_dinein.php?table=" . urlencode($_SESSION['table_number'] ?? ''));
        } else {
            header("Location: ../menu.php");
        }
        exit;
    }
}
// 🔴 กรณี: กดปุ่ม "ลบรายการ" ในหน้า cart.php / รายการที่สั่ง (แท็บตะกร้าใน menu_dinein.php)
elseif ($action === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? '') && isset($_POST['id'])) {
    $id = intval($_POST['id']); // $id คือลำดับของในตะกร้า (0, 1, 2...)

    if (isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]); // ลบของชิ้นนั้นทิ้ง
        $_SESSION['cart'] = array_values($_SESSION['cart']); // จัดเรียงลำดับใหม่ให้สวยงาม
    }

    header("Location: " . cart_action_return_url());
    exit;
}
// 🔼🔽 กรณี: กดปุ่ม +/- ปรับจำนวนในแท็บ "รายการที่สั่ง"
elseif (($action === 'increase' || $action === 'decrease') && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? '') && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    if (isset($_SESSION['cart'][$id])) {
        if ($action === 'increase') {
            $_SESSION['cart'][$id]['quantity'] = min(20, $_SESSION['cart'][$id]['quantity'] + 1);
        } else {
            $_SESSION['cart'][$id]['quantity'] -= 1;
            if ($_SESSION['cart'][$id]['quantity'] < 1) {
                // ลดจนเหลือ 0 ให้ถือว่าลบรายการนั้นออกไปเลย
                unset($_SESSION['cart'][$id]);
                $_SESSION['cart'] = array_values($_SESSION['cart']);
            }
        }
    }

    header("Location: " . cart_action_return_url());
    exit;
}

// ถ้างงๆ ให้กลับไปหน้าเมนู
header("Location: ../menu.php");
exit;
?>