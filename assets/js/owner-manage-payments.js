// ฟังก์ชันสำหรับอนุมัติออเดอร์ออนไลน์ (โอนเงินผ่านสลิปชัวร์ๆ)
function approvePayment(orderId, paymentMethod) {
    if (confirm('ยืนยันยอดเงินและอนุมัติออเดอร์นี้?')) {
        sendPaymentData(orderId, paymentMethod);
    }
}

// ฟังก์ชันสำหรับลูกค้าหน้าร้าน (ดึงค่าจาก Dropdown ว่าจ่ายสดหรือโอน)
function approveDineInPayment(orderId) {
    const method = document.getElementById('payMethod_' + orderId).value;
    if (confirm('ยืนยันการรับเงินหน้าร้านแบบ ' + (method === 'cash' ? 'เงินสด' : 'โอนเงิน') + ' ใช่หรือไม่?')) {
        sendPaymentData(orderId, method);
    }
}

// ส่งข้อมูลไปให้ API (ใช้ร่วมกันได้เลย)
function sendPaymentData(orderId, method) {
    const fd = new FormData();
    fd.append('order_id', orderId);
    fd.append('method', method); // ส่งไปบอก API ด้วยว่าจ่ายแบบไหน (เอาไปลงตาราง payment)

    fetch('api_approve_payment.php', {
        method: 'POST',
        body: fd
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('บันทึกการชำระเงินเรียบร้อย!');
            location.reload();
        } else {
            alert('ผิดพลาด: ' + (data.error || data.message));
        }
    })
    .catch(err => {
        console.error(err);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    });
}
