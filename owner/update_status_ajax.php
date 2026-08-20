<?php
// owner/update_status_ajax.php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';
header('Content-Type: application/json');

// 2. รับค่าจาก Javascript
$id = $_POST['id'] ?? '';
$type = $_POST['type'] ?? '';
$new_status = isset($_POST['new_status']) ? intval($_POST['new_status']) : 0;

$success = false;

// 3. แยกอัปเดตตามปุ่มที่กดมา
if ($type === 'shop_status') {
    // 🟢 กดจากหน้า Dashboard (เปิด/ปิด ร้านและออนไลน์)
    // เช็กว่ากดปุ่ม หน้าร้าน(shop) หรือ ออนไลน์(online)
    $column = ($id === 'shop') ? 'is_shop_open' : 'is_online_open';
    
    $stmt = $conn->prepare("UPDATE owner SET $column = ? WHERE owner_id = ?");
    $stmt->bind_param("ii", $new_status, $owner_id);
    $success = $stmt->execute();

} elseif ($type === 'menu' || $type === 'item') {
    // 🟢 กดจากหน้า จัดการเมนูอาหาร
    $item_id = intval($id);
    $stmt = $conn->prepare("UPDATE item SET is_active = ? WHERE item_id = ?");
    $stmt->bind_param("ii", $new_status, $item_id);
    $success = $stmt->execute();

} elseif ($type === 'topping') {
    // 🟢 กดจากหน้า จัดการท็อปปิ้ง
    $topping_id = intval($id);
    $stmt = $conn->prepare("UPDATE topping SET is_active = ? WHERE topping_id = ?");
    $stmt->bind_param("ii", $new_status, $topping_id);
    $success = $stmt->execute();
}

// 4. ส่งคำตอบกลับไปให้ SweetAlert2 ทำงาน
if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'ระบบฐานข้อมูลขัดข้อง: ' . $conn->error]);
}
?>