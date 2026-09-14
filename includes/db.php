<?php
// db.php

// 0. ตั้งเขตเวลาไว้ตรงกลางจุดเดียว (ทุกหน้าที่ require db.php จะได้เวลาไทยเสมอ ไม่ว่าจะรันบนเครื่องไหน)
//    สำคัญมากตอนย้ายไป hosting จริง เพราะ php.ini ของแต่ละโฮสต์ตั้งเขตเวลาไม่เหมือนกัน ถ้าไม่ตั้งเอง
//    วันที่ในออเดอร์/รายงานยอดขาย (created_at, CURDATE() ฝั่ง PHP ฯลฯ) อาจเพี้ยนไปจากที่ตั้งใจ
date_default_timezone_set('Asia/Bangkok');

//เชื่อมต่อฐานข้อมูล MySQL
$host = "localhost";
$user = "root";
$pass = "";
$db   = "restaurant_db1";

// 1. เช็คว่ากำลังรันบนเครื่อง dev (localhost) หรือโฮสต์จริง
//    บนเครื่อง dev: โชว์ error เต็มๆ เพื่อ debug ง่าย
//    บนโฮสต์จริง: ปิดการโชว์ error กัน path/SQL หลุดให้คนอื่นเห็น
//    ใช้ REMOTE_ADDR (IP ที่ต่อเข้ามาจริงตาม TCP connection) แทน HTTP_HOST เพราะ HTTP_HOST
//    เป็นค่าที่ client ส่งมาเอง ปลอมเป็น "Host: localhost" ตอนยิงเข้าโฮสต์จริงได้ ซึ่งจะเปิด error
//    แบบเต็มและรัน migration ด้านล่างใส่ฐานข้อมูลจริงโดยไม่ตั้งใจ ส่วน REMOTE_ADDR ปลอมผ่าน header ไม่ได้
//    ทำก่อนเชื่อมต่อ DB เพื่อให้ error ตอนเชื่อมต่อไม่สำเร็จก็ถูกซ่อนบนโฮสต์จริงด้วยเช่นกัน
$is_localhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']);

if ($is_localhost) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// 2. เชื่อมต่อฐานข้อมูล
$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die($is_localhost ? "Connection failed: " . $conn->connect_error : "Service temporarily unavailable.");
}

// 🟢 อัปเดตโครงสร้างตารางอัตโนมัติหากยังไม่มีคอลัมน์ใหม่ (Self-healing DB Migration)
//    รันเฉพาะบนเครื่อง dev (localhost) เท่านั้น เพราะ:
//    1) ฐานข้อมูลที่ import จาก database/restaurant_db.sql ตัวล่าสุดมีคอลัมน์พวกนี้ครบอยู่แล้ว ไม่ต้องรันซ้ำ
//    2) DB user บน hosting จริงหลายเจ้าไม่ได้ให้สิทธิ์ ALTER/CREATE TABLE เต็มๆ ถ้ารันทุก request จะ error เงียบๆ
//       (ถูก catch ทิ้ง) ทุกครั้งโดยเปล่าประโยชน์ แถมเปลืองเวลา query โดยไม่จำเป็นด้วย
if ($is_localhost) {
    $migrations = [
        "ALTER TABLE restauranttable ADD COLUMN join_code VARCHAR(10) DEFAULT NULL",
        // โทเค็นลับต่อโต๊ะ ฝังไว้ใน QR code (ดูตอนสร้าง QR ที่ manage_tables.php) กันคนเดาเลขโต๊ะ (เช่น A1, A2)
        // แล้วยิง URL เข้าเมนูของโต๊ะอื่นตรงๆ โดยไม่ได้สแกน QR จริง (ดูการตรวจสอบที่ qr_table/menu_dinein.php)
        "ALTER TABLE restauranttable ADD COLUMN qr_token VARCHAR(32) DEFAULT NULL",
        "UPDATE restauranttable SET qr_token = SUBSTRING(MD5(RAND()), 1, 16) WHERE qr_token IS NULL OR qr_token = ''",
        "ALTER TABLE item ADD COLUMN stock_qty INT NOT NULL DEFAULT 50",
        "ALTER TABLE item ADD COLUMN use_stock TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE topping ADD COLUMN stock_qty INT NOT NULL DEFAULT 50",
        "ALTER TABLE topping ADD COLUMN use_stock TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE orders ADD COLUMN daily_order_no INT DEFAULT NULL",
        "ALTER TABLE orders ADD COLUMN slip_resubmitted TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE item ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE topping_categories ADD COLUMN sort_order INT NOT NULL DEFAULT 0",
        "ALTER TABLE owner DROP COLUMN is_online_open",
        "CREATE TABLE IF NOT EXISTS login_attempts (
            attempt_id INT NOT NULL AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (attempt_id),
            KEY username (username, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        // ตัวนับจำนวนครั้งที่กรอก OTP ผิดแบบผูกกับเบอร์โทร (ไม่ใช่ session) กัน bypass ด้วยการล้างคุกกี้แล้วเริ่มนับใหม่
        "CREATE TABLE IF NOT EXISTS otp_verify_attempts (
            attempt_id INT NOT NULL AUTO_INCREMENT,
            phone VARCHAR(20) NOT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (attempt_id),
            KEY phone (phone, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        // ตัวนับจำนวนครั้งที่กรอกรหัสร่วมโต๊ะผิดแบบผูกกับโต๊ะ กันการเดารหัส 4 หลัก (9000 ความเป็นไปได้) แบบสคริปต์
        "CREATE TABLE IF NOT EXISTS join_pin_attempts (
            attempt_id INT NOT NULL AUTO_INCREMENT,
            table_id INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (attempt_id),
            KEY table_id (table_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ];

    foreach ($migrations as $sql) {
        try {
            $conn->query($sql);
        } catch (Throwable $e) {
            // Ignore duplicate column errors or existing migration steps
        }
    }
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
        'is_shop_open' => 1
    ];
}
?>
