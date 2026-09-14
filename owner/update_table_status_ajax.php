<?php
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';
require_once '../includes/csrf.php';
header('Content-Type: application/json');

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$table_id = intval($_POST['table_id'] ?? 0);

if ($table_id > 0) {
    // อัปเดตตาราง restauranttable ให้สถานะเป็นว่าง + ล้างรหัสร่วมโต๊ะเดิมทิ้งไปด้วยเสมอ
    // (เดิมไม่ได้ล้าง join_code ทำให้ลูกค้ารอบใหม่ที่มาสแกนโต๊ะนี้ต่อ โดนขอรหัสเก่าที่ไม่มีใครรู้แล้ว
    // ปุ่มนี้เลยเป็นทางออกที่เจ้าของร้านใช้ล้างโต๊ะให้สะอาดได้เองอยู่แล้วเวลาเจอเคสแปลกๆ เช่น
    // มีออเดอร์หลอกๆ ค้างล็อกโต๊ะที่ยังว่างอยู่จริง ไม่ต้องเพิ่มขั้นตอนใหม่ให้เจ้าของร้านทำเลย)
    $stmt = $conn->prepare("UPDATE restauranttable SET status = 'available', join_code = NULL WHERE table_id = ?");
    $stmt->bind_param("i", $table_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการอัปเดตสถานะโต๊ะ']);
    }
}
?>