<?php
include 'auth_admin.php';
require_once '../includes/db.php';


// 1. ดึงอันดับร้านค้าที่มีออเดอร์เยอะที่สุด (ปรับเป็น order_status และใช้สถานะ served ตาม SQL ของคุณ)
$sql_top_orders = "SELECT r.restaurant_name, COUNT(o.order_id) as order_count 
                   FROM orders o 
                   JOIN restaurants r ON o.restaurant_id = r.restaurant_id 
                   WHERE o.order_status = 'served' 
                   GROUP BY r.restaurant_id 
                   ORDER BY order_count DESC LIMIT 5";
$top_orders = $conn->query($sql_top_orders);

// 2. สถิติรวมปริมาณการใช้งานระบบ (ปรับเป็น order_status)
$total_completed_res = $conn->query("SELECT COUNT(*) as total FROM orders WHERE order_status = 'served'");
$total_completed = ($total_completed_res) ? $total_completed_res->fetch_assoc()['total'] : 0;

$total_customers_res = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'customer'");
$total_customers = ($total_customers_res) ? $total_customers_res->fetch_assoc()['total'] : 0;

$total_menus_res = $conn->query("SELECT COUNT(*) as total FROM menus WHERE is_available = 1");
$total_menus = ($total_menus_res) ? $total_menus_res->fetch_assoc()['total'] : 0;

include 'header_admin.php'; 
include 'nav_admin.php';
 // เพิ่มบรรทัดนี้เพื่อให้เมนูโผล่มาครับ
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold" style="color: #3e2723;"><i class="bi bi-bar-chart-line me-2"></i>สถิติการใช้งานระบบ</h2>
        <div class="badge bg-brown rounded-pill px-3 py-2" style="background: #795548; color: white;">Admin View</div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-white h-100">
                <div class="mx-auto mb-3" style="width: 50px; height: 50px; background: #efebe9; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-bag-check text-brown" style="color: #795548; font-size: 1.5rem;"></i>
                </div>
                <div class="small text-muted">ออเดอร์ที่สำเร็จแล้ว</div>
                <div class="h3 fw-bold mb-0"><?= number_format($total_completed) ?> <span class="fs-6 fw-normal">รายการ</span></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-white h-100">
                <div class="mx-auto mb-3" style="width: 50px; height: 50px; background: #efebe9; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-people text-brown" style="color: #795548; font-size: 1.5rem;"></i>
                </div>
                <div class="small text-muted">จำนวนสมาชิกในระบบ</div>
                <div class="h3 fw-bold mb-0"><?= number_format($total_customers) ?> <span class="fs-6 fw-normal">คน</span></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-white h-100">
                <div class="mx-auto mb-3" style="width: 50px; height: 50px; background: #efebe9; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-moped text-brown" style="color: #795548; font-size: 1.5rem;"></i>
                </div>
                <div class="small text-muted">เมนูที่พร้อมให้บริการ</div>
                <div class="h3 fw-bold mb-0"><?= number_format($total_menus) ?> <span class="fs-6 fw-normal">รายการ</span></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold mb-0"><i class="bi bi-fire text-danger me-2"></i>5 อันดับร้านค้ายอดนิยม (ตามจำนวนออเดอร์)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">อันดับ</th>
                                <th>ชื่อร้านค้า</th>
                                <th class="text-end pe-4">จำนวนครั้งที่สั่งซื้อ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            if($top_orders && $top_orders->num_rows > 0):
                                while($row = $top_orders->fetch_assoc()): 
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold">#<?= $rank++ ?></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($row['restaurant_name']) ?></td>
                                <td class="text-end pe-4">
                                    <span class="badge rounded-pill bg-dark px-3"><?= number_format($row['order_count']) ?> ออเดอร์</span>
                                </td>
                            </tr>
                            <?php 
                                endwhile; 
                            else: 
                                echo "<tr><td colspan='3' class='text-center py-4 text-muted'>ยังไม่มีข้อมูลการสั่งซื้อที่สำเร็จ</td></tr>";
                            endif; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <h6 class="fw-bold mb-3">สรุปสถานะร้านค้า</h6>
                <?php
                    // ปรับตามคอลัมน์ใน SQL: is_online_open และ status
                    $online = $conn->query("SELECT COUNT(*) as total FROM restaurants WHERE is_online_open = 1 AND status = 'approved'")->fetch_assoc()['total'];
                    $offline = $conn->query("SELECT COUNT(*) as total FROM restaurants WHERE is_online_open = 0 AND status = 'approved'")->fetch_assoc()['total'];
                ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="small">🟢 ร้านที่เปิดออนไลน์</span>
                    <span class="fw-bold text-success"><?= number_format($online) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="small">🔴 ร้านที่ปิดรับออเดอร์</span>
                    <span class="fw-bold text-muted"><?= number_format($offline) ?></span>
                </div>
                <hr>
                <p class="small text-muted fst-italic">ข้อมูลแสดงจำนวนร้านค้าที่ผ่านการอนุมัติแล้วเท่านั้น</p>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer_customer.php'; ?>