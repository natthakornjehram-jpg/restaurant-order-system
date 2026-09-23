<?php
// includes/nav_owner.php
$current_page = basename($_SERVER['PHP_SELF']);

// กำหนดค่าเริ่มต้น ป้องกัน Error กรณีดึงข้อมูลไม่ได้
$owner_name = "ผู้ดูแลระบบ";
$restaurant_name = "Owner System";
$owner_logo_url = "";
$owner_missing_qr = false;

// ตรวจสอบ Session ของฝั่งเจ้าของร้าน (สมมติว่าตอน Login คุณตั้งชื่อ Session เป็น owner_id)
if (isset($_SESSION['owner_id'])) {
    $o_id = $_SESSION['owner_id'];

    // ดึงชื่อเจ้าของร้าน และ ชื่อร้านอาหาร จากตาราง owner ตามโครงสร้าง DB ของคุณ
    $stmt = $conn->prepare("SELECT name, restaurant_name, logo_url, promptpay_qr FROM owner WHERE owner_id = ?");
    $stmt->bind_param("i", $o_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $o_info = $result->fetch_assoc();
        $owner_name = !empty($o_info['name']) ? $o_info['name'] : "ผู้ดูแลระบบ";
        $restaurant_name = !empty($o_info['restaurant_name']) ? $o_info['restaurant_name'] : "Owner System";
        $owner_logo_url = $o_info['logo_url'] ?? '';
        // ยังไม่ได้แนบ QR Code รับเงิน - เตือนไว้ที่เมนู "ตั้งค่าร้าน" เลย เผื่อเจ้าของร้านไม่รู้ว่าต้องมาตั้งค่าตรงนี้
        // (ไม่งั้นลูกค้าเลือกโอนเงินตอนสั่งกลับบ้านแล้วไม่เห็น QR ให้สแกนจ่าย)
        $owner_missing_qr = empty($o_info['promptpay_qr']);
    }
    $stmt->close();
}

// รายการเมนูฝั่งเจ้าของร้าน (วนลูปสร้างลิงก์ ลดการเขียนซ้ำ + ง่ายต่อการเพิ่ม/แก้เมนูในอนาคต)
$owner_nav_items = [
    ['page' => 'dashboard.php',        'icon' => 'bi-speedometer2',      'label' => 'แดชบอร์ด'],
    ['page' => 'manage_orders.php',    'icon' => 'bi-receipt-cutoff',    'label' => 'รายการออเดอร์เข้า'],
    ['page' => 'manage_payments.php',  'icon' => 'bi-wallet2',           'label' => 'จัดการชำระเงิน'],
    ['page' => 'manage_stock.php',     'icon' => 'bi-box-seam',          'label' => 'จัดการคลังสินค้า'],
    ['page' => 'product_list.php',     'icon' => 'bi-upc-scan',          'label' => 'รายการสินค้า'],
    ['page' => 'stock_transactions.php', 'icon' => 'bi-clock-history',   'label' => 'บันทึกรับ-จ่าย'],
    ['page' => 'manage_menu.php',      'icon' => 'bi-journal-text',      'label' => 'จัดการเมนูและหมวดหมู่'],
    ['page' => 'manage_toppings.php',  'icon' => 'bi-plus-circle-dotted','label' => 'จัดการตัวเลือกเสริม'],
    ['page' => 'manage_tables.php',    'icon' => 'bi-grid-3x3-gap',      'label' => 'จัดการโต๊ะอาหาร'],
    ['page' => 'reports.php',          'icon' => 'bi-bar-chart-line',    'label' => 'สถิติและยอดขาย'],
    [
        'page' => 'settings.php', 'icon' => 'bi-gear', 'label' => 'ตั้งค่าร้านและเวลาเปิด-ปิด',
        'warn' => $owner_missing_qr, 'warn_title' => 'ยังไม่ได้แนบ QR Code รับเงิน ลูกค้าโอนเงินตอนสั่งกลับบ้านจะไม่เห็น QR ให้สแกนจ่าย',
    ],
];
?>

<!-- แถบบนสุด: โชว์เฉพาะจอมือถือ (< lg) ใช้เป็นที่เปิดเมนูด้วยปุ่มแฮมเบอร์เกอร์ -->
<nav class="navbar navbar-dark bg-dark shadow-sm fixed-top d-lg-none auto-hide-header">
  <div class="container-fluid">
    <div class="d-flex align-items-center">
        <button class="navbar-toggler border-0 shadow-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#ownerSidebar">
          <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand fw-bold d-flex align-items-center" href="../owner/dashboard.php">
            <?= htmlspecialchars($restaurant_name) ?>
        </a>
    </div>

    <!-- ขอสิทธิ์แจ้งเตือนแบบระบบ (Web Notification) ให้เด้งแจ้ง "ออเดอร์เข้าแล้ว" ได้แม้สลับแท็บ/สลับแอปไปแล้ว
         ซ่อนไว้ก่อนด้วย JS (updateOwnerNotifyBellUI ใน footer_owner.php) โชว์เฉพาะตอนยังไม่เคยขอสิทธิ์เท่านั้น -->
    <button type="button" id="notifyBellMobile" class="btn btn-outline-light btn-sm rounded-circle shadow-sm" style="width: 36px; height: 36px; display: none; align-items: center; justify-content: center;" title="เปิดการแจ้งเตือนออเดอร์ใหม่" onclick="requestOwnerNotifyPermission()">
        <i class="bi bi-bell"></i>
    </button>
  </div>
</nav>

<div class="owner-shell d-flex">
  <div class="offcanvas-lg offcanvas-start owner-sidebar bg-dark text-white" tabindex="-1" id="ownerSidebar">
    <div class="offcanvas-header border-bottom border-secondary d-flex justify-content-between align-items-center">
      <h5 class="offcanvas-title fw-bold text-warning m-0">
        <?php if (!empty($owner_logo_url) && $owner_logo_url !== 'default_logo.png'): ?>
            <img src="<?= BASE_URL ?>assets/images/logos/<?= htmlspecialchars($owner_logo_url) ?>" alt="logo" style="width:36px;height:36px;object-fit:cover;border-radius:50%;" class="me-2">
        <?php else: ?>
            <i class="bi bi-shop me-2"></i>
        <?php endif; ?>
        จัดการร้าน
      </h5>
      <!-- ปุ่มขอสิทธิ์แจ้งเตือนอีกจุด สำหรับตอนเปิดจากจอเดสก์ท็อป (แถบบนสุด d-lg-none ไม่โชว์ตรงนั้น)
           ไม่ใช้ d-none/d-lg-flex ของ Bootstrap เพราะมี !important ชนกับ JS ที่ตั้ง style.display ตรงๆ
           (แถบนี้ถูกซ่อนลอยนอกจอบนมือถืออยู่แล้วโดย offcanvas เอง ไม่โชว์จนกว่าจะกดแฮมเบอร์เกอร์เปิดเมนู) -->
      <button type="button" id="notifyBellDesktop" class="btn btn-outline-warning btn-sm rounded-circle shadow-sm" style="width: 32px; height: 32px; display: none; align-items: center; justify-content: center;" title="เปิดการแจ้งเตือนออเดอร์ใหม่" onclick="requestOwnerNotifyPermission()">
        <i class="bi bi-bell"></i>
      </button>
    </div>

    <div class="offcanvas-body p-0 d-flex flex-column">
      <div class="owner-sidebar-brand d-none d-lg-block px-3 py-3 border-bottom border-secondary">
        <div class="fw-bold text-warning text-truncate"><?= htmlspecialchars($restaurant_name) ?></div>
        <div class="small text-white-50 text-truncate"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($owner_name) ?></div>
      </div>

      <ul class="nav flex-column flex-grow-1 mt-2 mt-lg-0">
        <?php foreach ($owner_nav_items as $item): ?>
        <li class="nav-item">
          <a class="owner-nav-link nav-link text-white py-2 px-3 d-flex align-items-center <?= ($current_page == $item['page']) ? 'active' : ''; ?>" href="../owner/<?= $item['page'] ?>">
            <span class="owner-nav-icon"><i class="bi <?= $item['icon'] ?>"></i></span>
            <span><?= $item['label'] ?></span>
            <?php if ($item['page'] === 'settings.php'): ?>
                <i id="navQrWarnIcon" class="bi bi-exclamation-circle-fill text-warning ms-2" title="<?= htmlspecialchars($item['warn_title'] ?? 'ต้องตรวจสอบการตั้งค่า') ?>" style="<?= empty($item['warn']) ? 'display:none;' : '' ?>"></i>
            <?php elseif (!empty($item['warn'])): ?>
                <i class="bi bi-exclamation-circle-fill text-warning ms-2" title="<?= htmlspecialchars($item['warn_title'] ?? 'ต้องตรวจสอบการตั้งค่า') ?>"></i>
            <?php endif; ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>

      <div class="p-3 border-top border-secondary">
        <a href="../logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold" onclick="return ownerConfirmNavigate(event, 'ยืนยันออกจากระบบ?', '../logout.php')">
            <i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ
        </a>
      </div>
    </div>
  </div>

  <div class="owner-main flex-grow-1">