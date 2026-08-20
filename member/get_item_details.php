<?php
// get_item_details.php
require_once '../includes/db.php';

$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($item_id == 0) {
    echo "<div class='p-4 text-center text-danger'>ไม่พบข้อมูลเมนูนี้</div>";
    exit;
}

// ดึงข้อมูลเมนู
$stmt = $conn->prepare("SELECT * FROM item WHERE item_id = ? AND is_active = 1");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    echo "<div class='p-4 text-center text-danger'>เมนูนี้หมดหรือถูกยกเลิกแล้ว</div>";
    exit;
}

// ดึงรายการท็อปปิ้งที่เปิดขายอยู่
$toppings = $conn->query("SELECT * FROM topping WHERE is_active = 1");
?>

<div class="position-relative">
    <img src="../assets/images/items/<?= htmlspecialchars($item['image_url']) ?>" class="w-100" style="height: 200px; object-fit: cover; border-radius: 15px 15px 0 0;" onerror="this.src='../assets/images/default.jpg'">
    <button type="button" class="btn-close position-absolute top-0 end-0 m-3 bg-white p-2 rounded-circle shadow-sm" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body p-4">
    <h4 class="fw-bold text-dark"><?= htmlspecialchars($item['name']) ?></h4>
    <p class="text-muted small"><?= htmlspecialchars($item['description'] ?? '') ?></p>
    <h5 class="fw-bold text-primary mb-4" id="basePrice" data-price="<?= $item['price'] ?>">฿<?= number_format($item['price'], 0) ?></h5>

    <?php if ($toppings->num_rows > 0): ?>
    <div class="mb-4">
        <label class="fw-bold mb-2">เพิ่มท็อปปิ้ง (เลือกได้หลายอย่าง)</label>
        <?php while($top = $toppings->fetch_assoc()): ?>
            <div class="form-check d-flex justify-content-between align-items-center mb-2 bg-light p-2 rounded-3">
                <div>
                    <input class="form-check-input ms-1 me-2 topping-check" type="checkbox" value="<?= $top['topping_id'] ?>" data-price="<?= $top['price'] ?>" data-name="<?= htmlspecialchars($top['topping_name']) ?>" id="top<?= $top['topping_id'] ?>">
                    <label class="form-check-label" for="top<?= $top['topping_id'] ?>">
                        <?= htmlspecialchars($top['topping_name']) ?>
                    </label>
                </div>
                <span class="text-muted small">+฿<?= number_format($top['price'], 0) ?></span>
            </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <div class="mb-4">
        <label class="fw-bold mb-2">หมายเหตุถึงร้าน (ถ้ามี)</label>
        <textarea class="form-control bg-light border-0" id="itemNote" rows="2" placeholder="เช่น ไม่ใส่ผัก, เผ็ดน้อย..."></textarea>
    </div>

    <div class="d-flex justify-content-center align-items-center mb-2">
        <button class="btn btn-outline-secondary rounded-circle fw-bold" onclick="changeQty(-1)" style="width: 40px; height: 40px;">-</button>
        <span class="mx-4 fs-4 fw-bold" id="itemQty">1</span>
        <button class="btn btn-outline-primary rounded-circle fw-bold" onclick="changeQty(1)" style="width: 40px; height: 40px;">+</button>
    </div>
</div>

<div class="modal-footer border-0 p-3 bg-white">
    <button class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-sm" onclick="addToCart(<?= $item['item_id'] ?>, '<?= htmlspecialchars($item['name']) ?>')">
        เพิ่มลงตะกร้า - <span id="displayTotal">฿<?= number_format($item['price'], 0) ?></span>
    </button>
</div>

<script>
    // สคริปต์คำนวณราคาแบบ Real-time ใน Modal
    let qty = 1;
    let basePrice = parseFloat(document.getElementById('basePrice').getAttribute('data-price'));

    function changeQty(amount) {
        qty += amount;
        if (qty < 1) qty = 1;
        document.getElementById('itemQty').innerText = qty;
        calculateTotal();
    }

    // จับเหตุการณ์เมื่อกดเลือกท็อปปิ้งให้คำนวณราคาใหม่
    document.querySelectorAll('.topping-check').forEach(cb => {
        cb.addEventListener('change', calculateTotal);
    });

    function calculateTotal() {
        let toppingPrice = 0;
        document.querySelectorAll('.topping-check:checked').forEach(cb => {
            toppingPrice += parseFloat(cb.getAttribute('data-price'));
        });
        
        let total = (basePrice + toppingPrice) * qty;
        document.getElementById('displayTotal').innerText = '฿' + total;
    }

    // ฟังก์ชันเก็บลง LocalStorage (ตะกร้าสินค้า)
    function addToCart(id, name) {
        let toppings = [];
        let toppingPrice = 0;
        
        document.querySelectorAll('.topping-check:checked').forEach(cb => {
            toppings.push({
                id: cb.value,
                name: cb.getAttribute('data-name'),
                price: parseFloat(cb.getAttribute('data-price'))
            });
            toppingPrice += parseFloat(cb.getAttribute('data-price'));
        });

        let note = document.getElementById('itemNote').value;
        let totalItemPrice = (basePrice + toppingPrice) * qty;

        let cartItem = {
            itemId: id,
            name: name,
            qty: qty,
            price: basePrice,
            toppings: toppings,
            note: note,
            totalPrice: totalItemPrice
        };

        // ดึงตะกร้าเดิมมาเพิ่มของใหม่
        let cart = JSON.parse(localStorage.getItem('rannaibaan_cart')) || [];
        cart.push(cartItem);
        localStorage.setItem('rannaibaan_cart', JSON.stringify(cart));

        // ปิด Modal และอัปเดตปุ่มลอย
        itemModal.hide();
        updateCartFloat();
    }
</script>