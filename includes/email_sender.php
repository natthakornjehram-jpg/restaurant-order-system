<?php
// includes/email_sender.php
// ฟังก์ชันกลางสำหรับส่งอีเมลผ่าน Brevo API (HTTP/HTTPS ธรรมดา ไม่ใช่ SMTP) - ตั้งค่าที่ includes/email_config.php
// ใช้ร่วมกันทั้ง OTP รีเซ็ตรหัสผ่าน (send_email_otp.php) และแจ้งเตือนอุปกรณ์ใหม่ (device_login_check.php)
// เหตุผลที่ใช้ Brevo API แทน Gmail SMTP ดูรายละเอียดที่ send_email_otp.php

function send_email_via_brevo(string $to_email, string $to_name, string $subject, string $html_content): array
{
    $config = require __DIR__ . '/email_config.php';
    $api_key = trim($config['brevo_api_key'] ?? '');
    $sender_email = trim($config['sender_email'] ?? '');

    if ($api_key === '' || $sender_email === '') {
        return ['success' => false, 'error' => 'ยังไม่ได้ตั้งค่า Brevo API key (brevo_api_key/sender_email) ใน includes/email_config.php'];
    }

    if (empty($to_email)) {
        return ['success' => false, 'error' => 'ไม่มีอีเมลปลายทางให้ส่ง'];
    }

    $payload = [
        'sender' => ['name' => $config['sender_name'] ?? 'ระบบร้านอาหาร', 'email' => $sender_email],
        'to' => [['email' => $to_email, 'name' => $to_name ?: $to_email]],
        'subject' => $subject,
        'htmlContent' => $html_content,
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $api_key,
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => 'เชื่อมต่อ Brevo API ไม่สำเร็จ: ' . $curl_error];
    }

    // Brevo ตอบ 201 Created ตอนส่งสำเร็จ นอกนั้นถือว่าผิดพลาด (เช่น api key ผิด, sender ยังไม่ยืนยัน)
    if ($http_code === 201) {
        return ['success' => true, 'error' => null];
    }

    $decoded = json_decode((string) $response, true);
    $api_message = $decoded['message'] ?? $response;
    return ['success' => false, 'error' => "ส่งอีเมลผ่าน Brevo ไม่สำเร็จ (HTTP $http_code): $api_message"];
}
