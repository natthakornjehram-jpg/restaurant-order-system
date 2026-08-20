function ajaxCheckout(tableId) {
    if (!confirm('ยืนยันการเช็คบิลและเคลียร์โต๊ะนี้ให้ว่าง?')) return;
    const formData = new FormData();
    formData.append('table_id', tableId);
    fetch('update_table_status_ajax.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) { location.reload(); } else { alert('ผิดพลาด: ' + data.error); }
    });
}

function showQR(tableNum) {
    // ใช้โดเมนที่กำลังเปิดอยู่จริง + BASE_URL (คำนวณจาก db.php) แทนการ hardcode โดเมน
    // ทำให้ QR code ชี้ไปที่โดเมนถูกต้องเสมอ ไม่ว่าจะรันบน localhost หรือโฮสต์จริง
    const baseUrl = window.location.origin + BASE_URL + "qr_table/menu_dinein.php?table=";
    const qrApi = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(baseUrl + tableNum)}`;

    document.getElementById('qrTableNum').innerText = tableNum;
    document.getElementById('qrImg').src = qrApi;

    const downloadBtn = document.getElementById('downloadQrBtn');
    downloadBtn.href = qrApi;
    downloadBtn.download = `qrcode_table_${tableNum}.png`;

    const myModal = new bootstrap.Modal(document.getElementById('qrModal'));
    myModal.show();
}
