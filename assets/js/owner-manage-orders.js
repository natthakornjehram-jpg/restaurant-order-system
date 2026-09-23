// เอาการ์ดออเดอร์ออกจากคิวแบบนุ่มๆ (เฟด+ย่อ) แล้วค่อยลบออกจาก DOM จริง พร้อมลดตัวเลข "กำลังรอ N ใบสั่ง" และล้าง modal แก้ไขที่ผูกกับออเดอร์นี้ทิ้งไปด้วย
function removeOrderCard(orderId) {
    const col = document.getElementById('order-col-' + orderId);
    if (col) {
        col.classList.add('card-col-removing');
        setTimeout(() => col.remove(), 300);
    }
    const modal = document.getElementById('editOrderModal' + orderId);
    if (modal) modal.remove();

    const badge = document.getElementById('queueCountBadge');
    if (badge) {
        const next = Math.max(0, (parseInt(badge.textContent, 10) || 0) - 1);
        badge.textContent = next;
    }
}

function changeStatus(orderId, nextStatus) {
    const fd = new FormData();
    fd.append('order_id', orderId);
    fd.append('new_status', nextStatus);
    fd.append('csrf_token', CSRF_TOKEN);

    fetch('api_update_order_status.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (!d.success) { ownerNotify('Error: ' + d.error, 'error'); return; }

        if (nextStatus === 'served') {
            // ปรุงเสร็จแล้ว = ออกจากคิว (หน้านี้แสดงเฉพาะ pending/cooking เท่านั้น)
            removeOrderCard(orderId);
            return;
        }

        // pending -> cooking: อัปเดตการ์ดเดิมในหน้าให้ตรงสถานะใหม่ ไม่ต้องรีโหลดทั้งหน้า
        const card = document.getElementById('order-card-' + orderId);
        if (card) {
            card.classList.remove('border', 'border-3', 'border-danger', 'shadow-lg', 'bg-soft-pending');
            card.classList.add('bg-soft-cooking');
            card.classList.add('card-update-flash');
            setTimeout(() => card.classList.remove('card-update-flash'), 800);
        }
        const banner = document.getElementById('order-openbanner-' + orderId);
        if (banner) banner.remove();

        const actionBtnWrap = document.getElementById('order-actionbtn-' + orderId);
        if (actionBtnWrap) {
            actionBtnWrap.innerHTML =
                '<button onclick="changeStatus(\'' + orderId + '\', \'served\')" class="btn w-100 btn-done btn-action shadow-sm">' +
                '<i class="bi bi-check2-all me-1"></i> ปรุงเสร็จแล้ว</button>';
        }
        // แก้ไข/ยกเลิกได้เฉพาะตอนยัง pending เท่านั้น พอเริ่มปรุงแล้วปุ่มนี้ใช้ไม่ได้แล้ว
        const editCancelRow = document.getElementById('order-editcancel-' + orderId);
        if (editCancelRow) editCancelRow.remove();
        const modal = document.getElementById('editOrderModal' + orderId);
        if (modal) modal.remove();
    })
    .catch(err => console.error('Error:', err));
}

// หมายเหตุ: การตรวจออเดอร์ใหม่เข้ามาถูกจัดการแบบสมาร์ตใน footer_owner.php (checkNewOrders) เรียบร้อยแล้ว

/**
 * ยกเลิกออเดอร์ (เฉพาะสถานะ pending เท่านั้น ฝั่งเซิร์ฟเวอร์เช็คซ้ำอีกชั้น) - ถามเหตุผลก่อนทุกครั้ง
 * (ไม่บังคับกรอก) แล้วส่งไปให้ api_cancel_order.php คืนวัตถุดิบในคลังให้อัตโนมัติ
 */
function cancelOrder(orderId) {
    Swal.fire({
        title: 'ยกเลิกออเดอร์นี้?',
        input: 'text',
        inputLabel: 'เหตุผล (ไม่บังคับ)',
        inputPlaceholder: 'เช่น ลูกค้าเปลี่ยนใจ',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันยกเลิก',
        cancelButtonText: 'กลับไปก่อน',
        confirmButtonColor: '#dc3545',
        reverseButtons: true
    }).then((result) => {
        if (!result.isConfirmed) return;
        const fd = new FormData();
        fd.append('order_id', orderId);
        fd.append('reason', result.value || '');
        fd.append('csrf_token', CSRF_TOKEN);

        fetch('api_cancel_order.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                removeOrderCard(orderId);
                ownerNotify('ยกเลิกออเดอร์เรียบร้อยแล้ว');
            } else {
                ownerNotify(d.error || 'เกิดข้อผิดพลาด', 'error');
            }
        })
        .catch(err => console.error('Error:', err));
    });
}

// ปรับจำนวนในหน้าต่างแก้ไขรายการ (จำกัดขั้นต่ำที่ 1 - ถ้าอยากลบรายการทั้งหมดให้กดปุ่มถังขยะแทน)
function editLineQty(btn, delta) {
    const line = btn.closest('.edit-order-line');
    if (line.dataset.removed === '1') return;
    const span = line.querySelector('.edit-line-qty');
    const qty = Math.max(1, parseInt(span.textContent, 10) + delta);
    span.textContent = qty;
}

// ทำเครื่องหมายลบ/ยกเลิกการลบรายการนี้ในหน้าต่างแก้ไข (ยังไม่ส่งไปเซิร์ฟเวอร์จนกว่าจะกด "บันทึกการแก้ไข")
function removeEditLine(btn) {
    const line = btn.closest('.edit-order-line');
    const isRemoved = line.dataset.removed === '1';
    line.dataset.removed = isRemoved ? '0' : '1';
    line.classList.toggle('opacity-50', !isRemoved);
    line.classList.toggle('text-decoration-line-through', !isRemoved);
    btn.innerHTML = isRemoved ? '<i class="bi bi-trash"></i>' : '<i class="bi bi-arrow-counterclockwise"></i>';
}

// รวบรวมรายการทั้งหมดในหน้าต่างแก้ไข (รายการที่ทำเครื่องหมายลบไว้จะส่งจำนวน = 0 ให้เซิร์ฟเวอร์คืนสต็อกและลบทิ้ง)
function saveOrderEdit(orderId) {
    const container = document.querySelector(`.edit-order-lines[data-order-id="${orderId}"]`);
    const lineEls = [...container.querySelectorAll('.edit-order-line')];
    const lines = lineEls.map(line => ({
        detail_id: line.dataset.detailId,
        quantity: line.dataset.removed === '1' ? 0 : parseInt(line.querySelector('.edit-line-qty').textContent, 10)
    }));

    const doSave = () => {
        const fd = new FormData();
        fd.append('order_id', orderId);
        fd.append('lines', JSON.stringify(lines));
        fd.append('csrf_token', CSRF_TOKEN);

        fetch('api_edit_order.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { ownerNotify(d.error || 'เกิดข้อผิดพลาด', 'error'); return; }

            const modalEl = document.getElementById('editOrderModal' + orderId);
            const modalInstance = modalEl && bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();

            if (allRemoved) {
                // ลบรายการจนหมด = ระบบยกเลิกออเดอร์ทั้งใบให้อัตโนมัติ (ดู api_edit_order.php) ออกจากคิวไปเลย
                removeOrderCard(orderId);
                ownerNotify('ลบรายการจนหมด ระบบยกเลิกออเดอร์นี้ให้อัตโนมัติ');
                return;
            }

            // อัปเดตสรุปรายการในการ์ด (ไม่ใช่หน้าต่างแก้ไข) ให้ตรงกับที่เพิ่งบันทึกไป โดยใช้ค่าที่มีอยู่ในเครื่องอยู่แล้ว ไม่ต้องรอข้อมูลจากเซิร์ฟเวอร์
            lines.forEach(line => {
                const editLine = container.querySelector('.edit-order-line[data-detail-id="' + line.detail_id + '"]');
                const row = document.getElementById('order-item-row-' + line.detail_id);
                if (line.quantity === 0) {
                    if (editLine) editLine.remove();
                    if (row) row.remove();
                    return;
                }
                if (row) {
                    const qtySpan = document.getElementById('order-item-qty-' + line.detail_id);
                    if (qtySpan) qtySpan.textContent = line.quantity;
                    row.classList.add('card-update-flash');
                    setTimeout(() => row.classList.remove('card-update-flash'), 800);
                }
            });
            ownerNotify('บันทึกการแก้ไขออเดอร์เรียบร้อยแล้ว');
        })
        .catch(err => console.error('Error:', err));
    };

    const allRemoved = lines.every(l => l.quantity === 0);
    if (allRemoved) {
        ownerConfirm('รายการอาหารถูกลบออกจนหมด ระบบจะยกเลิกออเดอร์นี้ทั้งใบ ยืนยันหรือไม่?').then((ok) => {
            if (ok) doSave();
        });
    } else {
        doSave();
    }
}
