<?php
// db.php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "restaurant_db"; // ✅ แก้ให้ตรงกับฐานข้อมูลจริงของคุณ

// 1. เชื่อมต่อฐานข้อมูล
$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. กำหนด BASE_URL (Path หลักของโปรเจกต์)
if (!defined('BASE_URL')) {
    // ปรับให้ตรงกับโฟลเดอร์ของคุณนฐกร (อ้างอิงจาก URL ในวิดีโอก่อนหน้า)
    define('BASE_URL', '/664244102/restaurant/'); 
}

// 3. ดึงข้อมูลการตั้งค่าร้านค้า (จากตาราง owner)
$store = [];
$query = "SELECT * FROM owner WHERE owner_id = 1 LIMIT 1";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $store = $result->fetch_assoc();
} else {
    // ค่า Default เผื่อในฐานข้อมูลยังไม่มีข้อมูล
    $store = [
        'restaurant_name' => 'My Restaurant',
        'is_online_open' => 1, // 1 = เปิดออนไลน์, 0 = ปิดออนไลน์
        'is_shop_open' => 1
    ];
}

/**
 * ฟังก์ชันช่วยเช็คสถานะออนไลน์ (Helper Function)
 * ใช้เรียกในหน้าของลูกค้า (Customer) เพื่อกันไม่ให้สั่งอาหารตอนร้านปิด
 */
function checkOnlineStatus($status) {
    if ($status == 0) {
        echo "<div style='text-align:center; margin-top:100px; font-family: sans-serif;'>";
        echo "<h2 style='color: #dc3545;'>🛑 ขออภัย ขณะนี้ร้านปิดรับออเดอร์ออนไลน์ชั่วคราว</h2>";
        echo "<p style='color: #666;'>กรุณาสั่งอาหารที่หน้าเคาน์เตอร์ หรือลองใหม่อีกครั้งในภายหลัง</p>";
        echo "<br>";
        echo "<a href='".BASE_URL."' style='padding: 10px 25px; background: #007bff; color: #fff; text-decoration: none; border-radius: 50px; font-weight: bold;'>กลับหน้าหลัก</a>";
        echo "</div>";
        exit; // หยุดการทำงานของหน้าเว็บทันที
    }
}
?>