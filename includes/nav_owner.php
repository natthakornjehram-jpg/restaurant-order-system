<?php
// includes/nav_owner.php
$current_page = basename($_SERVER['PHP_SELF']);

// กำหนดค่าเริ่มต้น ป้องกัน Error กรณีดึงข้อมูลไม่ได้
$owner_name = "ผู้ดูแลระบบ";
$restaurant_name = "Owner System";

// ตรวจสอบ Session ของฝั่งเจ้าของร้าน (สมมติว่าตอน Login คุณตั้งชื่อ Session เป็น owner_id)
if (isset($_SESSION['owner_id'])) {
    $o_id = $_SESSION['owner_id'];

    // ดึงชื่อเจ้าของร้าน และ ชื่อร้านอาหาร จากตาราง owner ตามโครงสร้าง DB ของคุณ
    $stmt = $conn->prepare("SELECT name, restaurant_name FROM owner WHERE owner_id = ?");
    $stmt->bind_param("i", $o_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $o_info = $result->fetch_assoc();
        $owner_name = !empty($o_info['name']) ? $o_info['name'] : "ผู้ดูแลระบบ";
        $restaurant_name = !empty($o_info['restaurant_name']) ? $o_info['restaurant_name'] : "Owner System";
    }
    $stmt->close();
}

// รายการเมนูฝั่งเจ้าของร้าน (วนลูปสร้างลิงก์ ลดการเขียนซ้ำ + ง่ายต่อการเพิ่ม/แก้เมนูในอนาคต)
$owner_nav_items = [
    ['page' => 'dashboard.php',        'icon' => 'bi-speedometer2',      'label' => 'แดชบอร์ด'],
    ['page' => 'manage_orders.php',    'icon' => 'bi-receipt-cutoff',    'label' => 'รายการออเดอร์เข้า'],
    ['page' => 'manage_payments.php',  'icon' => 'bi-wallet2',           'label' => 'จัดการชำระเงิน'],
    ['page' => 'manage_menu.php',      'icon' => 'bi-journal-text',      'label' => 'จัดการเมนูและหมวดหมู่'],
    ['page' => 'manage_toppings.php',  'icon' => 'bi-plus-circle-dotted','label' => 'จัดการท็อปปิ้ง'],
    ['page' => 'manage_tables.php',    'icon' => 'bi-grid-3x3-gap',      'label' => 'จัดการโต๊ะอาหาร'],
    ['page' => 'reports.php',          'icon' => 'bi-bar-chart-line',    'label' => 'สถิติและยอดขาย'],
    ['page' => 'settings.php',         'icon' => 'bi-gear',              'label' => 'ตั้งค่าร้านและเวลาเปิด-ปิด'],
];
?>

<!-- แถบบนสุด: โชว์เฉพาะจอมือถือ (< lg) ใช้เป็นที่เปิดเมนูด้วยปุ่มแฮมเบอร์เกอร์ -->
<nav class="navbar navbar-dark bg-dark shadow-sm fixed-top d-lg-none">
  <div class="container-fluid">
    <div class="d-flex align-items-center">
        <button class="navbar-toggler border-0 shadow-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#ownerSidebar">
          <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand fw-bold" href="../owner/dashboard.php">
            <?= htmlspecialchars($restaurant_name) ?>
        </a>
    </div>

    <div class="d-flex text-white align-items-center pe-2">
        <i class="bi bi-person-circle fs-5 text-warning"></i>
    </div>
  </div>
</nav>

<div class="owner-shell d-flex">
  <div class="offcanvas-lg offcanvas-start owner-sidebar bg-dark text-white" tabindex="-1" id="ownerSidebar">
    <div class="offcanvas-header border-bottom border-secondary">
      <h5 class="offcanvas-title fw-bold text-warning m-0"><i class="bi bi-shop me-2"></i>เมนูจัดการร้าน</h5>
      <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body p-0 d-flex flex-column">
      <div class="owner-sidebar-brand d-none d-lg-block px-4 py-4 border-bottom border-secondary">
        <div class="fw-bold text-warning text-truncate"><?= htmlspecialchars($restaurant_name) ?></div>
        <div class="small text-white-50 text-truncate"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($owner_name) ?></div>
      </div>

      <ul class="nav flex-column flex-grow-1 mt-2 mt-lg-0">
        <?php foreach ($owner_nav_items as $item): ?>
        <li class="nav-item">
          <a class="owner-nav-link nav-link text-white py-3 px-4 <?= ($current_page == $item['page']) ? 'active' : ''; ?>" href="../owner/<?= $item['page'] ?>">
            <span class="owner-nav-icon"><i class="bi <?= $item['icon'] ?>"></i></span>
            <span><?= $item['label'] ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>

      <div class="p-4 border-top border-secondary">
        <a href="../logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold" onclick="return confirm('ยืนยันออกจากระบบ?')">
            <i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ
        </a>
      </div>
    </div>
  </div>

  <div class="owner-main flex-grow-1">
    <?php if ($current_page !== 'dashboard.php'): ?>
    <div class="owner-back-bar px-3 px-lg-4 pt-3 pb-2">
        <a href="../owner/dashboard.php" class="btn btn-sm btn-light rounded-pill shadow-sm fw-bold">
            <i class="bi bi-arrow-left me-1"></i> กลับหน้าหลัก
        </a>
    </div>
    <?php endif; ?>
