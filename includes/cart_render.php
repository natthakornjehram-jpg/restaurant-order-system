<?php
// includes/cart_render.php
// เรนเดอร์แถวสินค้า 1 ชิ้นในตะกร้า (ใช้ร่วมกันทั้งตอนโหลดหน้า menu_dinein.php ปกติ และตอน member/cart_action.php
// ตอบกลับ JSON ให้ JS แทรกกลับเข้าไปในหน้าแบบไม่ต้องรีโหลด - กันไม่ให้ต้องเขียน HTML โครงสร้างเดียวกันซ้ำสองที่
// (PHP กับ JS) ซึ่งเคยเป็นต้นเหตุบั๊กมาแล้วรอบก่อนหน้า (เครื่องหมาย " ชนกันตอนต่อสตริง HTML ใน JS)
function render_dinein_cart_row($key, $item, $return_url) {
    $img_path = !empty($item['image']) ? "../assets/images/items/" . $item['image'] : "../assets/images/items/default_food.jpg";
    ob_start();
    ?>
    <div class="item-row d-flex align-items-center" id="cartRow<?= $key ?>" data-key="<?= $key ?>">
        <img src="<?= htmlspecialchars($img_path) ?>" class="rounded-3 me-3" style="width: 70px; height: 70px; object-fit: cover;" onerror="this.src='../assets/images/items/default_food.jpg'">
        <div class="flex-grow-1 pe-2">
            <h6 class="fw-bold mb-1"><?= htmlspecialchars($item['name']) ?></h6>
            <div class="small text-muted mb-1"><?= !empty($item['topping_names']) ? htmlspecialchars($item['topping_names']) : 'ดั้งเดิม'; ?></div>
            <?php if (!empty($item['note'])): ?>
                <div class="small text-danger"><i class="bi bi-chat-text"></i> <?= htmlspecialchars($item['note']) ?></div>
            <?php endif; ?>
            <form method="POST" action="../member/cart_action.php" class="d-inline cart-mini-form" data-cart-action="remove">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="id" value="<?= $key ?>">
                <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url) ?>">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <button type="submit" class="btn btn-link text-danger small text-decoration-none fw-bold p-0 border-0 align-baseline"><i class="bi bi-trash"></i> ลบ</button>
            </form>
        </div>
        <div class="text-end" style="min-width: 110px;">
            <div class="fw-bold text-dark mb-2"><span class="cart-row-subtotal">฿<?= number_format($item['price'] * $item['quantity'], 0) ?></span></div>
            <div class="d-flex align-items-center justify-content-end gap-2">
                <form method="POST" action="../member/cart_action.php" class="d-inline cart-mini-form" data-cart-action="decrease">
                    <input type="hidden" name="action" value="decrease">
                    <input type="hidden" name="id" value="<?= $key ?>">
                    <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url) ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary rounded-circle qty-step-btn">−</button>
                </form>
                <span class="fw-bold cart-row-qty"><?= $item['quantity'] ?></span>
                <form method="POST" action="../member/cart_action.php" class="d-inline cart-mini-form" data-cart-action="increase">
                    <input type="hidden" name="action" value="increase">
                    <input type="hidden" name="id" value="<?= $key ?>">
                    <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url) ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary rounded-circle qty-step-btn">+</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// เรนเดอร์ทุกแถวในตะกร้าปัจจุบันต่อกัน (ใช้ตอนตอบ JSON กลับหลังแก้ตะกร้า เพื่ออัปเดตทั้งลิสต์ให้ตรงกับ session ล่าสุด)
function render_dinein_cart_rows($cart, $return_url) {
    $html = '';
    foreach ($cart as $key => $item) {
        $html .= render_dinein_cart_row($key, $item, $return_url);
    }
    return $html;
}
