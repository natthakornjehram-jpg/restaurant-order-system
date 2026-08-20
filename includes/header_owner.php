<?php
// includes/header_owner.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db.php';

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
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --bg-body: #fdfaf5; /* สีครีมเบจอ่อน */
            --primary-brown: #795548; /* น้ำตาลหลัก */
            --dark-brown: #3e2723; /* น้ำตาลเข้ม/ดำ */
            --accent-soft: #d7ccc8; /* น้ำตาลอ่อนพาสเทล */
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--bg-body);
            color: var(--dark-brown);
        }

        h1, h2, h3, h4, h5, .font-mitr {
            font-family: 'Mitr', sans-serif;
        }

        /* ตกแต่ง Scrollbar ให้เป็นสีน้ำตาลเข้าชุด */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-body);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--primary-brown);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--dark-brown);
        }

        /* คลาสส่วนกลางสำหรับ Card ในหน้า Owner */
        .card-owner {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(62, 39, 35, 0.05);
            background-color: #ffffff;
        }

        .btn-brown {
            background-color: var(--primary-brown);
            color: white;
            border-radius: 50px;
            transition: 0.3s;
        }
        .btn-brown:hover {
            background-color: var(--dark-brown);
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>