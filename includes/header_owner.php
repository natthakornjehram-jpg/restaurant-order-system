<?php
// includes/header_owner.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db.php';

// กันหน้านี้โดนแคชไว้ในเบราว์เซอร์ (เจอปัญหาเบราว์เซอร์โหลด Bootstrap เวอร์ชันเก่าค้างจากแคช)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// ดึงชื่อร้านมาแสดงบน Title Bar (ดึงจากตาราง owner โดยตรง)
$owner_id = $_SESSION['owner_id'] ?? 0;
$stmt_h = $conn->prepare("SELECT restaurant_name FROM owner WHERE owner_id = ? LIMIT 1");
$stmt_h->bind_param("i", $owner_id);
$stmt_h->execute();
$header_res = $stmt_h->get_result()->fetch_assoc();
$display_name = !empty($header_res['restaurant_name']) ? $header_res['restaurant_name'] : 'ระบบจัดการร้านอาหาร';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($display_name) ?> - Owner System</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Mitr:wght@400;600&family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/owner.css">
</head>
<body>