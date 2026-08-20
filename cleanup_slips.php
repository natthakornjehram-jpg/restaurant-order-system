<?php
// cleanup_slips.php
$dir = __DIR__ . "/assets/images/slips/";

// เช็กไฟล์ทั้งหมดในโฟลเดอร์
if (is_dir($dir)) {
    if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) !== false) {
            if ($file != "." && $file != "..") {
                $file_path = $dir . $file;
                
                // เช็กอายุไฟล์ (3 ปี = 3 * 365 * 24 * 60 * 60 วินาที)
                $three_years_seconds = 3 * 365 * 24 * 60 * 60;
                
                if (filemtime($file_path) < (time() - $three_years_seconds)) {
                    unlink($file_path); // สั่งลบไฟล์ทันที
                }
            }
        }
        closedir($dh);
    }
}
?>