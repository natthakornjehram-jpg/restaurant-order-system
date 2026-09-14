<?php
// qr_table/cart_dinein.php
// ตะกร้าถูกย้ายไปรวมเป็นแท็บ "รายการที่สั่ง" ในหน้า menu_dinein.php แล้ว
// ไฟล์นี้เก็บไว้เผื่อมีลิงก์เก่า/บุ๊กมาร์กเก่าที่ยังชี้มาที่นี่อยู่
session_start();
require_once '../includes/db.php';

$table_no = $_SESSION['table_number'] ?? '';
header("Location: menu_dinein.php?table=" . urlencode($table_no) . "&tab=cart");
exit;
