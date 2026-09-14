function selectAll(groupClassName) {
    const checkboxes = document.querySelectorAll('.' + groupClassName);
    let allChecked = true;
    checkboxes.forEach(cb => { if (!cb.checked) allChecked = false; });
    checkboxes.forEach(cb => { cb.checked = !allChecked; });
}

// เปิด/ปิดกริดเช็คบ็อกซ์ของกลุ่มตัวเลือกเสริม (การ์ดกลุ่มพับเก็บไว้เป็นค่าเริ่มต้น กดปุ่ม "แก้ไขกลุ่มนี้" ค่อยกางออก)
function toggleGroupEditor(editorId) {
    const el = document.getElementById(editorId);
    if (!el) return;
    el.style.display = (el.style.display === 'none' || !el.style.display) ? 'block' : 'none';
}

// หยิบกลุ่มตัวเลือกที่เคยสร้างไว้ (แต่ยังไม่ได้ผูกกับเมนูนี้) มาแสดงเป็นการ์ด แล้วกางตัวเลือกให้ติ๊กได้ทันที
function attachToppingGroup(groupCardId) {
    const card = document.getElementById(groupCardId);
    if (!card) return;
    card.classList.remove('d-none');
    const editor = card.querySelector('.topping-group-editor');
    if (editor) editor.style.display = 'block';
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// แสดงแอนิเมชัน Loading หมุนๆ ตอนกดบันทึกข้อมูล แล้วต่อด้วยติ๊กถูกก่อนค่อยพาไปหน้าใหม่
// ยิงฟอร์มเองผ่าน fetch() แทนการปล่อยให้เบราว์เซอร์ submit ตามปกติ เพื่อคุมจังหวะการแสดงผลเอง
// ทั้งสองสถานะ (เดิมเซิร์ฟเวอร์ตอบกลับเร็วมากจนสปินเนอร์วูบหายไปเลย ไม่เคยเห็นติ๊กถูก)
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        Swal.fire({
            title: 'กำลังบันทึกข้อมูล...',
            html: 'กรุณารอสักครู่',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading()
            }
        });

        const startedAt = Date.now();
        const minVisibleMs = 1100;

        // new FormData(form) ไม่แนบ name/value ของปุ่ม submit ที่ถูกกดให้อัตโนมัติ (ต่างจากการ
        // submit ปกติของเบราว์เซอร์) ต้องแนบเองไม่งั้น api_save_menu.php จะเช็ค isset($_POST['save_menu'])
        // ไม่เจอแล้ว redirect กลับโดยไม่บันทึกอะไรเลยสักอย่าง
        const formData = new FormData(form);
        const clickedButton = e.submitter;
        if (clickedButton && clickedButton.name) {
            formData.append(clickedButton.name, clickedButton.value || '1');
        }

        fetch(form.action || window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => {
            const remaining = Math.max(0, minVisibleMs - (Date.now() - startedAt));
            setTimeout(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'บันทึกข้อมูลเรียบร้อยแล้ว',
                    showConfirmButton: false,
                    timer: 1300,
                    timerProgressBar: true
                }).then(() => {
                    window.location.href = res.url || window.location.href;
                });
            }, remaining);
        })
        .catch(() => {
            Swal.fire('ผิดพลาด!', 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง', 'error');
        });
    });
});
