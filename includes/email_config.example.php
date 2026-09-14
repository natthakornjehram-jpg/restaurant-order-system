<?php
// includes/email_config.example.php
// ไฟล์ตัวอย่าง - ให้คัดลอกไฟล์นี้เป็น includes/email_config.php แล้วกรอกค่าจริงลงไป
// (includes/email_config.php ถูกใส่ไว้ใน .gitignore เพราะมี App Password จริงอยู่ข้างใน ห้าม commit)
//
// ตั้งค่าการส่งอีเมล OTP ผ่าน Gmail SMTP (ใช้บัญชี Gmail ของร้านเอง ไม่ต้องสมัครบริการที่สามเพิ่ม)
//
// วิธีตั้งค่า (ใช้เวลา ~5 นาที):
// 1. เปิด 2-Step Verification ในบัญชี Gmail ที่จะใช้ส่ง (https://myaccount.google.com/security)
//    (ต้องเปิดก่อนเสมอ ไม่งั้นสร้าง App Password ในขั้นตอนถัดไปไม่ได้)
// 2. ไปที่ https://myaccount.google.com/apppasswords แล้วสร้าง App Password ใหม่
//    ตั้งชื่ออะไรก็ได้ (เช่น "restaurant-otp") กด Create แล้วก็อปรหัส 16 หลักที่ได้มาใส่ด้านล่าง
//    (รหัสนี้ไม่ใช่รหัสผ่าน Gmail จริง เป็นรหัสแยกเฉพาะสำหรับแอปนี้ ปิดการใช้งานทีหลังได้โดยไม่กระทบบัญชีหลัก)
// 3. ใส่อีเมล Gmail เต็ม (เช่น restaurant@gmail.com) ในช่อง smtp_username และ sender_email ด้านล่าง

return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-shop-email@gmail.com',
    'smtp_password' => '', // App Password 16 หลัก (ไม่ใช่รหัสผ่าน Gmail จริง)
    'sender_email' => 'your-shop-email@gmail.com',
    'sender_name' => 'ระบบร้านอาหาร',
];
