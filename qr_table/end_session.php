<?php
// qr_table/end_session.php
// เคลียร์ session โต๊ะฝั่งเซิร์ฟเวอร์ หลังร้านปิดบิลแล้ว เรียกจาก JS ก่อนเด้งออกจากหน้าเว็บ
// กันลูกค้ากดปุ่มย้อนกลับแล้วสั่งอาหารซ้ำ ต้องสแกน QR ใหม่เท่านั้น
session_start();
unset($_SESSION['table_id']);
unset($_SESSION['table_number']);
unset($_SESSION['order_type']);
unset($_SESSION['has_ordered']);
unset($_SESSION['cart']);

// ตั้งค่าตราปิด session ไว้ (ไม่ unset) กัน menu_dinein.php ผูก session โต๊ะกลับคืนให้อัตโนมัติ
// ตอนกดปุ่มย้อนกลับไปที่ URL เดิม (?table=...) ซึ่งหน้าตาเหมือนสแกน QR ใหม่ทุกอย่างในมุมของ server
// ตราจะหายไปเองตาม PHP session หมดอายุ (ปกติ ~24 นาทีไม่มีการใช้งาน) ถึงตอนนั้นสแกนใหม่ได้ตามปกติ
$_SESSION['session_ended'] = true;

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
?>
