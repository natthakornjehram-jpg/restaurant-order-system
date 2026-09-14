<?php
// owner/api_toggle_featured.php
// สลับสถานะ "เมนูแนะนำ" (ติดดาว) ของเมนู - เจ้าของร้านกดจากหน้า manage_menu.php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';
require_once '../includes/csrf.php';

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo "error";
    exit;
}

$id = intval($_POST['update_featured_id'] ?? 0);
$featured = intval($_POST['new_featured_val'] ?? 0);

$stmt = $conn->prepare("UPDATE item SET is_featured = ? WHERE item_id = ?");
$stmt->bind_param("ii", $featured, $id);
$stmt->execute();

echo "success";
