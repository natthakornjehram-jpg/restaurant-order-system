<?php
// includes/device_login_check.php
// เช็ก/บันทึกอุปกรณ์ที่เจ้าของร้านเคยล็อกอินสำเร็จไว้ (ผูกกับ cookie ในเครื่องนั้น) ถ้าล็อกอินสำเร็จจาก
// อุปกรณ์ที่ไม่เคยเห็นมาก่อน (ไม่มี cookie หรือ cookie ไม่ตรงกับที่บันทึกไว้ในฐานข้อมูล) จะส่งอีเมลแจ้งเจ้าของร้าน
// ทันที กันกรณีมีคนอื่นที่รู้รหัสผ่าน (เช่น รหัสหลุด หรือเคยบอกพนักงานไว้) แอบล็อกอินจากเครื่องอื่น
require_once __DIR__ . '/email_sender.php';

const OWNER_DEVICE_COOKIE = 'owner_device_token';

/**
 * เรียกทันทีหลังล็อกอินสำเร็จ (หลัง session_regenerate_id) เพื่อเช็ก/จดจำอุปกรณ์นี้
 */
function check_and_register_owner_device(mysqli $conn, int $owner_id, string $owner_email, string $owner_name): void
{
    $cookie_token = $_COOKIE[OWNER_DEVICE_COOKIE] ?? '';

    if ($cookie_token !== '') {
        $stmt = $conn->prepare("SELECT device_id FROM owner_trusted_devices WHERE owner_id = ? AND device_token = ? LIMIT 1");
        $stmt->bind_param("is", $owner_id, $cookie_token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row) {
            // อุปกรณ์เดิมที่เคยล็อกอินสำเร็จมาก่อนแล้ว แค่อัปเดตเวลาล่าสุด ไม่ต้องแจ้งเตือนซ้ำ
            $upd = $conn->prepare("UPDATE owner_trusted_devices SET last_seen_at = NOW() WHERE device_id = ?");
            $upd->bind_param("i", $row['device_id']);
            $upd->execute();
            return;
        }
    }

    // มาถึงตรงนี้แปลว่าอุปกรณ์ใหม่ (ไม่มี cookie เลย หรือมี cookie แต่ไม่ตรงกับที่บันทึกไว้ เช่น ล้าง cookie
    // ไปแล้ว/สลับเครื่อง/สลับเบราว์เซอร์) สร้าง token ใหม่ให้จำอุปกรณ์นี้ไว้ + แจ้งเตือนเจ้าของร้านทันที
    $new_token = bin2hex(random_bytes(32));
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $ins = $conn->prepare("INSERT INTO owner_trusted_devices (owner_id, device_token, user_agent, ip_address) VALUES (?, ?, ?, ?)");
    $ins->bind_param("isss", $owner_id, $new_token, $user_agent, $ip);
    $ins->execute();

    setcookie(OWNER_DEVICE_COOKIE, $new_token, [
        'expires' => time() + 60 * 60 * 24 * 365, // จำอุปกรณ์นี้ไว้ 1 ปี กันต้องแจ้งเตือนซ้ำทุกครั้งที่ล็อกอินจากเครื่องเดิม
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!empty($owner_email)) {
        $subject = 'แจ้งเตือน: มีการเข้าสู่ระบบจากอุปกรณ์ใหม่';
        $html = "<div style=\"font-family:sans-serif;font-size:16px;\">"
            . "<p>มีการเข้าสู่ระบบจัดการร้านของคุณสำเร็จ จากอุปกรณ์/เบราว์เซอร์ที่ไม่เคยล็อกอินมาก่อน</p>"
            . "<ul>"
            . "<li>เวลา: " . htmlspecialchars(date('d/m/Y H:i:s')) . " น.</li>"
            . "<li>IP: " . htmlspecialchars($ip) . "</li>"
            . "<li>อุปกรณ์/เบราว์เซอร์: " . htmlspecialchars($user_agent) . "</li>"
            . "</ul>"
            . "<p>ถ้าเป็นคุณเอง (เช่น เพิ่งเปลี่ยนเครื่อง/ล้าง cookie/ให้พนักงานล็อกอิน) ไม่ต้องทำอะไรเพิ่มเติม</p>"
            . "<p style=\"color:#c0392b;font-weight:bold;\">แต่ถ้าไม่ใช่คุณ กรุณาเปลี่ยนรหัสผ่านทันทีที่หน้าตั้งค่าร้าน</p>"
            . "</div>";
        // ส่งไม่สำเร็จก็ไม่บล็อกการล็อกอิน (เช่น ยังไม่ได้ตั้งค่า Brevo) แค่บันทึกอุปกรณ์ไว้เฉยๆ
        send_email_via_brevo($owner_email, $owner_name ?: $owner_email, $subject, $html);
    }
}
