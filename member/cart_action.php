<?php
session_start();
require_once '../includes/db.php';

// 1. เช็กสิทธิ์ว่าล็อกอินอยู่ไหม
if (!isset($_SESSION['customer_id'])) {
    header("Location: ../login_customer.php");
    exit;
}

$action = $_GET['action'] ?? '';

// สร้างตะกร้าว่างๆ ถ้ายังไม่เคยมี
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// 🟢 กรณี: กดปุ่ม "เพิ่มลงตะกร้า" จากหน้า menu.php
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = intval($_POST['item_id']);
    $quantity = max(1, intval($_POST['quantity']));
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
        if (!empty($toppings)) {
            $placeholders = implode(',', array_fill(0, count($toppings), '?'));
            $types = str_repeat('i', count($toppings));
            
            $stmt_top = $conn->prepare("SELECT topping_id, topping_name, price FROM topping WHERE topping_id IN ($placeholders)");
            $stmt_top->bind_param($types, ...$toppings);
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
        header("Location: ../menu.php");
        exit;
    }
} 
// 🔴 กรณี: กดปุ่ม "ลบรายการ" ในหน้า cart.php
elseif ($action === 'remove' && isset($_GET['id'])) {
    $id = intval($_GET['id']); // $id คือลำดับของในตะกร้า (0, 1, 2...)
    
    if (isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]); // ลบของชิ้นนั้นทิ้ง
        $_SESSION['cart'] = array_values($_SESSION['cart']); // จัดเรียงลำดับใหม่ให้สวยงาม
    }
    
    // เด้งกลับไปหน้าตะกร้า
    header("Location: cart.php");
    exit;
}

// ถ้างงๆ ให้กลับไปหน้าเมนู
header("Location: ../menu.php");
exit;
?>