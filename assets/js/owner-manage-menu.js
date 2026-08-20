function selectAll(groupClassName) {
    const checkboxes = document.querySelectorAll('.' + groupClassName);
    let allChecked = true;
    checkboxes.forEach(cb => { if (!cb.checked) allChecked = false; });
    checkboxes.forEach(cb => { cb.checked = !allChecked; });
}

function changeStatus(id, newStatus) {
    if (confirm('ยืนยันการเปลี่ยนสถานะเมนูนี้?')) {
        let formData = new FormData();
        formData.append('update_status_id', id);
        formData.append('new_status_val', newStatus);

        fetch('api_toggle_menu_status.php', { method: 'POST', body: formData })
        .then(response => response.text())
        .then(data => {
            if (data.trim() === 'success') {
                let btn = document.getElementById('status-btn-' + id);
                if (newStatus === 1) {
                    btn.className = 'status-btn status-green';
                    btn.innerHTML = '● พร้อมขาย';
                    btn.setAttribute('onclick', 'changeStatus(' + id + ', 0)');
                } else {
                    btn.className = 'status-btn status-gray';
                    btn.innerHTML = '● ไม่พร้อมขาย';
                    btn.setAttribute('onclick', 'changeStatus(' + id + ', 1)');
                }
            } else {
                Swal.fire({icon: 'error', title: 'เกิดข้อผิดพลาด!'});
            }
        });
    }
}

// แสดงแอนิเมชัน Loading หมุนๆ ตอนกดบันทึกข้อมูล
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function (e) {
        Swal.fire({
            title: 'กำลังบันทึกข้อมูล...',
            html: 'กรุณารอสักครู่',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading()
            }
        });
    });
});
