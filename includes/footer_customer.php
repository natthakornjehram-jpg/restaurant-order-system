<?php
// includes/header_customer.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raauaibaan - สั่งเลยอร่อยทุกอย่าง</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Mitr:wght@400;600&family=Sarabun:wght@300;400;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --main-brown: #795548;
            --dark-brown: #3e2723;
            --light-brown: #d7ccc8;
            --bg-soft: #fdfaf5; /* สีครีมเบจจางๆ สบายตา */
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--bg-soft);
            color: var(--dark-brown);
            margin: 0;
            padding: 0;
        }

        h1, h2, h3, h4, h5, h6, .font-mitr {
            font-family: 'Mitr', sans-serif;
        }

        /* ตกแต่ง Card ให้เข้าธีมน้ำตาล */
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(62, 39, 35, 0.05);
        }

        /* ปุ่มสีน้ำตาลหลัก */
        .btn-primary-brown {
            background-color: var(--main-brown);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 10px 25px;
            transition: 0.3s;
        }

        .btn-primary-brown:hover {
            background-color: var(--dark-brown);
            color: white;
            transform: translateY(-2px);
        }

        /* ปรับแต่ง Scrollbar ให้ดูแพง */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-soft);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--main-brown);
            border-radius: 10px;
        }
    </style>
</head>
<body></body>