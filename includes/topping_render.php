<?php
// includes/topping_render.php
// เรนเดอร์แถวตัวเลือกเสริม 1 แถว และการ์ดหมวดหมู่ทั้งใบ (หัวข้อ+ตาราง) ใช้ร่วมกันทั้งตอนโหลดหน้า
// owner/manage_toppings.php ปกติ และตอบกลับ JSON หลังเพิ่ม/แก้ไขแบบ AJAX (กันเขียน HTML ซ้ำสองที่)

function render_owner_topping_row($row) {
    $is_in_stock = $row['is_active'] == 1;
    // json_encode + htmlspecialchars (ไม่ใช่ htmlspecialchars อย่างเดียว) เพราะค่านี้ถูกใส่ใน onclick="..."
    // เป็นสตริง JS ด้วย - htmlspecialchars(ENT_QUOTES) เข้ารหัส ' เป็น &#039; ซึ่งเบราว์เซอร์จะถอดรหัส
    // HTML entity กลับเป็น ' ก่อนส่งให้ JS parser เสมอ ทำให้หลุดออกจากสตริง JS ได้อยู่ดีถ้าชื่อมี '
    $t_name_js = htmlspecialchars(json_encode($row['topping_name'], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
    ob_start();
    ?>
    <tr id="row-<?= $row['topping_id'] ?>" class="<?= !$is_in_stock ? 'out-of-stock' : '' ?>">
        <td class="ps-4 fw-bold topping-name">
            <?= htmlspecialchars($row['topping_name']) ?>
            <?php if (!empty($row['use_stock'])): ?>
                <i class="bi bi-box-seam text-secondary ms-1" style="font-size: 0.8rem;" title="ติดตามคลังสินค้าอยู่ (ดูจำนวนคงเหลือได้ที่หน้าจัดการคลังสินค้า)"></i>
            <?php endif; ?>
        </td>
        <td class="text-success fw-bold">+<?= number_format($row['price'], 2) ?> บาท</td>
        <td class="text-center">
            <div class="form-check form-switch d-inline-block m-0">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="active_<?= $row['topping_id'] ?>"
                       onchange="toggleToppingActive(<?= $row['topping_id'] ?>, this.checked)"
                       <?= $is_in_stock ? 'checked' : '' ?>>
            </div>
        </td>
        <td class="text-center">
            <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1"
                    onclick="openToppingModal(<?= $row['topping_id'] ?>, <?= $t_name_js ?>, <?= $row['price'] ?>, <?= $row['topping_cat_id'] ?>, <?= !empty($row['use_stock']) ? 1 : 0 ?>, <?= (int)$row['stock_qty'] ?>, <?= (int)$row['is_active'] ?>)">
                <i class="bi bi-pencil-square"></i> แก้ไข
            </button>
            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="confirmDeleteTopping(<?= $row['topping_id'] ?>, <?= $t_name_js ?>)">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

// $cat_toppings: array ของแถวตัวเลือกเสริมในหมวดนี้ (แต่ละแถวต้องมี topping_cat_id ติดมาด้วยเสมอ)
// ปุ่มขึ้น/ลงเรนเดอร์แบบ "เปิดใช้งานเสมอ" แล้วปล่อยให้ JS (refreshCatMoveButtons) ปรับปิด/เปิดให้ตรงตำแหน่งจริงทันทีหลังแทรก/แทนที่ DOM
// กันต้องคำนวณตำแหน่งจริงในกลุ่มทั้งหมดใหม่ทุกครั้งฝั่ง PHP ตอนตอบ AJAX (ซึ่งจะยุ่งยากกว่ามาก)
function render_owner_topping_category_block($cat, $cat_toppings) {
    $current_cat_id = $cat['topping_cat_id'];
    $cat_toppings_json = json_encode(array_map(function ($t) {
        return ['id' => $t['topping_id'], 'name' => $t['topping_name'], 'price' => $t['price']];
    }, $cat_toppings), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS);
    $cat_name_json = json_encode($cat['topping_cat_name'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS);
    ob_start();
    ?>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 cat-block" id="cat-block-<?= $current_cat_id ?>">
        <div class="card-header bg-light border-0 py-3 ps-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold m-0 text-dark"><?= htmlspecialchars($cat['topping_cat_name']) ?></h5>
            <div class="d-flex align-items-center gap-1">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center cat-move-up" style="width: 32px; height: 32px; padding: 0;"
                        onclick="moveCategory(<?= $current_cat_id ?>, 'up')" title="ย้ายขึ้น">
                    <i class="bi bi-arrow-up"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center cat-move-down" style="width: 32px; height: 32px; padding: 0;"
                        onclick="moveCategory(<?= $current_cat_id ?>, 'down')" title="ย้ายลง">
                    <i class="bi bi-arrow-down"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary rounded-pill ms-1"
                        onclick='openCatModal(<?= $current_cat_id ?>, <?= $cat_name_json ?>, <?= $cat_toppings_json ?>)'>
                    <i class="bi bi-pencil-square"></i> แก้ไขหมวดนี้
                </button>
            </div>
        </div>
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4 py-2 small" style="width: 34%;">ชื่อตัวเลือกเสริม</th>
                    <th class="py-2 small" style="width: 20%;">ราคาที่บวกเพิ่ม</th>
                    <th class="text-center py-2 small" style="width: 16%;">เปิดขาย</th>
                    <th class="text-center py-2 small" style="width: 30%;">จัดการ</th>
                </tr>
            </thead>
            <tbody id="topping-tbody-<?= $current_cat_id ?>">
                <?php foreach ($cat_toppings as $row) { echo render_owner_topping_row($row); } ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}
