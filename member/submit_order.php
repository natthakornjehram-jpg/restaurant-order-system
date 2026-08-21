<?php
// member/submit_order.php
session_start();
require_once '../includes/db.php';
require_once '../includes/upload_helper.php';

// ... (ส่วนที่ 1 เช็กตะกร้าคงเดิม) ...

// 💡 2. ดึงข้อมูลลูกค้าออนไลน์/กลับบ้าน (รับมาจาก confirm_order.php)
$customer_id = $_SESSION['customer_id'] ?? NULL;
$customer_username = $_SESSION['customer_name'] ?? 'Guest'; // สำหรับตั้งชื่อไฟล์
$table_id = $_SESSION['table_id'] ?? NULL; 
$order_type = $_POST['order_type'] ?? 'takeaway';
$total_amount = floatval($_POST['total_amount'] ?? 0);

// รับชื่อและเบอร์โทรเพิ่มมาตรงนี้
$online_name = $_POST['customer_name_online'] ?? ($_POST['takeaway_name'] ?? '');
$online_phone = $_POST['customer_phone_online'] ?? '';
$note = $_POST['note'] ?? '';

// 3. บันทึกข้อมูลลงตาราง orders (เพิ่มคอลัมน์ใหม่ 2 ตัว)
$order_status = 'pending';
$payment_status = 'unpaid';

$stmt_order = $conn->prepare("INSERT INTO orders 
    (customer_id, table_id, total_amount, order_status, payment_status, order_type, online_customer_name, online_customer_phone, note) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

// ปรับ bind_param เป็น "iidssssss" (เพิ่ม s สองตัวสำหรับชื่อและเบอร์)
$stmt_order->bind_param("iidssssss", $customer_id, $table_id, $total_amount, 
    $order_status, $payment_status, $order_type, $online_name, $online_phone, $note);

if ($stmt_order->execute()) {
    $order_id = $conn->insert_id;

    // 4. จัดการเรื่องอัปโหลดสลิป (ตั้งชื่อตาม orderID_วันที่ ไม่ใช้ชื่อผู้ใช้ที่ตั้งเองมาต่อ path)
    if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === 0) {
        $new_filename = handle_image_upload($_FILES['payment_slip'], '../assets/images/slips/', 'slip_order' . $order_id);

        if ($new_filename !== false) {
            // บันทึกลงตาราง payment (ระบุชื่อสลิปใหม่)
            $stmt_pay = $conn->prepare("INSERT INTO payment (order_id, amount, slip_image) VALUES (?, ?, ?)");
            $stmt_pay->bind_param("ids", $order_id, $total_amount, $new_filename);
            $stmt_pay->execute();
        }
    }

    // 5. บันทึกรายการอาหารลงตาราง orderdetail และ orderdetail_topping
    $stmt_detail = $conn->prepare("INSERT INTO orderdetail (order_id, item_id, quantity, unit_price, note) VALUES (?, ?, ?, ?, ?)");
    $stmt_top = $conn->prepare("INSERT INTO orderdetail_topping (order_detail_id, topping_id) VALUES (?, ?)");

    foreach ($_SESSION['cart'] as $item) {
        // บันทึกอาหารหลัก
        $stmt_detail->bind_param("iiids", $order_id, $item['item_id'], $item['quantity'], $item['price'], $item['note']);
        $stmt_detail->execute();
        $order_detail_id = $conn->insert_id;

        // บันทึกท็อปปิ้ง (ถ้ามี)
        if (!empty($item['topping_ids'])) {
            foreach ($item['topping_ids'] as $tid) {
                $stmt_top->bind_param("ii", $order_detail_id, $tid);
                $stmt_top->execute();
            }
        }
    }

    // 6. เคลียร์ตะกร้าทิ้งเมื่อสั่งสำเร็จ
    unset($_SESSION['cart']);

    // 7. ถ้ามาจากการสแกน QR ที่โต๊ะ (มี table_id) ให้กลับไปหน้าเมนูของโต๊ะเดิม
    //    (ยังไม่จบรอบ! ลูกค้าอาจสั่งเพิ่ม หรือกดดูสถานะ/บิลได้ จนกว่าร้านจะปิดบิลจริง)
    if ($table_id) {
        $_SESSION['has_ordered'] = true; // ใช้เช็กตอนโพลว่าเมื่อร้านปิดบิลแล้วให้เด้งออก (ดู qr_table/api_check_bill.php)
        header("Location: ../qr_table/menu_dinein.php?table=" . urlencode($_SESSION['table_number'] ?? '') . "&order_success=1");
        exit;
    }

    // 8. ลูกค้าออนไลน์/สมาชิก: เด้งไปหน้าดูสถานะ/ใบเสร็จตามเดิม
    echo "<script>
        alert('ส่งคำสั่งซื้อเรียบร้อยแล้ว!');
        window.location.href = 'order_detail.php?id=" . $order_id . "';
    </script>";
    exit;

} else {
    // กรณีเกิดข้อผิดพลาด
    echo "<script>
        alert('เกิดข้อผิดพลาด ไม่สามารถบันทึกคำสั่งซื้อได้: " . $conn->error . "');
        window.history.back();
    </script>";
    exit;
}
?>