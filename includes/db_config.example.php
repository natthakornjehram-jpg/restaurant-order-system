<?php
// includes/db_config.example.php
// ไฟล์ตัวอย่าง - ให้คัดลอกไฟล์นี้เป็น includes/db_config.php แล้วกรอกค่าจริงลงไป
// (includes/db_config.php ถูกใส่ไว้ใน .gitignore เพราะมีรหัสผ่านฐานข้อมูลจริงอยู่ข้างใน ห้าม commit)
//
// บนเครื่อง dev (XAMPP): ถ้าไม่สร้างไฟล์ includes/db_config.php เลย ระบบจะใช้ค่า default
// (localhost / root / ไม่มีรหัสผ่าน / restaurant_db1) ให้อัตโนมัติ ไม่ต้องตั้งค่าอะไรเพิ่ม
//
// บนโฮสต์จริง (เช่น InfinityFree): ต้องสร้างไฟล์นี้เอง แล้วกรอกค่าที่โฮสต์ให้มา
// (หน้า MySQL Databases ใน Control Panel ของโฮสต์ - ปกติ host ไม่ใช่ localhost)

return [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'db'   => 'restaurant_db1',
];
