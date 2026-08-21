<?php
// owner/api_update_status.php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';
header('Content-Type: application/json');

// เช็กสิทธิ์
$owner_id = $_SESSION['owner_id'] ?? $_SESSION['user_id'] ?? $_SESSION['use'] ?? 0;

if ($owner_id == 0) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// รับค่าจาก AJAX
$id = $_POST['id'] ?? '';
$type = $_POST['type'] ?? '';
$new_status = isset($_POST['new_status']) ? intval($_POST['new_status']) : 0;
$reason = $_POST['reason'] ?? ''; // รับค่าเหตุผลจาก Prompt

$success = false;

// แยกเงื่อนไข
if ($type === 'shop_status') {
    if ($id === 'shop') {
        $stmt = $conn->prepare("UPDATE owner SET is_shop_open = ? WHERE owner_id = ?");
        $stmt->bind_param("ii", $new_status, $owner_id);
        $success = $stmt->execute();
    } elseif ($id === 'online') {
        // อัปเดตสถานะรับกลับบ้าน พร้อมบันทึกเหตุผล
        $stmt = $conn->prepare("UPDATE owner SET is_online_open = ?, close_reason = ? WHERE owner_id = ?");
        $stmt->bind_param("isi", $new_status, $reason, $owner_id);
        $success = $stmt->execute();
    }
} elseif ($type === 'menu' || $type === 'item') {
    $item_id = intval($id);
    $stmt = $conn->prepare("UPDATE item SET is_active = ? WHERE item_id = ?");
    $stmt->bind_param("ii", $new_status, $item_id);
    $success = $stmt->execute();
} elseif ($type === 'topping') {
    $topping_id = intval($id);
    $stmt = $conn->prepare("UPDATE topping SET is_active = ? WHERE topping_id = ?");
    $stmt->bind_param("ii", $new_status, $topping_id);
    $success = $stmt->execute();
} else {
    // 🔴 วางกับดัก: ถ้าส่งค่ามาผิดช่อง ให้แจ้งเตือนแบบรู้สาเหตุทันที!
    echo json_encode(['success' => false, 'error' => "ส่งข้อมูลมาผิดรูปแบบ (id='$id', type='$type') กรุณาเช็กปุ่ม HTML"]);
    exit;
}

// ส่งผลลัพธ์
if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'DB Error: ' . $conn->error]);
}
?>
