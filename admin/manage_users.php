<?php
// admin/manage_users.php
include 'auth_admin.php';
require_once '../includes/db.php';

// กำหนดค่า ID แอดมินปัจจุบัน (เพื่อไม่ให้โชว์ตัวเองในรายการ)
$current_admin_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้ (ใช้ phone แทน email ตามโครงสร้าง DB ของคุณ)
$sql = "SELECT user_id, fullname, username, phone, role, is_approved, created_at 
        FROM users 
        WHERE user_id != $current_admin_id 
        ORDER BY created_at DESC";

$result = $conn->query($sql);

// ตรวจสอบว่า Query สำเร็จไหม ถ้าไม่สำเร็จให้แจ้ง Error
if (!$result) {
    die("SQL Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้งาน - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Mitr&family=Sarabun&display=swap" rel="stylesheet">
    
    <style>
        body { background-color: #f4f7f6; font-family: 'Sarabun', sans-serif; }
        h2 { font-family: 'Mitr', sans-serif; color: #2c3e50; }
        .card-table { border-radius: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); background: white; }
        .table thead { background-color: #f8f9fa; }
        .badge-role { border-radius: 50px; padding: 5px 12px; font-weight: 600; font-size: 0.75rem; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .btn-action { border-radius: 50px; padding: 5px 15px; font-size: 0.85rem; font-weight: bold; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-people-fill me-2 text-primary"></i> จัดการผู้ใช้งานทั้งหมด</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left"></i> กลับหน้าหลัก
        </a>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card card-table p-3 text-center border-start border-primary border-4">
                <small class="text-muted fw-bold">ผู้ใช้ทั้งหมดในระบบ</small>
                <h3 class="fw-bold mb-0"><?= $result->num_rows ?></h3>
            </div>
        </div>
    </div>

    <div class="card card-table shadow-sm">
        <div class="table-responsive p-4">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ผู้ใช้งาน</th>
                        <th>ข้อมูลติดต่อ</th>
                        <th class="text-center">ระดับสิทธิ์</th>
                        <th class="text-center">สถานะ</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['fullname']) ?></div>
                                    <small class="text-muted">@<?= htmlspecialchars($row['username']) ?></small>
                                </td>
                                <td>
                                    <div class="small fw-bold"><i class="bi bi-telephone me-1 text-primary"></i><?= htmlspecialchars($row['phone']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-calendar3 me-1"></i>สมัครเมื่อ: <?= date('d/m/Y', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td class="text-center">
                                    <?php 
                                        $role_class = ($row['role'] === 'owner') ? 'bg-info text-white shadow-sm' : 'bg-light text-dark border';
                                        $role_name = ($row['role'] === 'owner') ? 'เจ้าของร้าน' : 'ลูกค้าสมาชิก';
                                    ?>
                                    <span class="badge badge-role <?= $role_class ?>"><?= $role_name ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['is_approved'] == 0): ?>
                                        <span class="text-warning small fw-bold"><i class="status-dot bg-warning"></i> รออนุมัติ</span>
                                    <?php elseif ($row['is_approved'] == 1): ?>
                                        <span class="text-success small fw-bold"><i class="status-dot bg-success"></i> อนุมัติแล้ว</span>
                                    <?php else: ?>
                                        <span class="text-danger small fw-bold"><i class="status-dot bg-danger"></i> ถูกปฏิเสธ</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <?php if ($row['is_approved'] != 1): ?>
                                            <a href="approve_action.php?id=<?= $row['user_id'] ?>&status=1" 
                                               class="btn btn-success btn-sm btn-action me-1 shadow-sm"
                                               onclick="return confirm('ยืนยันการอนุมัติผู้ใช้คนนี้?')">อนุมัติ</a>
                                        <?php endif; ?>

                                        <?php if ($row['is_approved'] == 0): ?>
                                            <a href="approve_action.php?id=<?= $row['user_id'] ?>&status=2" 
                                               class="btn btn-outline-danger btn-sm btn-action me-1"
                                               onclick="return confirm('ปฏิเสธคำขอ?')">ปฏิเสธ</a>
                                        <?php endif; ?>

                                        <a href="delete_user.php?id=<?= $row['user_id'] ?>" 
                                           class="btn btn-light btn-sm rounded-circle text-danger border shadow-sm"
                                           onclick="return confirm('⚠️ ลบผู้ใช้คนนี้ออกจากระบบถาวรใช่หรือไม่?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">ไม่พบข้อมูลผู้ใช้งานคนอื่นในระบบ</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>