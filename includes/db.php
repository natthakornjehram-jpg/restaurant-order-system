<?php
// db.php

// ⚠️ ตอนย้ายขึ้นโฮสต์จริง แก้ 4 บรรทัดนี้ตามข้อมูลที่โฮสต์ให้มา
// (เช่น InfinityFree จะให้ host แบบ sql2xx.infinityfree.com, user/db แบบ epizXXXXXXX_xxxxx)
$host = "localhost";
$user = "root";
$pass = "";
$db   = "restaurant_db";

// 1. เชื่อมต่อฐานข้อมูล
$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. เช็คว่ากำลังรันบนเครื่อง dev (localhost) หรือโฮสต์จริง
//    บนเครื่อง dev: โชว์ error เต็มๆ เพื่อ debug ง่าย
//    บนโฮสต์จริง: ปิดการโชว์ error กัน path/SQL หลุดให้คนอื่นเห็น
$is_localhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])
    || str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost:');

if ($is_localhost) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// 3. กำหนด BASE_URL (Path หลักของโปรเจกต์) แบบอัตโนมัติ
//    คำนวณจากตำแหน่งไฟล์จริงเทียบกับ document root เลย ไม่ต้องแก้มือตอนย้ายโฮสต์/โดเมน
if (!defined('BASE_URL')) {
    $project_root = str_replace('\\', '/', dirname(__DIR__));
    $doc_root = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
    $base_path = $doc_root !== '' ? str_replace($doc_root, '', $project_root) : '';
    define('BASE_URL', rtrim($base_path, '/') . '/');
}

// 4. ดึงข้อมูลการตั้งค่าร้านค้า (จากตาราง owner)
$store = [];
$query = "SELECT * FROM owner WHERE owner_id = 1 LIMIT 1";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $store = $result->fetch_assoc();
} else {
    // ค่า Default เผื่อในฐานข้อมูลยังไม่มีข้อมูล
    $store = [
        'restaurant_name' => 'My Restaurant',
        'is_online_open' => 1, // 1 = เปิดรับกลับบ้าน, 0 = ปิดรับกลับบ้าน
        'is_shop_open' => 1
    ];
}
?>
