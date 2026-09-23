<?php
// includes/send_email_otp.php
// ส่งอีเมล OTP ผ่าน Brevo API (HTTP/HTTPS ธรรมดา ไม่ใช่ SMTP) - ตั้งค่าที่ includes/email_config.php
//
// เปลี่ยนจาก Gmail SMTP (PHPMailer) มาเป็น Brevo API เพราะโฮสต์ฟรีส่วนใหญ่ (เช่น InfinityFree, 000webhost)
// บล็อกการเชื่อมต่อ SMTP ขาออก (port 587/465) เพื่อกันสแปม ทำให้ส่งอีเมลผ่าน Gmail SMTP ไม่ได้เลยตอนขึ้นโฮสต์จริง
// ส่วน Brevo API เป็นแค่ HTTP request ธรรมดา (เหมือนเว็บเรียก API ทั่วไป) ใช้ผ่าน HTTPS port 443 ซึ่งโฮสต์แทบ
// ทุกเจ้าไม่บล็อก เลยใช้ได้ทั้งตอน dev บนเครื่องตัวเองและตอนขึ้นโฮสต์จริงโดยไม่ต้องเปลี่ยนโค้ดอีก
//
// การส่งจริง (curl ไปยัง Brevo) ย้ายไปรวมไว้ที่ includes/email_sender.php (send_email_via_brevo) แล้ว
// เพื่อให้ฟีเจอร์อื่นที่ต้องส่งอีเมลเหมือนกัน (เช่น แจ้งเตือนล็อกอินจากอุปกรณ์ใหม่) เรียกใช้ร่วมกันได้โดยไม่ก็อปโค้ดซ้ำ
require_once __DIR__ . '/email_sender.php';

function send_email_otp(string $to_email, string $to_name, string $otp): array
{
    if (empty($to_email)) {
        return ['success' => false, 'error' => 'เจ้าของร้านยังไม่ได้ตั้งอีเมลไว้ในหน้าตั้งค่าร้าน'];
    }

    $subject = "รหัส OTP รีเซ็ตรหัสผ่านของคุณคือ $otp";
    $html = "<div style=\"font-family:sans-serif;font-size:16px;\">"
        . "<p>รหัส OTP สำหรับรีเซ็ตรหัสผ่านของคุณคือ:</p>"
        . "<p style=\"font-size:32px;font-weight:bold;letter-spacing:6px;\">" . htmlspecialchars($otp) . "</p>"
        . "<p>รหัสนี้จะหมดอายุใน 5 นาที หากคุณไม่ได้เป็นผู้ขอรีเซ็ตรหัสผ่าน กรุณาเพิกเฉยต่ออีเมลนี้</p>"
        . "</div>";

    return send_email_via_brevo($to_email, $to_name, $subject, $html);
}
