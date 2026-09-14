<?php
// includes/owner_flash.php
// แสดงข้อความแจ้งเตือนที่ค้างไว้ใน session (หลังบันทึก/ลบข้อมูลแล้ว redirect กลับมาหน้าเดิม)
// รวมไว้ที่เดียว กันโค้ดซ้ำๆ กันในทุกหน้าจัดการข้อมูลฝั่งเจ้าของร้าน
// ต้อง include ไฟล์นี้ "หลัง" footer_owner.php เท่านั้น เพราะ ownerNotify() ถูกประกาศไว้ที่นั่น
if (isset($_SESSION['success_msg'])):
?>
<script>ownerNotify(<?= json_encode($_SESSION['success_msg']) ?>);</script>
<?php
    unset($_SESSION['success_msg']);
endif;

if (isset($_SESSION['error_msg'])):
?>
<script>ownerNotify(<?= json_encode($_SESSION['error_msg']) ?>, 'error');</script>
<?php
    unset($_SESSION['error_msg']);
endif;
