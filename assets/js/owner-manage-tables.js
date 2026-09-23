function ajaxCheckout(tableId) {
    const formData = new FormData();
    formData.append('table_id', tableId);
    formData.append('csrf_token', CSRF_TOKEN);
    fetch('update_table_status_ajax.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (!data.success) { ownerNotify('ผิดพลาด: ' + data.error, 'error'); return; }

        // โต๊ะกลายเป็นว่างแล้ว อัปเดตการ์ดในหน้าให้ตรงสถานะใหม่ทันที ไม่ต้องรีโหลดทั้งหน้า
        const card = document.getElementById('card-table-' + tableId);
        if (card) {
            const statusCard = card.querySelector('.status-card');
            statusCard.classList.remove('bg-danger', 'text-white');
            statusCard.classList.add('bg-white', 'text-dark');

            const badge = card.querySelector('.status-badge');
            badge.innerHTML = '<span class="badge rounded-pill bg-success px-3">ว่าง</span>';

            const btnArea = card.querySelector('.btn-area');
            const deleteForm = btnArea.querySelector('form');

            // สร้างปุ่มผ่าน DOM API + addEventListener แทนการต่อสตริง HTML ใส่ onclick ตรงๆ
            // (ต่อสตริงแล้วใส่ค่าที่มาจาก JSON.stringify ลงใน onclick="..." เคยพังมาแล้ว เพราะเครื่องหมาย " ในค่าชนกับเครื่องหมาย " ของแอตทริบิวต์เอง)
            const editBtn = document.createElement('button');
            editBtn.className = 'btn btn-sm btn-outline-primary rounded-pill shadow-sm';
            editBtn.setAttribute('data-bs-toggle', 'modal');
            editBtn.setAttribute('data-bs-target', '#editTable' + tableId);
            editBtn.innerHTML = '<i class="bi bi-pencil-square"></i> แก้ไขชื่อ';

            const qrBtn = document.createElement('button');
            qrBtn.type = 'button';
            qrBtn.className = 'btn btn-sm btn-outline-dark rounded-pill shadow-sm';
            qrBtn.innerHTML = '<i class="bi bi-qr-code"></i> พิมพ์ QR';
            qrBtn.addEventListener('click', () => showQR(data.table_number, data.qr_token || ''));

            btnArea.innerHTML = '';
            btnArea.appendChild(editBtn);
            btnArea.appendChild(qrBtn);
            if (deleteForm) {
                btnArea.appendChild(deleteForm);
                const delBtn = deleteForm.querySelector('button');
                if (delBtn) { delBtn.classList.remove('text-white'); delBtn.classList.add('text-danger'); }
            }

            card.classList.add('card-update-flash');
            setTimeout(() => card.classList.remove('card-update-flash'), 800);
        }
        ownerNotify('เช็คบิลและเคลียร์โต๊ะเรียบร้อยแล้ว');
    })
    .catch(err => {
        console.error(err);
        ownerNotify('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
    });
}

// เพิ่ม/แก้ไข/ลบโต๊ะ แบบ AJAX ไม่รีโหลดทั้งหน้า (event delegation เพราะการ์ด/modal จะถูกแทนที่ใหม่ทุกครั้งที่แก้ไข)
document.addEventListener('submit', function (e) {
    const form = e.target.closest('.table-mini-form');
    if (!form) return;
    const action = form.dataset.tableAction;

    if (action === 'delete') {
        e.preventDefault();
        ownerConfirm('ยืนยันลบโต๊ะนี้?').then(function (ok) {
            if (!ok) return;
            const formUrl = form.getAttribute('action') || 'manage_tables.php';
            fetch(formUrl, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form)
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) { ownerNotify(data.error || 'ลบไม่สำเร็จ', 'error'); return; }
                const col = form.closest('.col-6, .col-md-4, .col-lg-2');
                if (col) {
                    col.classList.add('card-col-removing');
                    setTimeout(function () {
                        col.remove();
                        const grid = document.getElementById('table-grid');
                        if (grid && !grid.querySelector('[id^="card-table-"]')) {
                            grid.insertAdjacentHTML('beforeend', '<div class="col-12 text-center py-5 text-muted" id="tableEmptyState">ยังไม่มีข้อมูลโต๊ะในระบบ</div>');
                        }
                    }, 300);
                }
                const modal = form.closest('.modal');
                if (modal) modal.remove(); // เผื่อกดลบจากใน modal แก้ไขชื่อในอนาคต (ตอนนี้ปุ่มลบอยู่แค่ในการ์ด)
                ownerNotify('ลบโต๊ะเรียบร้อยแล้ว');
            })
            .catch(function () { ownerNotify('เกิดข้อผิดพลาด ไม่สามารถลบได้', 'error'); });
        });
        return;
    }

    // เพิ่ม/แก้ไข - ทั้งสองแบบเซิร์ฟเวอร์ตอบการ์ด+modal ที่ render สดกลับมาเหมือนกัน ต่างกันแค่จะ "แทรกใหม่" หรือ "แทนที่ของเดิม"
    e.preventDefault();
    const formUrl = form.getAttribute('action') || 'manage_tables.php';
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnHtml = submitBtn.innerHTML;
    const errorBox = form.querySelector('.add-table-error, .edit-table-error');
    if (errorBox) errorBox.innerHTML = '';
    submitBtn.disabled = true;

    // new FormData(form) ไม่ใส่ name/value ของปุ่ม submit ที่กดให้อัตโนมัติ (add_table/edit_table เป็นชื่อปุ่ม ไม่ใช่ input ซ่อน)
    // ฝั่ง PHP เช็คว่าเป็นแอคชันไหนจาก isset($_POST['add_table']/'edit_table') ต้องใส่เองตรงนี้ ไม่งั้นเซิร์ฟเวอร์จะไม่รู้ว่าต้องรันแอคชันไหนเลย
    const fd = new FormData(form);
    if (submitBtn.name) { fd.append(submitBtn.name, submitBtn.value || '1'); }

    fetch(formUrl, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        submitBtn.disabled = false;

        if (!data.success) {
            if (errorBox) { errorBox.innerHTML = '<div class="alert alert-danger rounded-3 py-2 small mb-3">' + (data.error || 'เกิดข้อผิดพลาด') + '</div>'; }
            else { ownerNotify(data.error || 'เกิดข้อผิดพลาด', 'error'); }
            return;
        }

        if (action === 'add') {
            const emptyState = document.getElementById('tableEmptyState');
            if (emptyState) emptyState.remove();
            const grid = document.getElementById('table-grid');
            grid.insertAdjacentHTML('beforeend', data.card_html + data.modal_html);
            const modalEl = bootstrap.Modal.getInstance(document.getElementById('addTableModal'));
            if (modalEl) modalEl.hide();
            form.reset();
            ownerNotify('เพิ่มโต๊ะเรียบร้อยแล้ว');
        } else if (action === 'edit') {
            const tableId = data.table_id;
            const modalInstance = bootstrap.Modal.getInstance(document.getElementById('editTable' + tableId));
            if (modalInstance) modalInstance.hide();

            // รอ modal ปิดก่อนค่อยแทนที่ DOM ของมันเอง กัน bootstrap อ้างอิง element เดิมที่กำลังจะถูกลบไปตอนเล่นแอนิเมชันปิดอยู่
            setTimeout(function () {
                const oldCard = document.getElementById('card-table-' + tableId);
                const oldModal = document.getElementById('editTable' + tableId);
                if (oldCard) oldCard.outerHTML = data.card_html;
                if (oldModal) oldModal.outerHTML = data.modal_html;
                const newCard = document.getElementById('card-table-' + tableId);
                if (newCard) {
                    const cardEl = newCard.querySelector('.status-card');
                    if (cardEl) { cardEl.classList.add('card-update-flash'); setTimeout(() => cardEl.classList.remove('card-update-flash'), 800); }
                }
            }, 300);
            ownerNotify('แก้ไขชื่อโต๊ะเรียบร้อยแล้ว');
        }
    })
    .catch(function () {
        submitBtn.disabled = false;
        if (errorBox) { errorBox.innerHTML = '<div class="alert alert-danger rounded-3 py-2 small mb-3">เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ กรุณาลองใหม่อีกครั้ง</div>'; }
        else { ownerNotify('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', 'error'); }
    });
});

function showQR(tableNum, qrToken) {
    // ใช้โดเมนที่กำลังเปิดอยู่จริง + BASE_URL (คำนวณจาก db.php) แทนการ hardcode โดเมน
    // ทำให้ QR code ชี้ไปที่โดเมนถูกต้องเสมอ ไม่ว่าจะรันบน localhost หรือโฮสต์จริง
    // แนบ token ลับต่อโต๊ะ (&t=) ไปด้วยเสมอ - menu_dinein.php ใช้เช็คว่าสแกนจาก QR จริง ไม่ใช่แค่เดาเลขโต๊ะ
    let tableUrl = window.location.origin + BASE_URL + "qr_table/menu_dinein.php?table=" + encodeURIComponent(tableNum);
    if (qrToken) {
        tableUrl += "&t=" + encodeURIComponent(qrToken);
    }
    const qrApi = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(tableUrl)}`;

    document.getElementById('qrTableNum').innerText = tableNum;
    document.getElementById('qrImg').src = qrApi;

    const downloadBtn = document.getElementById('downloadQrBtn');
    downloadBtn.href = qrApi;
    downloadBtn.download = `qrcode_table_${tableNum}.png`;

    // ลิงก์เปิดเมนูโต๊ะนี้ตรงๆ (ใช้ทดสอบจากมือถือ/คอมได้โดยไม่ต้องสแกน)
    const linkBtn = document.getElementById('openTableLinkBtn');
    linkBtn.href = tableUrl;

    const copyBtn = document.getElementById('copyTableLinkBtn');
    copyBtn.onclick = function() {
        navigator.clipboard.writeText(tableUrl).then(function() {
            copyBtn.innerHTML = '<i class="bi bi-check-lg"></i> คัดลอกแล้ว';
            setTimeout(function() { copyBtn.innerHTML = '<i class="bi bi-link-45deg"></i> คัดลอกลิงก์'; }, 1500);
        });
    };

    const myModal = new bootstrap.Modal(document.getElementById('qrModal'));
    myModal.show();
}
