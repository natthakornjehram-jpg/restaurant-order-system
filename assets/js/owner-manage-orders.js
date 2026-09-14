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

// หมายเหตุ: การตรวจและรีโหลดหน้าออเดอร์ใหม่ถูกจัดการแบบสมาร์ตใน footer_owner.php (checkNewOrders) เรียบร้อยแล้ว
