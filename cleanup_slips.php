<?php
// cleanup_slips.php
// สคริปต์นี้มีไว้รันผ่าน cron/CLI เท่านั้น (ลบไฟล์สลิปเก่าอัตโนมัติ) เดิมไม่มีการกันการเข้าถึงเลย
// ใครก็เปิด URL นี้ตรงๆ ผ่านเว็บแล้วสั่งลบไฟล์ได้ทันที จึงเพิ่มการเช็คว่ารันจาก CLI เท่านั้น
// ถ้าจำเป็นต้องรันผ่าน web (เช่น cron ของ hosting บางเจ้ายิงเป็น HTTP request) ให้ล็อกอินเป็น owner ก่อน
// (ไม่ include owner/auth_owner.php ตรงๆ เพราะไฟล์นั้น redirect แบบ relative path "../login.php"
// ซึ่งคำนวณจากการถูก include ในโฟลเดอร์ owner/ เท่านั้น ใช้จากตำแหน่ง root ตรงๆ จะได้ path ผิด)
if (PHP_SAPI !== 'cli') {
    session_start();
    if (($_SESSION['role'] ?? '') !== 'owner' || empty($_SESSION['owner_id'])) {
        http_response_code(403);
        exit('Forbidden');
    }
}

$dir = __DIR__ . "/assets/images/slips/";

// เช็กไฟล์ทั้งหมดในโฟลเดอร์
if (is_dir($dir)) {
    if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) !== false) {
            if ($file != "." && $file != "..") {
                $file_path = $dir . $file;
                if (!is_file($file_path)) continue;

                // เช็กอายุไฟล์ (3 ปี = 3 * 365 * 24 * 60 * 60 วินาที)
                $three_years_seconds = 3 * 365 * 24 * 60 * 60;

                $mtime = @filemtime($file_path);
                if ($mtime !== false && $mtime < (time() - $three_years_seconds)) {
                    unlink($file_path); // สั่งลบไฟล์ทันที
                }
            }
        }
        closedir($dh);
    }
}
?>