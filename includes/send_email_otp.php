<?php
// includes/send_email_otp.php
// ส่งอีเมล OTP ผ่าน Brevo API (HTTP/HTTPS ธรรมดา ไม่ใช่ SMTP) - ตั้งค่าที่ includes/email_config.php
//
// เปลี่ยนจาก Gmail SMTP (PHPMailer) มาเป็น Brevo API เพราะโฮสต์ฟรีส่วนใหญ่ (เช่น InfinityFree, 000webhost)
// บล็อกการเชื่อมต่อ SMTP ขาออก (port 587/465) เพื่อกันสแปม ทำให้ส่งอีเมลผ่าน Gmail SMTP ไม่ได้เลยตอนขึ้นโฮสต์จริง
// ส่วน Brevo API เป็นแค่ HTTP request ธรรมดา (เหมือนเว็บเรียก API ทั่วไป) ใช้ผ่าน HTTPS port 443 ซึ่งโฮสต์แทบ
// ทุกเจ้าไม่บล็อก เลยใช้ได้ทั้งตอน dev บนเครื่องตัวเองและตอนขึ้นโฮสต์จริงโดยไม่ต้องเปลี่ยนโค้ดอีก

function send_email_otp(string $to_email, string $to_name, string $otp): array
{
    $config = require __DIR__ . '/email_config.php';
    $api_key = trim($config['brevo_api_key'] ?? '');
    $sender_email = trim($config['sender_email'] ?? '');

    if ($api_key === '' || $sender_email === '') {
        return ['success' => false, 'error' => 'ยังไม่ได้ตั้งค่า Brevo API key (brevo_api_key/sender_email) ใน includes/email_config.php'];
    }

    if (empty($to_email)) {
        return ['success' => false, 'error' => 'เจ้าของร้านยังไม่ได้ตั้งอีเมลไว้ในหน้าตั้งค่าร้าน'];
    }

    $payload = [
        'sender' => ['name' => $config['sender_name'] ?? 'ระบบร้านอาหาร', 'email' => $sender_email],
        'to' => [['email' => $to_email, 'name' => $to_name ?: $to_email]],
        'subject' => "รหัส OTP รีเซ็ตรหัสผ่านของคุณคือ $otp",
        'htmlContent' => "<div style=\"font-family:sans-serif;font-size:16px;\">"
            . "<p>รหัส OTP สำหรับรีเซ็ตรหัสผ่านของคุณคือ:</p>"
            . "<p style=\"font-size:32px;font-weight:bold;letter-spacing:6px;\">" . htmlspecialchars($otp) . "</p>"
            . "<p>รหัสนี้จะหมดอายุใน 5 นาที หากคุณไม่ได้เป็นผู้ขอรีเซ็ตรหัสผ่าน กรุณาเพิกเฉยต่ออีเมลนี้</p>"
            . "</div>",
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
