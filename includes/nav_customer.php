<?php
// nav_customer.php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    :root {
        --nav-theme: #d35400;
        --nav-hover: #a04000;
        --btn-coffee: #605b5a;  
        --btn-coffee-dark: #3e2723;
    }
    .text-theme { color: var(--nav-theme) !important; }
    .btn-theme { 
        background-color: var(--btn-coffee) !important; 
        color: white !important; border: none; transition: 0.3s; 
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .btn-theme:hover { 
        background-color: var(--btn-coffee-dark) !important; 
        color: white !important; transform: translateY(-2px);
    }
    .btn-outline-theme { 
        border: 2px solid var(--nav-theme) !important; 
        color: var(--nav-theme) !important; 
        background-color: transparent; transition: 0.3s; 
    }
    .btn-outline-theme:hover { 
        background-color: var(--nav-theme) !important; 
        color: white !important; 
    }
    .nav-menu-link {
        color: #3b3b3b !important; padding: 8px 20px !important;
        border-radius: 50px; transition: 0.3s;
    }
    .nav-menu-link:hover { color: var(--nav-theme) !important; background-color: #fdfaf5; }
    .nav-menu-link.active-menu {
        background-color: var(--nav-theme) !important;
        color: white !important;
        box-shadow: 0 4px 12px rgba(211, 84, 0, 0.25);
    }
</style>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container">
        <div class="d-flex align-items-center">
            <button type="button" onclick="history.back()" class="btn btn-sm btn-light rounded-circle shadow-sm me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" title="ย้อนกลับ">
                <i class="bi bi-arrow-left"></i>
            </button>
            <a class="navbar-brand fw-bold text-theme m-0" href="<?= BASE_URL ?>menu.php">
                <i class="bi bi-shop me-2"></i> RANNAIBAAN
            </a>
        </div>

        <button class="navbar-toggler border-0 shadow-none" type="button"
                data-bs-toggle="collapse" data-bs-target="#navCustomer">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navCustomer">

            <ul class="navbar-nav me-auto mb-2 mb-lg-0 mt-2 mt-lg-0">
                <li class="nav-item">
                    <a class="nav-link fw-bold nav-menu-link <?= ($current_page == 'menu.php') ? 'active-menu' : '' ?>"
                       href="<?= BASE_URL ?>menu.php">เมนูอาหาร</a>
                </li>
            </ul>


            <div class="d-flex align-items-center mt-3 mt-lg-0">
                <a href="<?= BASE_URL ?>member/cart.php" class="btn btn-theme rounded-pill px-4 position-relative">
                    <i class="bi bi-cart3 me-1"></i> ตะกร้า
                    <?php if (!empty($_SESSION['cart'])): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= count($_SESSION['cart']) ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
</nav>