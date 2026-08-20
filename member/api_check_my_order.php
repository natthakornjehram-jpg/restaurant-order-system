<?php
// member/api_check_my_order.php
session_start();
error_reporting(0);
require_once '../includes/db.php';
header('Content-Type: application/json');

// 1. เช็กว่าลูกค้ามี Session โต๊ะหรือไม่ (ถ้าไม่มีแสดงว่าไม่ใช่ลูกค้า Dine-in)
if (!isset($_SESSION['table_id'])) {
    echo json_encode(['food_ready' => false, 'error' => 'No table session']);
    exit;
}

$table_id = intval($_SESSION['table_id']);

// 2. ค้นหาออเดอร์ของโต๊ะนี้ ที่สถานะเป็น 'ready' (เตรียมเสิร์ฟ)
// ดึงเฉพาะของ "วันนี้" ป้องกันการไปดึงบิลเก่าข้ามวันมาเตือนซ้ำ
$stmt = $conn->prepare("SELECT order_id 
                        FROM orders 
                        WHERE table_id = ? 
                        AND order_status = 'ready' 
                        AND payment_status = 'unpaid' 
                        AND DATE(created_at) = CURDATE() 
                        LIMIT 1");
$stmt->bind_param("i", $table_id);
$stmt->execute();
$res = $stmt->get_result();

// 3. ถ้าเจอออเดอร์ที่พร้อมเสิร์ฟ ส่งค่า true กลับไปให้ JS ทำงาน
if ($res->num_rows > 0) {
    echo json_encode(['food_ready' => true]);
} else {
    echo json_encode(['food_ready' => false]);
}

$stmt->close();
$conn->close();
?>