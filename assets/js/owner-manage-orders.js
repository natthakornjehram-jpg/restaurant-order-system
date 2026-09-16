function changeStatus(orderId, nextStatus) {
    const fd = new FormData();
    fd.append('order_id', orderId);
    fd.append('new_status', nextStatus);
    fd.append('csrf_token', CSRF_TOKEN);

    fetch('api_update_order_status.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) location.reload();
        else ownerNotify('Error: ' + d.error, 'error');
    })
    .catch(err => console.error('Error:', err));
}

// แก้เลขคิวของออเดอร์เอง เผื่อระบบนับอัตโนมัติผิดเพี้ยน หรืออยากสลับลำดับคิวในครัวเอง
function editQueueNo(orderId, currentNo) {
    Swal.fire({
        title: 'แก้ไขเลขคิว',
        input: 'number',
        inputValue: currentNo,
        inputAttributes: { min: 1, max: 999, step: 1 },
        showCancelButton: true,
        confirmButtonText: 'บันทึก',
        cancelButtonText: 'ยกเลิก',
        inputValidator: (value) => {
            const n = parseInt(value, 10);
            if (!value || isNaN(n) || n < 1 || n > 999) {
                return 'กรุณากรอกเลขคิวเป็นตัวเลข 1-999';
            }
        }
    }).then((result) => {
        if (!result.isConfirmed) return;

        const fd = new FormData();
        fd.append('order_id', orderId);
        fd.append('queue_no', result.value);
        fd.append('csrf_token', CSRF_TOKEN);

        fetch('api_update_order_queue.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
            else ownerNotify(d.error || 'แก้ไขเลขคิวไม่สำเร็จ', 'error');
        })
        .catch(() => ownerNotify('เกิดข้อผิดพลาด ไม่สามารถแก้ไขเลขคิวได้', 'error'));
    });
}

// หมายเหตุ: การตรวจและรีโหลดหน้าออเดอร์ใหม่ถูกจัดการแบบสมาร์ตใน footer_owner.php (checkNewOrders) เรียบร้อยแล้ว
