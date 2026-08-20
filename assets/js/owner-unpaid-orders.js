// Auto-Refresh หน้าจอทุก 15 วินาที
setInterval(function () {
    if (document.querySelectorAll('.modal.show').length === 0) {
        location.reload();
    }
}, 15000);

function confirmPayment(orderId, method) {
    let methodName = (method === 'cash') ? 'เงินสด' : 'เงินโอน';
    if (confirm('ยืนยันรับชำระเงินด้วย ' + methodName + ' ใช่หรือไม่?')) {
        const fd = new FormData();
        fd.append('order_id', orderId);
        fd.append('payment_method', method);

        fetch('payments.php', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('บันทึกการชำระเงินสำเร็จ!');
                location.reload();
            } else {
                alert('เกิดข้อผิดพลาด: ' + data.error);
            }
        })
        .catch(err => alert('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'));
    }
}
