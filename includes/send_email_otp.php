<?php
// includes/send_email_otp.php
// ส่งอีเมล OTP ผ่าน Brevo Transactional Email API (HTTP + curl ล้วนๆ ไม่ต้องพึ่งไลบรารีภายนอก)
// ตั้งค่า API key ที่ includes/email_config.php

function send_email_otp(string $to_email, string $to_name, string $otp): array
{
    $config = require __DIR__ . '/email_config.php';
    $api_key = trim($config['api_key'] ?? '');
    $sender_email = trim($config['sender_email'] ?? '');

    if ($api_key === '' || $sender_email === '') {
        return ['success' => false, 'error' => 'ยังไม่ได้ตั้งค่า Brevo API key / sender email ใน includes/email_config.php'];
    }

    if (empty($to_email)) {
        return ['success' => false, 'error' => 'เจ้าของร้านยังไม่ได้ตั้งอีเมลไว้ในหน้าตั้งค่าร้าน'];
    }

    $payload = json_encode([
        'sender' => [
            'name' => $config['sender_name'] ?? 'ระบบร้านอาหาร',
            'email' => $sender_email,
        ],
        'to' => [
            ['email' => $to_email, 'name' => $to_name ?: $to_email],
        ],
        'subject' => "รหัส OTP รีเซ็ตรหัสผ่านของคุณคือ $otp",
        'htmlContent' => "<div style=\"font-family:sans-serif;font-size:16px;\">"
            . "<p>รหัส OTP สำหรับรีเซ็ตรหัสผ่านของคุณคือ:</p>"
            . "<p style=\"font-size:32px;font-weight:bold;letter-spacing:6px;\">" . htmlspecialchars($otp) . "</p>"
            . "<p>รหัสนี้จะหมดอายุใน 5 นาที หากคุณไม่ได้เป็นผู้ขอรีเซ็ตรหัสผ่าน กรุณาเพิกเฉยต่ออีเมลนี้</p>"
            . "</div>",
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'accept: application/json',
            'api-key: ' . $api_key,
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['success' => false, 'error' => 'เชื่อมต่อ Brevo ไม่ได้: ' . $curl_error];
    }

    if ($http_code >= 200 && $http_code < 300) {
        return ['success' => true, 'error' => null];
    }

    return ['success' => false, 'error' => 'Brevo ตอบกลับผิดพลาด (HTTP ' . $http_code . '): ' . $response];
}
