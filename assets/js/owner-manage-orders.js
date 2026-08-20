function changeStatus(orderId, nextStatus) {
    const fd = new FormData();
    fd.append('order_id', orderId);
    fd.append('new_status', nextStatus);

    fetch('api_update_order_status.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) location.reload();
        else alert('Error: ' + d.error);
    })
    .catch(err => console.error('Error:', err));
}

// ตรวจสอบออเดอร์ใหม่ทุก 10 วินาที
setInterval(function () {
    if (document.querySelectorAll('.modal.show').length === 0) {
        location.reload();
    }
}, 10000);
