<?php
// owner/api_update_topping_status.php
session_start();
error_reporting(0);
include '../includes/db.php';
require_once 'auth_owner.php';

header('Content-Type: application/json');

// 1. เช็กสิทธิ์การล็อกอิน (ใช้ owner_id ตามระบบร้านเดี่ยว)
if (!isset($_SESSION['owner_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// 2. รับค่าที่ส่งมาจาก JavaScript (AJAX)
// อิงตามชื่อตัวแปรใน FormData: topping_id และ new_status
$id = isset($_POST['topping_id']) ? intval($_POST['topping_id']) : 0;
$status = isset($_POST['new_status']) ? intval($_POST['new_status']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

// 3. อัปเดตสถานะในตาราง topping (เปลี่ยนจาก is_available เป็น is_active ให้ตรงกับ DB ของคุณ)
$sql = "UPDATE topping SET is_active = ? WHERE topping_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $status, $id);

if ($stmt->execute()) {
    // ส่งผลลัพธ์กลับไปให้หน้าจัดการท็อปปิ้ง
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'ไม่สามารถอัปเดตสถานะได้: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>
