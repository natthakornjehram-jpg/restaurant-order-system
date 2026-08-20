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

    <style>
        :root {
            --admin-dark: #212529;
            --admin-accent: #343a40;
            --danger-red: #dc3545;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa; /* พื้นหลังสีเทาอ่อนแบบ Dashboard */
            color: #212529;
        }

        h1, h2, h3, .navbar-brand {
            font-family: 'Mitr', sans-serif;
        }

        /* ตกแต่งตารางในหน้า Admin */
        .table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .table thead {
            background-color: var(--admin-dark);
            color: white;
        }

        /* ตกแต่ง Sidebar หรือ Card สรุปผล */
        .admin-card {
            border: none;
            border-radius: 15px;
            transition: 0.3s;
        }

        .admin-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        /* สีพิเศษสำหรับ Admin */
        .bg-admin-dark { background-color: var(--admin-dark) !important; }
        .text-admin { color: var(--admin-dark); }
    </style>
</head>
<body>