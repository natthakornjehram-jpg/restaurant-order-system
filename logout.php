<?php
session_start(); // เริ่มต้น Session เพื่อให้ระบบรู้จักว่าใครกำลังจะออก

// 1. ล้างข้อมูลทุกอย่างใน Session
$_SESSION = array();

// 2. ถ้ามีการใช้ Cookie สำหรับ Session ให้ทำลายทิ้งด้วย
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. ทำลาย Session ให้สิ้นซาก
session_destroy();

setcookie('remember_role', '', time() - 3600, '/');
setcookie('remember_id', '', time() - 3600, '/');

// 4. ส่งผู้ใช้งานกลับไปที่หน้า Login หลัก
header("Location: menu.php");
exit;
?>
