<?php
// member/submit_order.php
session_start();
require_once '../includes/db.php';
require_once '../includes/csrf.php';
require_once '../includes/upload_helper.php';

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    echo "<script>
        alert('คำขอไม่ถูกต้อง (CSRF token ไม่ถูกต้อง) กรุณาลองใหม่อีกครั้ง');
        window.history.back();
    </script>";
    exit;
}

// 0. ปลายทางกลับไปหน้าเมนูตอนเกิดข้อผิดพลาด (มีโต๊ะก็กลับไปหน้าเมนูของโต๊ะนั้น ไม่มีโต๊ะก็กลับไปหน้าเมนูเฉยๆ)
$menu_fallback_url = '../qr_table/menu_dinein.php' . (!empty($_SESSION['table_number']) ? '?table=' . urlencode($_SESSION['table_number']) : '');

// 1. เช็กว่ามีตะกร้าจริงไหม (กันเคส POST ตรงมาที่ไฟล์นี้โดยไม่ผ่านหน้าตะกร้า)
if (empty($_SESSION['cart'])) {
    header("Location: $menu_fallback_url");
    exit;
}

// 1.5 เช็คสถานะร้านฝั่งเซิร์ฟเวอร์อีกครั้ง (หน้าตะกร้าปิดปุ่มสั่งไว้แค่ฝั่ง client เท่านั้น
//     ยิง POST ตรงมาที่ไฟล์นี้ตอนร้านปิดได้ถ้าไม่เช็คซ้ำตรงนี้)
if (empty($store['is_shop_open'])) {
    echo "<script>
        alert('ขณะนี้ร้านปิดให้บริการ ไม่สามารถสั่งอาหารได้ในขณะนี้');
        window.location.href = " . json_encode($menu_fallback_url, JSON_UNESCAPED_SLASHES) . ";
    </script>";
    exit;
}

// 💡 2. ดึงข้อมูลลูกค้าออนไลน์/กลับบ้าน (รับมาจาก confirm_order.php)
// ไม่มีระบบบัญชีลูกค้าแล้ว - ผูกความเป็นเจ้าของออเดอร์ด้วย session แทน (ดูขั้นตอนที่ 8)
$table_id = $_SESSION['table_id'] ?? NULL;
// order_type เป็น dine_in ได้จริงก็ต่อเมื่อมี table_id จากการสแกน QR ในเซสชันเท่านั้น (ไม่ใช่เชื่อค่าจากฟอร์มตรงๆ)
// กันลูกค้าออนไลน์ (ไม่มี table_id) ปลอมค่า order_type เป็น dine_in เพื่อข้ามการสร้างรายการชำระเงินด้านล่าง
// (ออเดอร์ dine_in จะไม่ insert แถว payment เลย เพราะปกติจ่ายตอนปิดบิลที่เคาน์เตอร์แทน)
$order_type = ($_POST['order_type'] ?? '') === 'dine_in' && $table_id ? 'dine_in' : 'takeaway';

// 1.6 เช็คสถานะเปิด/ปิดรับออเดอร์ตามประเภทฝั่งเซิร์ฟเวอร์อีกครั้ง (หน้าเลือกประเภท/ตะกร้าปิดปุ่มไว้แค่ฝั่ง client
//     เช่นเดียวกับข้อ 1.5 ยิง POST ตรงมาที่ไฟล์นี้ตอนร้านเพิ่งปิดรับประเภทนั้นได้ถ้าไม่เช็คซ้ำตรงนี้)
if (($order_type === 'dine_in' && empty($store['is_dinein_open'])) || ($order_type === 'takeaway' && empty($store['is_takeaway_open']))) {
    echo "<script>
        alert('ขณะนี้ร้านงดรับออเดอร์ประเภทนี้ชั่วคราว กรุณาเลือกใหม่อีกครั้ง');
        window.location.href = " . json_encode($menu_fallback_url, JSON_UNESCAPED_SLASHES) . ";
    </script>";
    exit;
}

// รับชื่อและเบอร์โทรเพิ่มมาตรงนี้
$online_name = $_POST['customer_name_online'] ?? ($_POST['takeaway_name'] ?? '');
$online_phone = $_POST['customer_phone_online'] ?? '';
$note = $_POST['note'] ?? '';
$payment_method = in_array($_POST['payment_method'] ?? '', ['cash', 'transfer', 'qr_counter'], true) ? $_POST['payment_method'] : 'cash';

// 2.6 "โอนเงินเอง" ต้องแนบสลิปมาพร้อมตอนสั่งเลย (ไม่ใช่โชว์ตอนมารับเหมือนเดิม) กันลูกค้าสั่งทิ้งไว้ไม่มารับ/ไม่จ่ายจริง
// อัปโหลดก่อนเปิดทรานแซกชัน เพราะการเขียนไฟล์ไม่ได้อยู่ในทรานแซกชันของฐานข้อมูลด้วย ถ้าอัปโหลดไม่ผ่านต้องเช็คให้เสร็จตั้งแต่ตรงนี้
$slip_filename = null;
if ($payment_method === 'transfer' && $order_type !== 'dine_in') {
    $slip_filename = !empty($_FILES['payment_slip']['name'])
        ? handle_image_upload($_FILES['payment_slip'], '../assets/images/slips/', 'slip')
        : false;

    if ($slip_filename === false) {
        echo "<script>
            alert('กรุณาแนบไฟล์รูปสลิปการโอนเงิน (JPG, PNG, WEBP) ก่อนส่งออเดอร์ครับ');
            window.history.back();
        </script>";
        exit;
    }
}

// 2.5 ห้ามเชื่อ total_amount ที่ส่งมาจากฟอร์ม (ลูกค้าแก้ค่าใน request ได้) และห้ามเชื่อราคาที่แคชไว้ในตะกร้าตอนกดเพิ่มลงตะกร้า
//     (ราคาอาจเปลี่ยนไปแล้วระหว่างที่ลูกค้าเลือกของ) ต้องคำนวณยอดใหม่จากราคาปัจจุบันในฐานข้อมูลทุกครั้งตอนสั่งจริง
$stmt_item = $conn->prepare("SELECT price, is_active FROM item WHERE item_id = ?");
$stmt_topping = $conn->prepare("SELECT t.price FROM topping t
    JOIN menu_toppings mt ON mt.topping_id = t.topping_id
    WHERE mt.item_id = ? AND t.topping_id = ? AND t.is_active = 1");

$validated_cart = [];
$total_amount = 0;

foreach ($_SESSION['cart'] as $item) {
    $item_id = intval($item['item_id']);
    $quantity = max(1, intval($item['quantity']));

    $stmt_item->bind_param("i", $item_id);
    $stmt_item->execute();
    $item_row = $stmt_item->get_result()->fetch_assoc();

    // เมนูถูกลบ/ปิดขายไปแล้วระหว่างที่ลูกค้ากำลังสั่ง ให้ข้ามรายการนี้ไป
    if (!$item_row || intval($item_row['is_active']) !== 1) {
        continue;
    }

    $unit_price = floatval($item_row['price']);
    $valid_topping_ids = [];

    foreach ($item['topping_ids'] ?? [] as $tid) {
        $tid = intval($tid);
        $stmt_topping->bind_param("ii", $item_id, $tid);
        $stmt_topping->execute();
        $topping_row = $stmt_topping->get_result()->fetch_assoc();
        if ($topping_row) {
            $unit_price += floatval($topping_row['price']);
            $valid_topping_ids[] = $tid;
        }
        // ท็อปปิ้งที่ไม่ผูกกับเมนูนี้หรือถูกปิดใช้งานไปแล้ว จะถูกตัดทิ้งเงียบๆ ไม่รวมในราคา
    }

    $validated_cart[] = [
        'item_id' => $item_id,
        'quantity' => $quantity,
        'note' => $item['note'] ?? '',
        'unit_price' => $unit_price,
        'topping_ids' => $valid_topping_ids,
    ];
    $total_amount += $unit_price * $quantity;
}

if (empty($validated_cart)) {
    echo "<script>
        alert('เมนูในตะกร้าไม่พร้อมขายแล้ว กรุณาเลือกเมนูใหม่อีกครั้ง');
        window.location.href = " . json_encode($menu_fallback_url, JSON_UNESCAPED_SLASHES) . ";
    </script>";
    exit;
}

$order_status = 'pending';
$payment_status = 'unpaid';

// การสร้างออเดอร์ + ตัดสต็อก + สร้างรายการชำระเงิน ต้องสำเร็จไปด้วยกันทั้งหมด
// ถ้าขั้นตอนไหนพลาด ต้อง rollback ทั้งหมด กันออเดอร์ค้าง/สต็อกไม่ตรงกัน
$conn->begin_transaction();
try {
    // 3. คำนวณลำดับออเดอร์ของวันนี้ (เริ่มต้นที่ 1 ใหม่ทุกวัน)
    // ย้ายมาไว้ในทรานแซกชันเดียวกันพร้อม FOR UPDATE กันสองออเดอร์ที่ยิงเข้ามาพร้อมกันได้เลขคิวซ้ำกัน
    // (เดิม query แยกก่อนเปิดทรานแซกชัน ทำให้สองคำขอที่ทับเวลากันเห็นค่านับเดิมพร้อมกันได้)
    $d_res = $conn->query("SELECT COUNT(*) as today_count FROM orders WHERE DATE(created_at) = CURDATE() FOR UPDATE");
    $today_count = ($d_res && $d_row = $d_res->fetch_assoc()) ? intval($d_row['today_count']) : 0;
    $daily_order_no = $today_count + 1;

    $stmt_order = $conn->prepare("INSERT INTO orders
        (table_id, total_amount, order_status, payment_status, order_type, online_customer_name, online_customer_phone, note, daily_order_no)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt_order->bind_param(
        "idssssssi",
        $table_id,
        $total_amount,
        $order_status, 
        $payment_status, 
        $order_type, 
        $online_name, 
        $online_phone, 
        $note, 
        $daily_order_no
    );

    $stmt_order->execute();

    $order_id = $conn->insert_id;

    // 3.5 ออเดอร์แรกของโต๊ะ (ทานที่ร้าน) ต้องสุ่มรหัสร่วมโต๊ะทันที ตั้งแต่ตอนสั่งเลย ไม่ใช่รอให้ร้าน
    // กดอนุมัติก่อน (เดิมรอกดอนุมัติถึงจะสุ่มรหัส) เพราะช่วงที่ออเดอร์แรกยังค้างรออนุมัติอยู่ ยังไม่มีรหัส
    // เลย ถ้ามีคนอื่นสแกน QR โต๊ะเดียวกันเข้ามาพอดีช่วงนั้นจะเข้าเมนูสั่งอาหารแยกได้อิสระโดยไม่ต้องใส่รหัส
    // (ตั้งใจไม่แตะสถานะโต๊ะ "available"/"occupied" ตรงนี้ ปล่อยให้ยังคงเปลี่ยนตอนร้านกดอนุมัติเหมือนเดิม
    // เพื่อไม่ให้กระทบ badge "ออเดอร์แรก (ขอเปิดโต๊ะใหม่)" ในหน้าออเดอร์ฝั่งร้านที่เช็คจากสถานะนี้อยู่
    // การล็อกกันคนแปลกหน้าเข้าร่วมโต๊ะใช้ join_code เพียงอย่างเดียวเป็นตัวตัดสิน ดู qr_table/join_table.php)
    // ใช้ COALESCE(NULLIF(...)) กันไม่ให้สุ่มรหัสใหม่ทับของเดิมตอนลูกค้าสั่งเพิ่มรอบถัดไป
    if ($order_type === 'dine_in' && $table_id) {
        $rand_pin = str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $stmt_lock_table = $conn->prepare("UPDATE restauranttable SET join_code = COALESCE(NULLIF(join_code, ''), ?) WHERE table_id = ?");
        $stmt_lock_table->bind_param("si", $rand_pin, $table_id);
        $stmt_lock_table->execute();
    }

    // 4. บันทึกข้อมูลการชำระเงิน (เฉพาะออเดอร์กลับบ้าน/ออนไลน์ - ทานที่ร้านจ่ายตอนปิดบิลแทน)
    // เช็คจาก order_type ไม่ใช่ table_id เพราะลูกค้าที่สแกนโต๊ะแล้วเลือก "สั่งกลับบ้าน" ก็ยังมี table_id ติดมาด้วย
    // (ถ้าเช็คจาก table_id ออเดอร์กลับบ้านที่สแกนโต๊ะจะไม่มีแถว payment เลย และไม่โผล่ในหน้าจัดการชำระเงินฝั่งร้าน)
    // จ่ายเงินสด/สแกน QR หน้าเคาน์เตอร์ตอนมารับ - บันทึกไว้เป็นหลักฐานว่าตกลงจ่ายแบบไหน รอร้านยืนยันรับเงินตอนลูกค้ามารับของ
    // ส่วน "โอนเงินเอง" แนบสลิปมาแล้วตั้งแต่ก่อนเปิดทรานแซกชัน (ดูขั้นตอนที่ 2.6) บันทึกชื่อไฟล์สลิปลง slip_image ไปด้วย
    if ($order_type !== 'dine_in') {
        $stmt_pay = $conn->prepare("INSERT INTO payment (order_id, amount, method, slip_image) VALUES (?, ?, ?, ?)");
        $stmt_pay->bind_param(
            "idss",
            $order_id,
            $total_amount,
            $payment_method,
            $slip_filename);

        $stmt_pay->execute();
    }

    // 5. บันทึกรายการอาหารลงตาราง orderdetail และ orderdetail_topping พร้อมตัดสต็อกอัตโนมัติ
    $stmt_detail = $conn->prepare("INSERT INTO orderdetail (order_id, item_id, quantity, unit_price, note) VALUES (?, ?, ?, ?, ?)");
    $stmt_top = $conn->prepare("INSERT INTO orderdetail_topping (order_detail_id, topping_id) VALUES (?, ?)");
    // เดิม GREATEST(0, ...) ปล่อยให้ตัดสต็อกติดลบไม่ได้แต่ก็ยังรับออเดอร์ผ่านอยู่ดีแม้ของจะไม่พอ
    // เปลี่ยนเป็นเงื่อนไข stock_qty >= ? ใน WHERE แทน ให้ UPDATE ล้มเหลว (affected_rows = 0) ถ้าของไม่พอ
    // ซึ่งปลอดภัยต่อ race condition ด้วย เพราะ MySQL ล็อกแถวระหว่างรัน UPDATE ทีละคำสั่งอยู่แล้ว
    $stmt_stock = $conn->prepare("UPDATE item SET stock_qty = stock_qty - ? WHERE item_id = ? AND use_stock = 1 AND stock_qty >= ?");
    $stmt_stock_check = $conn->prepare("SELECT use_stock FROM item WHERE item_id = ?");

    foreach ($validated_cart as $item) {
        // บันทึกอาหารหลัก
        $stmt_detail->bind_param("iiids", $order_id, $item['item_id'], $item['quantity'], $item['unit_price'], $item['note']);
        $stmt_detail->execute();
        $order_detail_id = $conn->insert_id;

        // ตัดสต็อกอาหาร (เฉพาะเมนูที่เปิดติดตามคลังสินค้า และต้องมีของพอเท่านั้น)
        $stmt_stock->bind_param("iii", $item['quantity'], $item['item_id'], $item['quantity']);
        $stmt_stock->execute();
        if ($stmt_stock->affected_rows === 0) {
            // ไม่มีแถวถูกอัปเดต อาจเพราะ (ก) เมนูนี้ไม่ได้ติดตามคลังสินค้า (use_stock=0) ซึ่งปกติดี ไม่ต้องทำอะไร
            // หรือ (ข) ติดตามคลังสินค้าอยู่แต่ของไม่พอ ต้องยกเลิกออเดอร์ทั้งหมด (rollback) แจ้งลูกค้าว่าของหมด
            $stmt_stock_check->bind_param("i", $item['item_id']);
            $stmt_stock_check->execute();
            $stock_check_row = $stmt_stock_check->get_result()->fetch_assoc();
            if ($stock_check_row && intval($stock_check_row['use_stock']) === 1) {
                throw new RuntimeException('stock_insufficient');
            }
        }

        // บันทึกท็อปปิ้ง (ถ้ามี)
        foreach ($item['topping_ids'] as $tid) {
            $stmt_top->bind_param("ii", $order_detail_id, $tid);
            $stmt_top->execute();
        }
    }

    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    $msg = ($exception->getMessage() === 'stock_insufficient')
        ? 'ขออภัยค่ะ มีเมนูบางรายการในตะกร้าที่วัตถุดิบไม่พอแล้ว กรุณาปรับจำนวนหรือเลือกเมนูอื่นแทน'
        : 'เกิดข้อผิดพลาด ไม่สามารถบันทึกคำสั่งซื้อได้ กรุณาลองใหม่อีกครั้ง';
    echo "<script>
        alert(" . json_encode($msg, JSON_UNESCAPED_UNICODE) . ");
        window.history.back();
    </script>";
    exit;
}

// 6. เคลียร์ตะกร้าทิ้งเมื่อสั่งสำเร็จ
unset($_SESSION['cart']);

// 7. "ทานที่ร้าน" (มี table_id จริง) เท่านั้นที่ถือเป็นบิลรวมของโต๊ะ กลับไปหน้าเมนูของโต๊ะเดิมได้
//    (ยังไม่จบรอบ! ลูกค้าอาจสั่งเพิ่ม หรือกดดูสถานะ/บิลรวมได้ จนกว่าร้านจะปิดบิลจริง)
//    ส่วน "สั่งกลับบ้าน" ถือเป็นออเดอร์ส่วนตัวเสมอ แม้จะสแกน QR จากโต๊ะมาก็ตาม (แค่ใช้ QR เป็นทางลัดเข้าเมนู)
//    ไม่ควรไปปนกับบิลรวมของโต๊ะที่อาจมีคนอื่นสั่งทานที่ร้านอยู่ จึงแยกไปดูสถานะออเดอร์ของตัวเองเท่านั้นที่ขั้นตอนที่ 8
if ($table_id && $order_type === 'dine_in') {
    $_SESSION['has_ordered'] = true; // ใช้เช็กตอนโพลว่าเมื่อร้านปิดบิลแล้วให้เด้งออก (ดู qr_table/api_check_bill.php)

    // จำชื่อ/เบอร์ล่าสุดไว้ในเซสชัน เผื่อสั่งเพิ่มรอบถัดไปที่โต๊ะเดียวกัน จะได้กดใช้ซ้ำได้เลยไม่ต้องพิมพ์ใหม่
    if ($online_name !== '') {
        $_SESSION['dinein_last_name'] = $online_name;
        $_SESSION['dinein_last_phone'] = $online_phone;
    }

    header("Location: ../qr_table/menu_dinein.php?table=" . urlencode($_SESSION['table_number'] ?? '') . "&order_success=1&queue_no=" . $daily_order_no);
    exit;
}

// 8. "สั่งกลับบ้าน" ทุกกรณี (ไม่ว่าจะสแกน QR โต๊ะมาหรือเข้าลิงก์ตรงๆ ไม่มีบัญชีลูกค้า):
//    จำ order_id ไว้ใน session เพื่อให้ดูสถานะ/ใบเสร็จของออเดอร์ตัวเองเท่านั้น ไม่ปนกับของคนอื่นที่โต๊ะเดียวกัน
$_SESSION['guest_order_ids'][] = $order_id;
if ($online_name !== '') {
    $_SESSION['dinein_last_name'] = $online_name;
    $_SESSION['dinein_last_phone'] = $online_phone;
}
echo "<script>
    alert('ส่งคำสั่งซื้อเรียบร้อยแล้ว!');
    window.location.href = 'order_detail.php?id=" . $order_id . "';
</script>";
exit;
?>