<?php
// admin/approve_users.php
include 'auth_admin.php';
require_once '../includes/db.php';

// 2. ดึงรายชื่อทุกคนที่สถานะเป็น "รออนุมัติ" (is_approved = 0)
// คัดเฉพาะคนที่จะเป็น Owner หรือ Customer ที่สมัครสมาชิกเข้ามา
$sql = "SELECT user_id, fullname, username, email, role, created_at 
        FROM users 
        WHERE is_approved = 0 AND role IN ('owner', 'customer')
        ORDER BY created_at ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อนุมัติผู้ใช้งานใหม่ - Raauaibaan Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Mitr&family=Sarabun&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; font-family: 'Sarabun', sans-serif; }
        .admin-card { border-radius: 25px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); background: white; }
        h2 { font-family: 'Mitr', sans-serif; color: #2c3e50; }
        .table thead { background-color: #f8f9fa; color: #6c757d; font-size: 0.85rem; text-transform: uppercase; }
        .badge-role { border-radius: 50px; padding: 6px 15px; font-weight: 600; font-size: 0.75rem; }
        .btn-approve { background-color: #2fb344; color: white; border-radius: 50px; border: none; transition: 0.3s; }
        .btn-approve:hover { background-color: #248f36; transform: scale(1.05); color: white; }
        .btn-reject { background-color: #fff1f0; color: #e03131; border-radius: 50px; border: 1px solid #ffa8a8; transition: 0.3s; }
        .btn-reject:hover { background-color: #f03e3e; color: white; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
        <h2><i class="bi bi-person-check-fill me-2 text-success"></i> รายการรออนุมัติ</h2>
        <span class="badge bg-dark rounded-pill px-3 py-2">รอดำเนินการ: <?= $result->num_rows ?> รายการ</span>
    </div>

    <div class="card admin-card p-4">
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>วันที่สมัคร</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th class="text-center">ประเภทผู้ใช้</th>
                            <th>ข้อมูลติดต่อ</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($u = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="small text-muted">
                                    <?= date('d/m/Y', strtotime($u['created_at'])) ?><br>
                                    <span style="font-size: 0.7rem;"><?= date('H:i', strtotime($u['created_at'])) ?> น.</span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($u['fullname']) ?></div>
                                    <small class="text-muted">@<?= htmlspecialchars($u['username']) ?></small>
                                </td>
                                <td class="text-center">
                                    <?php if ($u['role'] === 'owner'): ?>
                                        <span class="badge badge-role bg-info text-white shadow-sm">
                                            <i class="bi bi-shop me-1"></i> เจ้าของร้าน
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-role bg-light text-secondary border shadow-sm">
                                            <i class="bi bi-person me-1"></i> ลูกค้าสมาชิก
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <i class="bi bi-envelope-at me-1 text-muted"></i> <?= htmlspecialchars($u['email']) ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group gap-2">
                                        <a href="approve_action.php?id=<?= $u['user_id'] ?>&status=1" 
                                           class="btn btn-approve btn-sm px-3 fw-bold"
                                           onclick="return confirm('คุณต้องการอนุมัติผู้ใช้คนนี้ใช่หรือไม่?')">
                                            อนุมัติ
                                        </a>
                                        <a href="approve_action.php?id=<?= $u['user_id'] ?>&status=2" 
                                           class="btn btn-reject btn-sm px-3 fw-bold"
                                           onclick="return confirm('ยืนยันการปฏิเสธคำขอสมัครสมาชิก?')">
                                            ปฏิเสธ
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="bi bi-check-circle text-success opacity-25" style="font-size: 5rem;"></i>
                </div>
                <h5 class="text-muted fw-bold">เย้! ไม่มีคำขอค้างอยู่แล้ว</h5>
                <p class="small text-secondary">ข้อมูลผู้ใช้งานทุกคนได้รับการตรวจสอบเรียบร้อย</p>
                <a href="dashboard.php" class="btn btn-outline-primary btn-sm rounded-pill px-4 mt-2">กลับหน้า Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>