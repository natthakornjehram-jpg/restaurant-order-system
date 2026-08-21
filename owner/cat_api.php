<?php
// owner/cat_api.php
session_start();
error_reporting(0);
require_once '../includes/db.php';
require_once 'auth_owner.php';
header('Content-Type: application/json');

// ไม่ต้องดึงหา restaurant_id เพราะเป็นระบบร้านเดียว
$action = isset($_GET['action']) ? $_GET['action'] : '';

// --- ดึงรายการหมวดหมู่ ---
if ($action == 'list') {
    // ดึงข้อมูลจากตาราง category
    $stmt = $conn->prepare("SELECT * FROM category ORDER BY category_id ASC");
    $stmt->execute();
    $res = $stmt->get_result();
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    $stmt->close();
    exit;
}

// --- เพิ่มหมวดหมู่ ---
if ($action == 'add') {
    $name = trim($_POST['category_name']);
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อหมวดหมู่']);
        exit;
    }

    // บันทึกลงตาราง category (มีแค่ category_name)
    $stmt = $conn->prepare("INSERT INTO category (category_name) VALUES (?)");
    $stmt->bind_param("s", $name);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึก: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// --- ลบหมวดหมู่ ---
if ($action == 'delete') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    // เช็คว่ามีเมนูค้างอยู่ไหม (ในฐานข้อมูลของคุณ ตารางเมนูชื่อ 'item')
    $check_stmt = $conn->prepare("SELECT item_id FROM item WHERE category_id = ? LIMIT 1");
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_res = $check_stmt->get_result();
    
    if ($check_res->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'ลบไม่ได้! มีเมนูอาหารค้างอยู่ในหมวดหมู่นี้']);
    } else {
        // ลบข้อมูลจากตาราง category
        $del_stmt = $conn->prepare("DELETE FROM category WHERE category_id = ?");
        $del_stmt->bind_param("i", $id);
        
        if ($del_stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ลบข้อมูลไม่สำเร็จ']);
        }
        $del_stmt->close();
    }
    $check_stmt->close();
    exit;
}

// --- เปิด/ปิด หมวดหมู่ (Toggle) - ซ่อนจากเมนูลูกค้าโดยไม่ต้องลบ ---
if ($action == 'toggle') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $stmt = $conn->prepare("UPDATE category SET is_active = NOT is_active WHERE category_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'อัปเดตสถานะไม่สำเร็จ']);
    }
    $stmt->close();
    exit;
}

// ถ้าระบุ action ไม่ถูกต้อง
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
