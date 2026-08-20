<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-shield-lock-fill me-2"></i> Admin Panel
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i> แดชบอร์ด</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_users.php"><i class="bi bi-people me-1"></i> จัดการผู้ใช้</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php"><i class="bi bi-graph-up me-1"></i> รายงานสถิติ</a>
                </li>
            </ul>
            <div class="d-flex">
                <span class="navbar-text me-3 text-white-50">
                    สวัสดี, <?= $_SESSION['fullname'] ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="bi bi-box-arrow-right me-1"></i> ออกจากระบบ
                </a>
            </div>
        </div>
    </div>
</nav>