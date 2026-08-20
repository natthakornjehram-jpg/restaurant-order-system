<?php
// update_menu_status_ajax.php
session_start();
include '../includes/db.php';

header('Content-Type: application/json');

// 1. เช็กสิทธิ์การล็อกอินว่าเป็นเจ้าของร้านหรือไม่
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
}

$owner_id = $_SESSION['user_id'];

// 2. หา restaurant_id ของเจ้าของร้านที่ล็อกอินอยู่
$stmt_rest = $conn->prepare("SELECT restaurant_id FROM restaurants WHERE owner_id = ? LIMIT 1");
$stmt_rest->bind_param("i", $owner_id);
$stmt_rest->execute();
$res_rest = $stmt_rest->get_result();
$rest_data = $res_rest->fetch_assoc();

if (!$rest_data) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบข้อมูลร้านค้าของคุณ']);
    exit;
}
$restaurant_id = $rest_data['restaurant_id'];

// 3. รับค่าและตรวจสอบข้อมูล
if (isset($_POST['menu_id']) && isset($_POST['is_available'])) {
    $menu_id = intval($_POST['menu_id']);
    $status = intval($_POST['is_available']);

    // 4. อัปเดตสถานะ (เช็กด้วยว่าเมนูนี้ต้องเป็นของร้านนี้จริงๆ)
    $sql = "UPDATE menus SET is_available = ? WHERE menu_id = ? AND restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $status, $menu_id, $restaurant_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ไม่พบเมนู หรือไม่มีการเปลี่ยนแปลง']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
}

$conn->close();
?>