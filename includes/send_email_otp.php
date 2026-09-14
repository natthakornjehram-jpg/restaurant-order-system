<?php
// includes/send_email_otp.php
// ส่งอีเมล OTP ผ่าน Gmail SMTP โดยใช้ PHPMailer (ไฟล์ vendor ไว้ที่ includes/PHPMailer/ ไม่ต้องใช้ Composer)
// ตั้งค่าบัญชี Gmail + App Password ที่ includes/email_config.php

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function send_email_otp(string $to_email, string $to_name, string $otp): array
{
    $config = require __DIR__ . '/email_config.php';
    $smtp_username = trim($config['smtp_username'] ?? '');
    $smtp_password = trim($config['smtp_password'] ?? '');
    $sender_email = trim($config['sender_email'] ?? '');

    if ($smtp_username === '' || $smtp_password === '' || $sender_email === '') {
        return ['success' => false, 'error' => 'ยังไม่ได้ตั้งค่า Gmail SMTP (smtp_username/smtp_password) ใน includes/email_config.php'];
    }

    if (empty($to_email)) {
        return ['success' => false, 'error' => 'เจ้าของร้านยังไม่ได้ตั้งอีเมลไว้ในหน้าตั้งค่าร้าน'];
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password; // App Password 16 หลัก ไม่ใช่รหัสผ่าน Gmail จริง
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) ($config['smtp_port'] ?? 587);
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 10;

        $mail->setFrom($sender_email, $config['sender_name'] ?? 'ระบบร้านอาหาร');
        $mail->addAddress($to_email, $to_name ?: $to_email);

        $mail->isHTML(true);
        $mail->Subject = "รหัส OTP รีเซ็ตรหัสผ่านของคุณคือ $otp";
        $mail->Body = "<div style=\"font-family:sans-serif;font-size:16px;\">"
            . "<p>รหัส OTP สำหรับรีเซ็ตรหัสผ่านของคุณคือ:</p>"
            . "<p style=\"font-size:32px;font-weight:bold;letter-spacing:6px;\">" . htmlspecialchars($otp) . "</p>"
            . "<p>รหัสนี้จะหมดอายุใน 5 นาที หากคุณไม่ได้เป็นผู้ขอรีเซ็ตรหัสผ่าน กรุณาเพิกเฉยต่ออีเมลนี้</p>"
            . "</div>";

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (PHPMailerException $e) {
        return ['success' => false, 'error' => 'ส่งอีเมลผ่าน Gmail ไม่สำเร็จ: ' . $mail->ErrorInfo];
    }
}
