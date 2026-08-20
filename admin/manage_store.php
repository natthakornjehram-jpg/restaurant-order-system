<?php
include 'auth_admin.php';
require_once '../includes/db.php';

if (isset($_GET['id']) && isset($_GET['action'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];
    $status = ($action === 'approve') ? 'approved' : 'rejected';

    $stmt = $conn->prepare("UPDATE restaurants SET status = ? WHERE restaurant_id = ?");
    $stmt->bind_param("si", $status, $id);
    
    if ($stmt->execute()) {
        header("Location: dashboard.php?msg=success");
    } else {
        header("Location: dashboard.php?msg=error");
    }
}