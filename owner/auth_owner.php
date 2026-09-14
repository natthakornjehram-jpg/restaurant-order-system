<?php
// Shared access check for every owner page and owner API.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ห้ามเบราว์เซอร์แคชหน้าฝั่งเจ้าของร้านเด็ดขาด (ข้อมูลออเดอร์/คลังสินค้าเปลี่ยนตลอดเวลา และกันปัญหา
// แก้โค้ดแล้วเบราว์เซอร์ยังโหลด HTML เก่าที่แคชไว้อยู่ ทำให้ดูเหมือนโค้ดที่แก้ไม่มีผล)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (($_SESSION['role'] ?? '') !== 'owner' || empty($_SESSION['owner_id'])) {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_starts_with($script, 'api_') || str_ends_with($script, '_ajax.php') || str_ends_with($script, '_api.php')) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    header('Location: ../login.php');
    exit;
}

$owner_id = (int) $_SESSION['owner_id'];
