<?php
// admin/approve_action.php
include 'auth_admin.php';
require_once '../includes/db.php';

$target_id = intval($_GET['id']);
$status = intval($_GET['status']); // 1 = อนุมัติ, 2 = ปฏิเสธ

if ($target_id > 0) {
    // อัปเดตสถานะ is_approved
    $stmt = $conn->prepare("UPDATE users SET is_approved = ? WHERE user_id = ? AND role = 'owner'");
    $stmt->bind_param("ii", $status, $target_id);
    
    if ($stmt->execute()) {
        header("Location: manage_approvals.php?msg=success");
    } else {
        header("Location: manage_approvals.php?msg=error");
    }
}
exit;