<?php
// includes/header_customer.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// กันหน้านี้โดนแคชไว้ในเบราว์เซอร์ (เจอปัญหาเบราว์เซอร์โหลด Bootstrap เวอร์ชันเก่าค้างจากแคช)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sookjai Order - สั่งเลยอร่อยทุกอย่าง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Mitr:wght@400;600&family=Sarabun:wght@300;400;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/nav.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer.css">
</head>
<body>
