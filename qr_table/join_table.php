<?php
// qr_table/join_table.php
// หน้าแยกต่างหากสำหรับกรอก "รหัสร่วมโต๊ะ 4 หลัก" (แยกออกมาจาก menu_dinein.php ให้เป็นสัดส่วน)
session_start();
require_once '../includes/db.php';
require_once '../includes/csrf.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!empty($_SESSION['session_ended'])) {
    echo "<script>window.location='../index.php';</script>";
    exit;
}

if (!isset($_SESSION['table_id'])) {
    echo "<script>alert('กรุณาสแกน QR Code ที่โต๊ะก่อนครับ'); window.location='../index.php';</script>";
    exit;
}

$table_display = $_SESSION['table_number'] ?? ($_GET['table'] ?? 'ไม่ทราบโต๊ะ');
$menu_url = 'menu_dinein.php?table=' . urlencode($table_display);

$tbl_stmt = $conn->prepare("SELECT join_code FROM restauranttable WHERE table_id = ?");
$tbl_stmt->bind_param("i", $_SESSION['table_id']);
$tbl_stmt->execute();
$tbl_data = $tbl_stmt->get_result()->fetch_assoc();

$db_join_code = $tbl_data['join_code'] ?? '';

$is_verified_join = (!empty($_SESSION['has_ordered']) || (isset($_SESSION['user_join_code']) && $_SESSION['user_join_code'] === $db_join_code));

// ไม่มีอะไรต้องกรอกแล้ว (ยังไม่มีรหัส/ยืนยันไปแล้ว/ไม่ใช่โหมดทานที่ร้าน) -> เด้งกลับเมนูเลย ไม่ต้องมาหน้านี้
// เช็คจาก join_code อย่างเดียว ไม่เช็คสถานะโต๊ะ เพราะรหัสถูกสุ่มไว้ตั้งแต่ตอนสั่งออเดอร์แรก
// ก่อนที่ร้านจะกดอนุมัติเปิดโต๊ะเสียอีก (สถานะตอนนั้นอาจยังเป็น "available" อยู่)
// รหัสร่วมโต๊ะมีไว้กันคนแปลกหน้ามาสั่งปนกับโต๊ะที่ทานร่วมกันเท่านั้น "สั่งกลับบ้าน" เป็นออเดอร์ส่วนตัว ไม่ต้องกรอกรหัส
if (empty($db_join_code) || $is_verified_join || ($_SESSION['order_type'] ?? '') !== 'dine_in') {
    header("Location: $menu_url");
    exit;
}

$pin_error = "";
// ให้หน้านี้ตอบเป็น JSON แทนการรีโหลดทั้งหน้าได้ ถ้าคำขอมาจาก fetch() ของ JS - ตรรกะตรวจสอบ/จำกัดจำนวนครั้งด้านล่างเหมือนเดิมทุกอย่าง
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// จำกัดจำนวนครั้งที่กรอกรหัสผิด: ไม่เกิน 10 ครั้งต่อโต๊ะภายใน 15 นาที กันสคริปต์เดารหัส 4 หลัก (9000 ความเป็นไปได้)
// นับจากตาราง join_pin_attempts ที่ผูกกับ table_id ไม่ใช่ session กันบายพาสด้วยการล้างคุกกี้/เปิดแท็บใหม่
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$fail_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM join_pin_attempts WHERE table_id = ? AND success = 0 AND created_at >= (NOW() - INTERVAL 15 MINUTE)");
$fail_stmt->bind_param("i", $_SESSION['table_id']);
$fail_stmt->execute();
$pin_fail_count = intval($fail_stmt->get_result()->fetch_assoc()['cnt']);

if (isset($_POST['submit_join_pin'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $pin_error = "คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
    } elseif ($pin_fail_count >= 10) {
        $pin_error = "กรอกรหัสผิดเกินจำนวนที่กำหนด กรุณารอสักครู่แล้วลองใหม่ หรือสอบถามรหัสจากเพื่อนร่วมโต๊ะครับ";
    } else {
        $input_pin = trim($_POST['join_pin'] ?? '');
        if ($input_pin === $db_join_code) {
            $log_stmt = $conn->prepare("INSERT INTO join_pin_attempts (table_id, ip_address, success) VALUES (?, ?, 1)");
            $log_stmt->bind_param("is", $_SESSION['table_id'], $client_ip);
            $log_stmt->execute();

            $_SESSION['user_join_code'] = $db_join_code;
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'redirect' => $menu_url]);
                exit;
            }
            header("Location: $menu_url");
            exit;
        }
        $log_stmt = $conn->prepare("INSERT INTO join_pin_attempts (table_id, ip_address, success) VALUES (?, ?, 0)");
        $log_stmt->bind_param("is", $_SESSION['table_id'], $client_ip);
        $log_stmt->execute();
        $pin_fail_count++;
        $pin_error = "รหัสร่วมโต๊ะไม่ถูกต้อง กรุณาสอบถามรหัสจากเพื่อนร่วมโต๊ะครับ";
    }

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $pin_error, 'locked' => $pin_fail_count >= 10]);
        exit;
    }
}

include '../includes/header_dinein.php';
include '../includes/nav_dinein.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/menu-dinein.css">

<div class="container py-4">
    <div class="card border-0 shadow rounded-4 p-4 my-4 text-center mx-auto" style="max-width: 420px;">
        <i class="bi bi-shield-lock-fill text-warning display-3 mb-2"></i>
        <h4 class="fw-bold text-dark">โต๊ะนี้กำลังเปิดบริการอยู่</h4>
        <p class="text-muted small mb-3">กรุณากรอก <b>รหัสร่วมโต๊ะ 4 หลัก</b> เพื่อร่วมสั่งอาหารกับเพื่อนในโต๊ะเดียวกันครับ (ไม่ว่าจะทานที่ร้านหรือสั่งกลับบ้าน)<br>(ดูรหัสได้จากหน้าจอมือถือของเพื่อนร่วมโต๊ะที่สแกนเป็นคนแรก)</p>

        <div id="joinPinErrorBox">
        <?php if ($pin_error): ?>
            <div class="alert alert-danger rounded-3 py-2 small mb-3"><?= htmlspecialchars($pin_error) ?></div>
        <?php endif; ?>
        </div>

        <form method="POST" class="d-grid gap-3" id="joinPinForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="text" name="join_pin" id="joinPinInput" inputmode="numeric" pattern="[0-9]*" maxlength="4"
                   class="form-control form-control-lg text-center fw-bold rounded-3" placeholder="กรอกรหัส 4 หลัก" required autofocus
                   style="letter-spacing: 10px; font-size: 1.8rem;" <?= $pin_fail_count >= 10 ? 'disabled' : '' ?>>
            <button type="submit" name="submit_join_pin" id="joinPinSubmitBtn" class="btn btn-warning py-3 rounded-pill fw-bold shadow" style="background-color: var(--cafe-brown); color: #fff; border: none;" <?= $pin_fail_count >= 10 ? 'disabled' : '' ?>>
                <i class="bi bi-key-fill me-1"></i> ยืนยันเข้าร่วมโต๊ะ
            </button>
        </form>
    </div>
</div>

<script>
    // เอาไว้เฉพาะตัวเลข 0-9 และส่งฟอร์มอัตโนมัติทันทีที่ครบ 4 หลัก ให้ลื่นไหลไม่ต้องกดปุ่มเอง
    const joinPinInput = document.getElementById('joinPinInput');
    const joinPinForm = document.getElementById('joinPinForm');
    const joinPinSubmitBtn = document.getElementById('joinPinSubmitBtn');
    const joinPinErrorBox = document.getElementById('joinPinErrorBox');

    joinPinInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 4);
        if (this.value.length === 4) {
            joinPinForm.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    // ส่งฟอร์มแบบ AJAX แทนการรีโหลดทั้งหน้า - ตรรกะตรวจสอบ/จำกัดจำนวนครั้งฝั่งเซิร์ฟเวอร์เหมือนเดิมทุกอย่าง
    joinPinForm.addEventListener('submit', function (e) {
        e.preventDefault();
        joinPinErrorBox.innerHTML = '';

        // ต้องสร้าง FormData "ก่อน" ปิด (disabled) ช่องกรอกเสมอ เพราะฟิลด์ที่ disabled จะไม่ถูกรวมเข้า FormData
        // เลยตาม spec ของ HTML (เจอบั๊กนี้จากการทดสอบจริง - ปิดช่องก่อนแล้วค่อยสร้าง FormData ทำให้ join_pin หายไปเงียบๆ)
        const fd = new FormData(joinPinForm);
        // new FormData(form) ไม่ใส่ name/value ของปุ่ม submit ที่กดให้อัตโนมัติ (submit_join_pin เป็นชื่อปุ่ม ไม่ใช่ input ซ่อน)
        fd.append('submit_join_pin', '1');

        joinPinInput.disabled = true;
        joinPinSubmitBtn.disabled = true;

        fetch(joinPinForm.getAttribute('action') || 'join_table.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.href = data.redirect;
                return; // คงปุ่มปิดไว้จนกว่าหน้าจะเปลี่ยนจริง กันกดซ้ำระหว่างรอ
            }
            joinPinErrorBox.innerHTML = '<div class="alert alert-danger rounded-3 py-2 small mb-3">' + (data.error || 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง') + '</div>';
            joinPinInput.value = '';
            joinPinInput.disabled = !!data.locked;
            joinPinSubmitBtn.disabled = !!data.locked;
            if (!data.locked) joinPinInput.focus();
        })
        .catch(function () {
            joinPinErrorBox.innerHTML = '<div class="alert alert-danger rounded-3 py-2 small mb-3">เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ กรุณาลองใหม่อีกครั้ง</div>';
            joinPinInput.disabled = false;
            joinPinSubmitBtn.disabled = false;
        });
    });
</script>

<?php include '../includes/footer_dinein.php'; ?>
