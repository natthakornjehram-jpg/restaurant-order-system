<?php
// owner/api_toggle_menu_status.php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';

// สลับสถานะพร้อมขายของเมนู (แยกออกมาจาก manage_menu.php)
$id = intval($_POST['update_status_id'] ?? 0);
$status = intval($_POST['new_status_val'] ?? 0);

$stmt = $conn->prepare("UPDATE item SET is_active = ? WHERE item_id = ?");
$stmt->bind_param("ii", $status, $id);
$stmt->execute();

echo "success";
