// ฟังก์ชันเปิด Modal หมวดหมู่
function openCatModal(id = '', name = '') {
    document.getElementById('cat_id').value = id;
    document.getElementById('cat_name').value = name;
    document.getElementById('catModalTitle').innerText = id ? 'แก้ไขชื่อหมวดหมู่' : 'เพิ่มหมวดหมู่ใหม่';
    new bootstrap.Modal(document.getElementById('catModal')).show();
}

// ฟังก์ชันเปิด Modal ท็อปปิ้ง
function openToppingModal(id = '', name = '', price = '0.00', cat_id = '') {
    document.getElementById('t_id').value = id;
    document.getElementById('t_name').value = name;
    document.getElementById('t_price').value = price;
    document.getElementById('t_cat_id').value = cat_id;
    document.getElementById('toppingModalTitle').innerText = id ? 'แก้ไขข้อมูลท็อปปิ้ง' : 'เพิ่มท็อปปิ้งใหม่';
    new bootstrap.Modal(document.getElementById('toppingModal')).show();
}

// ฟังก์ชันเปิด/ปิด ของหมด (ฉบับแก้ไขให้คุยกับ API รู้เรื่อง)
function toggleToppingStatus(id, currentStatus) {
    const newStatus = (currentStatus == 1) ? 0 : 1;
    const fd = new FormData();

    fd.append('id', id);
    fd.append('type', 'topping');
    fd.append('new_status', newStatus);

    fetch('api_update_status.php', {
        method: 'POST',
        body: fd
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('เกิดข้อผิดพลาด: ' + (data.error || 'บันทึกไม่สำเร็จ'));
        }
    })
    .catch(err => console.error('Error:', err));
}
