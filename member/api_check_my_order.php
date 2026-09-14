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
$food_ready = $res->num_rows > 0;
$stmt->close();

// 4. สร้างค่าสรุปสถานะออเดอร์ทั้งหมดของโต๊ะนี้ (ยังไม่จ่ายเงิน) ไว้เทียบว่ามีการเปลี่ยนแปลงไหม
// ให้หน้าบิล (my_bill.php) รีเฟรชตัวเองอัตโนมัติเมื่อสถานะออเดอร์ไหนก็ตามเปลี่ยน ไม่ต้องรอแค่ "พร้อมเสิร์ฟ"
$status_stmt = $conn->prepare("SELECT order_id, order_status FROM orders WHERE table_id = ? AND payment_status = 'unpaid' ORDER BY order_id ASC");
$status_stmt->bind_param("i", $table_id);
$status_stmt->execute();
$status_res = $status_stmt->get_result();
$parts = [];
while ($row = $status_res->fetch_assoc()) {
    $parts[] = $row['order_id'] . ':' . $row['order_status'];
}
$status_stmt->close();

echo json_encode([
    'food_ready' => $food_ready,
    'status_hash' => implode(',', $parts)
]);
$conn->close();
?>