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
        if (data.success) {
            if (announcement && typeof speakThai === 'function') {
                speakThai(announcement);
            }
            sessionStorage.setItem('ownerFlashMsg', announcement || 'บันทึกการชำระเงินเรียบร้อย!');
            location.reload();
        } else {
            ownerNotify('ผิดพลาด: ' + (data.error || data.message), 'error');
        }
    })
    .catch(err => {
        console.error(err);
        ownerNotify('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
    });
}
