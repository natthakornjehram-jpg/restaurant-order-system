// ฟังก์ชันสำหรับปิดออเดอร์กลับบ้าน (จ่ายเงินสดตอนมารับ)
function approvePayment(orderId, paymentMethod) {
    sendPaymentData(orderId, paymentMethod);
}

// ฟังก์ชันสำหรับลูกค้าหน้าร้าน (กดปุ่มปิดบิล)
function approveDineInPayment(orderId) {
    const methodEl = document.getElementById('payMethod_' + orderId);
    const method = methodEl ? methodEl.value : 'cash';
    sendPaymentData(orderId, method, 'ปิดบิลโต๊ะแล้ว');
}

// ส่งข้อมูลไปให้ API (ใช้ร่วมกันได้เลย)
// announcement: ข้อความให้พูดแจ้งเตือนตอนสำเร็จ (ถ้าไม่ส่งมาก็แค่ alert เหมือนเดิม)
function sendPaymentData(orderId, method, announcement) {
    const fd = new FormData();
    fd.append('order_id', orderId);
    fd.append('method', method); // ส่งไปบอก API ด้วยว่าจ่ายแบบไหน (เอาไปลงตาราง payment)
    fd.append('csrf_token', CSRF_TOKEN);

    fetch('api_approve_payment.php', {
        method: 'POST',
        body: fd
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            ownerNotify('ผิดพลาด: ' + (data.error || data.message), 'error');
            return;
        }
        if (announcement && typeof speakThai === 'function') {
            speakThai(announcement);
        }
        ownerNotify(announcement || 'บันทึกการชำระเงินเรียบร้อย!');

        // ปิดแล้ว = ออกจากทั้งสองแท็บ (ไม่ใช่ unpaid อีกต่อไป) เอาการ์ดออกแบบนุ่มๆ ไม่ต้องรีโหลดทั้งหน้า
        const col = document.getElementById('payment-col-' + orderId);
        if (col) {
            col.classList.add('card-col-removing');
            setTimeout(() => col.remove(), 300);
        }
        const modal = document.getElementById('payModal' + orderId);
        if (modal) {
            const inst = bootstrap.Modal.getInstance(modal);
            if (inst) inst.hide();
            modal.remove();
        }

        // แท็บไหนมีการ์ดนี้อยู่ ก็ลดตัวเลขนับของแท็บนั้น (เช็คจาก id ของแท็บที่ยังหาการ์ดเจอตอนกดปุ่ม)
        const badgeId = col && col.closest('#pills-served') ? 'servedCountBadge' : 'onlineCountBadge';
        const badge = document.getElementById(badgeId);
        if (badge) {
            const next = Math.max(0, (parseInt(badge.textContent, 10) || 0) - 1);
            if (next === 0) badge.style.display = 'none';
            badge.textContent = next;
        }
    })
    .catch(err => {
        console.error(err);
        ownerNotify('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
    });
}
