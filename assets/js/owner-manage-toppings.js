// อัปเดตปุ่มขึ้น/ลงของบล็อกหมวดหมู่หนึ่งใบ ให้ตรงกับตำแหน่งจริงหลังสลับ (บนสุด = ปิดปุ่มขึ้น, ล่างสุด = ปิดปุ่มลง)
// ต้องข้าม sibling ที่ไม่ใช่ .cat-block ไปด้วย (เช่น div หัวข้อ+ปุ่ม "+หมวดหมู่"/"+เพิ่มตัวเลือกเสริม" ที่อยู่ก่อนบล็อกแรก
// ในคอนเทนเนอร์เดียวกัน) ไม่งั้นบล็อกแรกจะเข้าใจผิดว่ามี previousElementSibling แล้วไม่ปิดปุ่มขึ้นให้
function refreshCatMoveButtons(block) {
    if (!block) return;
    const upBtn = block.querySelector('.cat-move-up');
    const downBtn = block.querySelector('.cat-move-down');
    let prev = block.previousElementSibling;
    while (prev && !prev.classList.contains('cat-block')) prev = prev.previousElementSibling;
    let next = block.nextElementSibling;
    while (next && !next.classList.contains('cat-block')) next = next.nextElementSibling;
    if (upBtn) upBtn.disabled = !prev;
    if (downBtn) downBtn.disabled = !next;
}

// ย้ายลำดับการแสดงหมวดหมู่ขึ้น/ลง (สลับกับหมวดที่อยู่ติดกัน) - สลับตำแหน่งการ์ดสองใบใน DOM ตรงๆ ไม่ต้องรีโหลดหน้า
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
        if (!data.success) { ownerNotify(data.error || 'ย้ายลำดับไม่สำเร็จ', 'error'); return; }

        const block = document.getElementById('cat-block-' + data.cat_id);
        const swapBlock = document.getElementById('cat-block-' + data.swap_id);
        if (!block || !swapBlock) return;

        if (direction === 'up') {
            block.parentNode.insertBefore(block, swapBlock);
        } else {
            block.parentNode.insertBefore(swapBlock, block);
        }
        refreshCatMoveButtons(block);
        refreshCatMoveButtons(swapBlock);
        block.classList.add('card-update-flash');
        setTimeout(() => block.classList.remove('card-update-flash'), 800);
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
    // ตั้งค่าเริ่มต้นไว้ก่อน append กันเห็นแถวโผล่เต็มๆ วูบเดียวก่อน anime.js จะเริ่มทำงาน
    if (typeof anime !== 'undefined') {
        row.style.opacity = '0';
        row.style.transform = 'translateY(-14px) scale(0.97)';
    }
    container.appendChild(row);

    // แถวใหม่ไถลลงมาพร้อมจางเข้าแบบนุ่มนวล แทนที่จะโผล่มาทันทีเฉยๆ (ใช้ anime.js)
    if (typeof anime !== 'undefined') {
        anime({
            targets: row,
            opacity: [0, 1],
            translateY: [-14, 0],
            scale: [0.97, 1],
            duration: 450,
            easing: 'easeOutCubic'
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

    // จางออก+ไถลนิดหน่อยก่อน แล้วค่อยยุบความสูงตามทีหลัง (แยกจังหวะกันแทนทำพร้อมกันทั้งหมด ดูนุ่มนวลกว่า)
    // ยุบจริงถึงจะลบออกจาก DOM (ใช้ anime.js timeline)
    if (typeof anime !== 'undefined') {
        const rowHeight = row.offsetHeight;
        const rowMarginBottom = parseFloat(getComputedStyle(row).marginBottom) || 0;
        row.style.overflow = 'hidden';
        anime.timeline({ easing: 'easeInOutCubic' })
            .add({
                targets: row,
                opacity: [1, 0],
                translateX: [0, 18],
                scale: [1, 0.98],
                duration: 260
            })
            .add({
                targets: row,
                height: [rowHeight, 0],
                marginBottom: [rowMarginBottom, 0],
                duration: 280,
                complete: () => row.remove()
            }, '-=40');
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

// popup ยืนยันก่อนลบเสมอ ส่งเป็น AJAX แทนการรีโหลดทั้งหน้า (เดิม submit ฟอร์มที่ซ่อนไว้แบบธรรมดา)
// ใช้ ownerConfirm() (SweetAlert2) แทน confirm() ของเบราว์เซอร์ เพราะ confirm() ถูกบล็อกแบบเงียบๆ
// ในเบราว์เซอร์/เว็บวิวบางตัว ทำให้กดลบแล้วไม่มีอะไรเกิดขึ้นเลยโดยไม่รู้สาเหตุ
function confirmDeleteTopping(id, name) {
    ownerConfirm('ต้องการลบ "' + name + '" ใช่หรือไม่? การลบไม่สามารถกู้คืนได้').then(function (ok) {
        if (!ok) return;
        const fd = new FormData();
        fd.append('csrf_token', CSRF_TOKEN);
        fd.append('delete_id', id);
        fetch('manage_toppings.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) { ownerNotify(data.error || 'ลบไม่สำเร็จ', 'error'); return; }
            const row = document.getElementById('row-' + id);
            if (row) {
                row.style.transition = 'opacity 0.3s ease';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            }
            ownerNotify('ลบตัวเลือกเสริมเรียบร้อยแล้ว');
        })
        .catch(() => ownerNotify('เกิดข้อผิดพลาด ไม่สามารถลบได้', 'error'));
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

// ตอนโหลดหน้าครั้งแรก ปุ่มขึ้น/ลงของทุกบล็อกเรนเดอร์มาแบบ "เปิดใช้งานเสมอ" (ดู includes/topping_render.php)
// ต้องรีเฟรชให้ตรงตำแหน่งจริงทันทีที่โหลดหน้า (บนสุด/ล่างสุดต้องปิดปุ่มที่เกี่ยวข้อง)
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.cat-block').forEach(refreshCatMoveButtons);
});

// จัดการฟอร์ม "หมวดหมู่ + ตัวเลือกย่อยทั้งหมดในหมวด" แบบ AJAX ไม่รีโหลดทั้งหน้า (เพิ่มหมวดใหม่/แก้ไขหมวดเดิม
// พร้อมเพิ่ม/แก้ไข/ลบตัวเลือกย่อยหลายแถวพร้อมกันได้ในครั้งเดียว - ตรรกะฝั่งเซิร์ฟเวอร์เหมือนเดิมทุกอย่าง)
document.addEventListener('DOMContentLoaded', function () {
    const catForm = document.getElementById('catGroupForm');
    if (!catForm) return;

    catForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const errorBox = catForm.querySelector('.cat-group-error');
        if (errorBox) errorBox.innerHTML = '';
        const submitBtn = catForm.querySelector('button[type="submit"]');
        const originalBtnHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;

        // new FormData(form) ไม่ใส่ name/value ของปุ่ม submit ที่กดให้อัตโนมัติ (save_category_group เป็นชื่อปุ่ม ไม่ใช่ input ซ่อน)
        const fd = new FormData(catForm);
        fd.append('save_category_group', '1');

        fetch('manage_toppings.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;

            if (!data.success) {
                if (errorBox) { errorBox.innerHTML = '<div class="alert alert-danger rounded-3 py-2 small mb-3">' + (data.error || 'เกิดข้อผิดพลาด') + '</div>'; }
                return;
            }

            const modalInstance = bootstrap.Modal.getInstance(document.getElementById('catModal'));
            if (modalInstance) modalInstance.hide();

            // รอ modal ปิดก่อนค่อยแทรก/แทนที่ DOM กัน bootstrap อ้างอิง element เดิมที่กำลังปิดอยู่
            setTimeout(function () {
                const container = document.querySelector('.container.py-3.py-md-4');
                if (data.is_new) {
                    // เพิ่มหมวดใหม่ต่อท้ายสุด แล้วรีเฟรชปุ่มขึ้น/ลงของบล็อกที่เคยเป็นบล็อกสุดท้ายด้วย (ตอนนี้ไม่ใช่บล็อกสุดท้ายแล้ว)
                    const previousLastBlock = document.querySelector('.cat-block:last-of-type');
                    container.insertAdjacentHTML('beforeend', data.block_html);
                    if (previousLastBlock) refreshCatMoveButtons(previousLastBlock);
                } else {
                    const oldBlock = document.getElementById('cat-block-' + data.cat_id);
                    if (oldBlock) oldBlock.outerHTML = data.block_html;
                }
                const newBlock = document.getElementById('cat-block-' + data.cat_id);
                if (newBlock) {
                    refreshCatMoveButtons(newBlock);
                    newBlock.classList.add('card-update-flash');
                    setTimeout(() => newBlock.classList.remove('card-update-flash'), 800);
                }

                // ซิงก์ dropdown "กลุ่มตัวเลือกเสริม" ในโมดัลเพิ่ม/แก้ไขตัวเลือกเสริมเดี่ยวด้วย ไม่งั้นเพิ่ม/แก้ไขหมวดหมู่ใหม่
                // ไปแล้วเปิดโมดัลนั้นต่อจะไม่เห็นหมวดที่เพิ่งเพิ่ม/เปลี่ยนชื่อไป (หน้าไม่ได้รีโหลดให้ dropdown อ่านค่าใหม่เอง)
                const catSelect = document.getElementById('t_cat_id');
                if (catSelect) {
                    const existingOption = catSelect.querySelector('option[value="' + data.cat_id + '"]');
                    if (existingOption) {
                        existingOption.textContent = data.cat_name;
                    } else {
                        const opt = document.createElement('option');
                        opt.value = data.cat_id;
                        opt.textContent = data.cat_name;
                        catSelect.appendChild(opt);
                    }
                }
            }, 300);

            ownerNotify(data.warning || 'บันทึกหมวดหมู่เรียบร้อยแล้ว', data.warning ? 'error' : 'success');
        })
        .catch(function () {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
            if (errorBox) { errorBox.innerHTML = '<div class="alert alert-danger rounded-3 py-2 small mb-3">เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ กรุณาลองใหม่อีกครั้ง</div>'; }
        });
    });
});

// ตรวจสอบความถูกต้องของฟอร์มก่อนบันทึก แล้วส่งแบบ AJAX ไม่รีโหลดทั้งหน้า
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('toppingForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
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

        if (!valid) return;

        const errorBox = form.querySelector('.topping-form-error');
        if (errorBox) errorBox.innerHTML = '';
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;

        // new FormData(form) ไม่ใส่ name/value ของปุ่ม submit ที่กดให้อัตโนมัติ (save_topping เป็นชื่อปุ่ม ไม่ใช่ input ซ่อน)
        const fd = new FormData(form);
        fd.append('save_topping', '1');

        fetch('manage_toppings.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;

            if (!data.success) {
                if (errorBox) { errorBox.innerHTML = '<div class="alert alert-danger rounded-3 py-2 small mb-3">' + (data.error || 'เกิดข้อผิดพลาด') + '</div>'; }
                return;
            }

            const modalInstance = bootstrap.Modal.getInstance(document.getElementById('toppingModal'));
            if (modalInstance) modalInstance.hide();

            setTimeout(function () {
                const existingRow = document.getElementById('row-' + data.topping_id);
                const movedCategory = !data.is_new && data.old_cat_id !== null && Number(data.old_cat_id) !== Number(data.cat_id);

                if (data.is_new || movedCategory) {
                    if (existingRow) existingRow.remove();
                    const tbody = document.getElementById('topping-tbody-' + data.cat_id);
                    if (tbody) tbody.insertAdjacentHTML('beforeend', data.row_html);
                } else if (existingRow) {
                    existingRow.outerHTML = data.row_html;
                }
                const newRow = document.getElementById('row-' + data.topping_id);
                if (newRow) {
                    newRow.classList.add('card-update-flash');
                    setTimeout(() => newRow.classList.remove('card-update-flash'), 800);
                }
            }, 300);

            ownerNotify(data.is_new ? 'เพิ่มตัวเลือกเสริมเรียบร้อยแล้ว' : 'แก้ไขตัวเลือกเสริมเรียบร้อยแล้ว');
        })
        .catch(function () {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
            if (errorBox) { errorBox.innerHTML = '<div class="alert alert-danger rounded-3 py-2 small mb-3">เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ กรุณาลองใหม่อีกครั้ง</div>'; }
        });
    });
});
