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

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
?>
