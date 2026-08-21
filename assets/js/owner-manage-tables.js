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
    const tableUrl = window.location.origin + BASE_URL + "qr_table/menu_dinein.php?table=" + encodeURIComponent(tableNum);
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
