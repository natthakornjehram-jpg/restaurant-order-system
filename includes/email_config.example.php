<?php
// includes/email_config.example.php
// ไฟล์ตัวอย่าง - ให้คัดลอกไฟล์นี้เป็น includes/email_config.php แล้วกรอกค่าจริงลงไป
// (includes/email_config.php ถูกใส่ไว้ใน .gitignore เพราะมี API key จริงอยู่ข้างใน ห้าม commit)
//
// ตั้งค่าการส่งอีเมล OTP ผ่าน Brevo API (ใช้ HTTPS ธรรมดา ไม่ใช่ SMTP - ใช้ได้ทั้ง localhost และโฮสต์ฟรีทั่วไป
// ที่มักบล็อกการเชื่อมต่อ SMTP ขาออก)
//
// วิธีตั้งค่า (ใช้เวลา ~5 นาที ฟรี ไม่ต้องผูกบัตรเครดิต):
// 1. สมัครบัญชีฟรีที่ https://www.brevo.com (แพ็กเกจฟรีส่งได้ 300 อีเมล/วัน เพียงพอสำหรับ OTP)
// 2. ไปที่เมนู SMTP & API > API Keys แล้วกด "Generate a new API key" คัดลอกค่าที่ได้มาใส่ brevo_api_key ด้านล่าง
// 3. ไปที่เมนู Senders (หรือ Contacts > Senders, Domains & Dedicated IPs) แล้วเพิ่ม/ยืนยันอีเมลผู้ส่ง
//    (อีเมลที่จะใส่ใน sender_email ด้านล่าง ต้องเป็นอีเมลที่ยืนยันไว้ในขั้นตอนนี้แล้วเท่านั้น ถึงจะส่งผ่านได้)

return [
    'brevo_api_key' => '',
    'sender_email' => 'your-shop-email@gmail.com', // ต้องเป็นอีเมลที่ยืนยันไว้ในบัญชี Brevo แล้ว
    'sender_name' => 'ระบบร้านอาหาร',
];
