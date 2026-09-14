<?php
// csrf.php
// ตัวช่วยสร้างและตรวจสอบ CSRF token (session_start() ถูกเรียกไว้แล้วในไฟล์ที่ include ไฟล์นี้)

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify($token) {
    if (!is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
