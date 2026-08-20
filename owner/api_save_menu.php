<?php
// owner/api_save_menu.php
session_start();
require_once '../includes/db.php';
require_once 'auth_owner.php';
require_once '../includes/upload_helper.php';

// บันทึก/อัปเดตข้อมูลเมนู (แยกออกมาจาก manage_menu.php)
if (!isset($_POST['save_menu'])) {
    header("Location: manage_menu.php");
    exit;
}

$m_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
$name = $_POST['menu_name'];
$price = (float) $_POST['price'];
$cat_id = (int) $_POST['category_id'];
$status = isset($_POST['is_active']) ? 1 : 0;

$db_image_path = "";
if (!empty($_FILES['image']['name'])) {
    $uploaded = handle_image_upload($_FILES['image'], "../assets/images/items/", "item");
    if ($uploaded !== false) {
        $db_image_path = $uploaded;
    }
}

if ($m_id > 0) {
    if ($db_image_path !== "") {
        $stmt = $conn->prepare("UPDATE item SET name=?, price=?, category_id=?, is_active=?, image_url=? WHERE item_id=?");
        $stmt->bind_param("sdiisi", $name, $price, $cat_id, $status, $db_image_path, $m_id);
    } else {
        $stmt = $conn->prepare("UPDATE item SET name=?, price=?, category_id=?, is_active=? WHERE item_id=?");
        $stmt->bind_param("sdiii", $name, $price, $cat_id, $status, $m_id);
    }
} else {
    $img = !empty($db_image_path) ? $db_image_path : 'default_food.png';
    $stmt = $conn->prepare("INSERT INTO item (name, price, category_id, image_url, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->bind_param("sdis", $name, $price, $cat_id, $img);
}

if ($stmt->execute()) {
    $target_id = ($m_id > 0) ? $m_id : $conn->insert_id;

    // จัดการท็อปปิ้ง
    $conn->query("DELETE FROM menu_toppings WHERE item_id = $target_id");
    if (!empty($_POST['topping_ids'])) {
        foreach ($_POST['topping_ids'] as $t_id) {
            $conn->query("INSERT INTO menu_toppings (item_id, topping_id) VALUES ($target_id, " . intval($t_id) . ")");
        }
    }
    $_SESSION['success_msg'] = "บันทึกข้อมูลเรียบร้อย!";
} else {
    $_SESSION['error_msg'] = "เกิดข้อผิดพลาด: " . $conn->error;
}

header("Location: manage_menu.php");
exit;
