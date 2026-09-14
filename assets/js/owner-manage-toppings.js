// ย้ายลำดับการแสดงหมวดหมู่ขึ้น/ลง (สลับกับหมวดที่อยู่ติดกัน) แล้วรีโหลดหน้าให้เห็นลำดับใหม่
function moveCategory(id, direction) {
    const formData = new FormData();
    formData.append('action', 'reorder_category');
    formData.append('cat_id', id);
    formData.append('direction', direction);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('manage_toppings.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            ownerNotify(data.error || 'ย้ายลำดับไม่สำเร็จ', 'error');
        }
    })
    .catch(() => ownerNotify('เกิดข้อผิดพลาด ไม่สามารถย้ายลำดับได้', 'error'));
}

// เปิด Modal จัดการหมวดหมู่ + ตัวเลือกย่อยในหมวดนั้นทั้งหมดในหน้าจอเดียว
// toppings คืออาร์เรย์ [{id, name, price}, ...] ของตัวเลือกที่มีอยู่แล้วในหมวดนี้ (ส่งมาจาก PHP)
function openCatModal(id = '', name = '', toppings = []) {
    document.getElementById('cat_id').value = id;
    document.getElementById('cat_name').value = name;
    document.getElementById('catModalTitle').innerText = id ? 'แก้ไขหมวดนี้' : 'เพิ่มหมวดหมู่ใหม่';

    const container = document.getElementById('subOptionsContainer');
    container.innerHTML = '';
    document.getElementById('catGroupForm').querySelectorAll('input[name="deleted_topping_ids[]"]').forEach(el => el.remove());

    if (Array.isArray(toppings) && toppings.length > 0) {
        toppings.forEach(t => addSubOptionRow(t.id, t.name, t.price));
    } else if (!id) {
        addSubOptionRow(); // เพิ่มหมวดใหม่ ให้มีแถวว่างพร้อมกรอกทันที 1 แถว
    }

    new bootstrap.Modal(document.getElementById('catModal')).show();
}

function escapeHtmlAttr(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// เพิ่มแถว "ตัวเลือกย่อย" หนึ่งแถว (ชื่อ + ราคา) — id ว่าง = แถวใหม่ที่ยังไม่มีในฐานข้อมูล
function addSubOptionRow(id = '', name = '', price = '0.00') {
    const container = document.getElementById('subOptionsContainer');
    const row = document.createElement('div');
    row.className = 'd-flex gap-2 align-items-center mb-2 sub-option-row';
    row.innerHTML =
        '<input type="hidden" name="row_topping_id[]" value="' + escapeHtmlAttr(id) + '">' +
        '<input type="text" name="row_topping_name[]" class="form-control rounded-3" placeholder="ชื่อตัวเลือกย่อย เช่น เผ็ดน้อย" value="' + escapeHtmlAttr(name) + '">' +
        '<span class="fw-bold text-muted">+</span>' +
        '<input type="number" step="0.01" min="0" name="row_price[]" class="form-control rounded-3" style="max-width: 110px;" value="' + escapeHtmlAttr(price) + '">' +
        '<button type="button" class="btn btn-outline-danger rounded-3 flex-shrink-0" onclick="removeSubOptionRow(this)"><i class="bi bi-trash"></i></button>';
    container.appendChild(row);

    // แถวใหม่ไถลลงมาพร้อมจางเข้า แทนที่จะโผล่มาทันทีเฉยๆ (ใช้ anime.js)
    if (typeof anime !== 'undefined') {
        anime({
            targets: row,
            opacity: [0, 1],
            translateY: [-12, 0],
            duration: 280,
            easing: 'easeOutQuad'
        });
    }
}

// ลบแถวตัวเลือกย่อยออกจากหน้าจอ ถ้าแถวนี้มีอยู่ในฐานข้อมูลแล้วจะมาร์คไว้ให้ลบจริงตอนกดบันทึก
function removeSubOptionRow(btn) {
    const row = btn.closest('.sub-option-row');
    const hiddenId = row.querySelector('input[name="row_topping_id[]"]');
    if (hiddenId && hiddenId.value) {
        const del = document.createElement('input');
        del.type = 'hidden';
        del.name = 'deleted_topping_ids[]';
        del.value = hiddenId.value;
        document.getElementById('catGroupForm').appendChild(del);
    }

    // จางออก+ยุบความสูงลงก่อนค่อยลบออกจริงจาก DOM แทนที่จะหายวับไปทันที (ใช้ anime.js)
    if (typeof anime !== 'undefined') {
        const rowHeight = row.offsetHeight;
        const rowMarginBottom = parseFloat(getComputedStyle(row).marginBottom) || 0;
        row.style.overflow = 'hidden';
        anime({
            targets: row,
            opacity: [1, 0],
            translateX: [0, 16],
            height: [rowHeight, 0],
            marginBottom: [rowMarginBottom, 0],
            duration: 220,
            easing: 'easeInQuad',
            complete: () => row.remove()
        });
    } else {
        row.remove();
    }
}

// ฟังก์ชันเปิด Modal ตัวเลือกเสริม (ค่าเริ่มต้นตอนเพิ่มใหม่: ปิดติดตามคลังสินค้า, เปิดขาย)
function openToppingModal(id = '', name = '', price = '0.00', cat_id = '', use_stock = 0, stock_qty = 50, is_active = 1) {
    const form = document.getElementById('toppingForm');
    form.classList.remove('was-validated');
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

    document.getElementById('t_id').value = id;
    document.getElementById('t_name').value = name;
    document.getElementById('t_price').value = price;
    document.getElementById('t_cat_id').value = cat_id;
    document.getElementById('t_use_stock').checked = !!use_stock;
    document.getElementById('t_stock_qty').value = stock_qty || 50;
    document.getElementById('t_is_active').checked = id ? !!is_active : true;
    document.getElementById('toppingModalTitle').innerText = id ? 'แก้ไขตัวเลือกเสริม' : 'เพิ่มตัวเลือกเสริมใหม่';

    toggleStockQtyField();
    new bootstrap.Modal(document.getElementById('toppingModal')).show();
}

// แสดง/ซ่อนช่องจำนวนเริ่มต้นในคลังสินค้า ตามสถานะ Toggle ติดตามคลังสินค้า (ไม่ล้างค่าเดิมในช่อง เผื่อเปิดกลับมาใช้ใหม่)
function toggleStockQtyField() {
    const useStock = document.getElementById('t_use_stock').checked;
    document.getElementById('t_stock_qty_wrap').style.display = useStock ? 'block' : 'none';
}

// popup ยืนยันก่อนลบเสมอ (ส่งเป็น POST ผ่านฟอร์มที่ซ่อนไว้ พร้อม CSRF token แทนการยิง GET ตรงๆ)
// ใช้ ownerConfirm() (SweetAlert2) แทน confirm() ของเบราว์เซอร์ เพราะ confirm() ถูกบล็อกแบบเงียบๆ
// ในเบราว์เซอร์/เว็บวิวบางตัว ทำให้กดลบแล้วไม่มีอะไรเกิดขึ้นเลยโดยไม่รู้สาเหตุ
function confirmDeleteTopping(id, name) {
    ownerConfirm('ต้องการลบ "' + name + '" ใช่หรือไม่? การลบไม่สามารถกู้คืนได้').then(function (ok) {
        if (ok) {
            document.getElementById('delete_topping_id').value = id;
            document.getElementById('deleteToppingForm').submit();
        }
    });
}

// สลับสถานะเปิด/ปิดขายจากหน้ารายการโดยตรง (ไม่ต้องเปิด Modal)
function toggleToppingActive(id, isChecked) {
    const formData = new FormData();
    formData.append('action', 'toggle_active');
    formData.append('topping_id', id);
    formData.append('new_status', isChecked ? 1 : 0);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('manage_toppings.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        const row = document.getElementById('row-' + id);
        if (data.success) {
            if (row) row.classList.toggle('out-of-stock', !isChecked);
        } else {
            document.getElementById('active_' + id).checked = !isChecked;
            ownerNotify('เกิดข้อผิดพลาด ไม่สามารถเปลี่ยนสถานะได้', 'error');
        }
    })
    .catch(() => {
        document.getElementById('active_' + id).checked = !isChecked;
        ownerNotify('เกิดข้อผิดพลาด ไม่สามารถเปลี่ยนสถานะได้', 'error');
    });
}

// ตรวจสอบความถูกต้องของฟอร์มก่อนบันทึก
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('toppingForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        let valid = true;

        const nameInput = document.getElementById('t_name');
        if (!nameInput.value.trim()) {
            nameInput.classList.add('is-invalid');
            valid = false;
        } else {
            nameInput.classList.remove('is-invalid');
        }

        const catInput = document.getElementById('t_cat_id');
        if (!catInput.value) {
            catInput.classList.add('is-invalid');
            valid = false;
        } else {
            catInput.classList.remove('is-invalid');
        }

        const priceInput = document.getElementById('t_price');
        if (priceInput.value === '' || parseFloat(priceInput.value) < 0) {
            priceInput.classList.add('is-invalid');
            valid = false;
        } else {
            priceInput.classList.remove('is-invalid');
        }

        const useStock = document.getElementById('t_use_stock').checked;
        const stockInput = document.getElementById('t_stock_qty');
        if (useStock) {
            const qty = stockInput.value;
            if (qty === '' || !Number.isInteger(Number(qty)) || Number(qty) < 0) {
                stockInput.classList.add('is-invalid');
                valid = false;
            } else {
                stockInput.classList.remove('is-invalid');
            }
        } else {
            stockInput.classList.remove('is-invalid');
        }

        if (!valid) {
            e.preventDefault();
        }
    });
});
