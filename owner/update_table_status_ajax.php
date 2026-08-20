<?php
session_start();
include '../includes/db.php';
require_once 'auth_owner.php';
header('Content-Type: application/json');

$table_id = $_POST['table_id'] ?? 0;

if ($table_id > 0) {
    // อัปเดตตาราง restauranttable ให้สถานะเป็นว่าง
    $sql = "UPDATE restauranttable SET status = 'available' WHERE table_id = " . intval($table_id);
    if ($conn->query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
}
?>