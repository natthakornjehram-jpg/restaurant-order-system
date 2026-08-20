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
?>

<nav class="navbar navbar-dark bg-dark shadow-sm fixed-top">
  <div class="container-fluid">
    <div class="d-flex align-items-center">
        <button class="navbar-toggler border-0 shadow-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#ownerSidebar">
          <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand fw-bold" href="../owner/dashboard.php">
            <?= htmlspecialchars($restaurant_name) ?> <span class="fw-light small d-none d-sm-inline">| ระบบจัดการร้าน</span>
        </a>
    </div>
    
    <div class="d-flex text-white align-items-center pe-2">
        <i class="bi bi-person-circle fs-5 me-2 text-warning"></i>
        <span class="d-none d-md-inline"><?= htmlspecialchars($owner_name) ?></span>
    </div>
  </div>
</nav>

<div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="ownerSidebar" style="width: 280px;">
  <div class="offcanvas-header border-bottom border-secondary">
    <h5 class="offcanvas-title fw-bold text-warning"><i class="bi bi-shop me-2"></i>เมนูจัดการร้าน</h5>
    <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="offcanvas"></button>
  </div>
  
  <div class="offcanvas-body p-0">
    <ul class="nav flex-column mt-2">
      
      <li class="nav-item">
        <a class="nav-link text-white py-3 px-4 border-bottom border-secondary <?= ($current_page == 'dashboard.php') ? 'active bg-primary shadow-sm' : ''; ?>" href="../owner/dashboard.php">
          <i class="bi bi-speedometer2 me-2"></i> แดชบอร์ด
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link text-white py-3 px-4 border-bottom border-secondary <?= ($current_page == 'manage_orders.php') ? 'active bg-primary shadow-sm' : ''; ?>" href="../owner/manage_orders.php">
          <i class="bi bi-receipt-cutoff me-2"></i> รายการออเดอร์เข้า
        </a>
      </li>
      
      <li class="nav-item">
        <a class="nav-link text-white py-3 px-4 border-bottom border-secondary <?= ($current_page == 'manage_menu.php') ? 'active bg-primary shadow-sm' : ''; ?>" href="../owner/manage_menu.php">
          <i class="bi bi-journal-text me-2"></i> จัดการเมนูและหมวดหมู่
        </a>
      </li>
      
      <li class="nav-item">
        <a class="nav-link text-white py-3 px-4 border-bottom border-secondary <?= ($current_page == 'manage_toppings.php') ? 'active bg-primary shadow-sm' : ''; ?>" href="../owner/manage_toppings.php">
          <i class="bi bi-plus-circle-dotted me-2"></i> จัดการท็อปปิ้ง
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link text-white py-3 px-4 border-bottom border-secondary <?= ($current_page == 'manage_tables.php') ? 'active bg-primary shadow-sm' : ''; ?>" href="../owner/manage_tables.php">
          <i class="bi bi-grid-3x3 me-2"></i> จัดการโต๊ะอาหาร
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link text-white py-3 px-4 border-bottom border-secondary <?= ($current_page == 'reports.php') ? 'active bg-primary shadow-sm' : ''; ?>" href="../owner/reports.php">
          <i class="bi bi-bar-chart-line me-2"></i> สถิติและยอดขาย
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link text-white py-3 px-4 border-bottom border-secondary <?= ($current_page == 'settings.php') ? 'active bg-primary shadow-sm' : ''; ?>" href="../owner/settings.php">
          <i class="bi bi-gear me-2"></i> ตั้งค่าร้านและเวลาเปิด-ปิด
        </a>
      </li>
      
    </ul>

    <div class="mt-4 px-4 pb-4">
        <a href="../logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold" onclick="return confirm('ยืนยันออกจากระบบ?')">
            <i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ
        </a>
    </div>
  </div>
</div>