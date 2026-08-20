<?php
// 1. ตรวจสอบการ Login และสิทธิ์ Admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// เช็คว่าถ้าไม่ใช่ admin ให้เด้งกลับไปหน้า login ทันที
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
// 2. ตั้งค่าหัวข้อหน้าเว็บ
$page_title = isset($title) ? $title . " | ระบบจัดการหลังบ้าน" : "ผู้ดูแลระบบ - Raauaibaan";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Mitr:wght@400;600&family=Sarabun:wght@300;400;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
</head>
<body>