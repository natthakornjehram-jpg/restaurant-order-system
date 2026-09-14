// 1. ฟังก์ชันโหลดข้อมูลหมวดหมู่มาโชว์ในตาราง
function loadCategories() {
    fetch('cat_api.php?action=list')
    .then(res => res.json())
    .then(data => {
        let html = '';
        if (data && data.length > 0) {
            data.forEach(item => {
                const isActive = item.is_active == 1;
                html += `
                <tr>
                    <td class="ps-4 fw-bold">
                        ${item.category_name}
                        ${!isActive ? '<span class="badge bg-secondary ms-2">ซ่อนอยู่</span>' : ''}
                    </td>
                    <td class="text-center">
                        <button class="btn btn-outline-${isActive ? 'secondary' : 'success'} btn-sm rounded-pill px-3 me-2" onclick="toggleCategory(${item.category_id})" title="${isActive ? 'ซ่อนจากเมนูลูกค้า' : 'แสดงในเมนูลูกค้า'}">
                            <i class="bi ${isActive ? 'bi-eye-slash' : 'bi-eye'}"></i> ${isActive ? 'ซ่อน' : 'แสดง'}
                        </button>
                        <button class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="deleteCategory(${item.category_id})">
                            <i class="bi bi-trash"></i> ลบ
                        </button>
                    </td>
                </tr>`;
            });
        } else {
            html = '<tr><td colspan="2" class="text-center py-5 text-muted">ยังไม่มีข้อมูลหมวดหมู่</td></tr>';
        }
        document.getElementById('catList').innerHTML = html;
    })
    .catch(error => console.error('Error loading categories:', error));
}

// 2. ฟังก์ชันบันทึกข้อมูล (เพิ่มหมวดหมู่)
function saveCategory() {
    const nameInput = document.getElementById('cat_name');
    const name = nameInput.value.trim();
    if (!name) { ownerNotify('กรุณากรอกชื่อหมวดหมู่', 'error'); return; }

    const formData = new FormData();
    formData.append('category_name', name);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('cat_api.php?action=add', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            nameInput.value = ''; // ล้างช่องกรอกข้อมูล
            ownerNotify('เพิ่มหมวดหมู่เรียบร้อยแล้ว');
            loadCategories(); // โหลดตารางใหม่
        } else {
            ownerNotify('เกิดข้อผิดพลาด: ' + data.message, 'error');
        }
    })
    .catch(error => console.error('Error saving category:', error));
}

// 3. ฟังก์ชันลบข้อมูล (ลบทันทีไม่ต้องยืนยันซ้ำ ให้ลื่นไหล)
function deleteCategory(id) {
    const formData = new FormData();
    formData.append('id', id);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('cat_api.php?action=delete', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            ownerNotify('ลบหมวดหมู่แล้ว');
            loadCategories();
        } else {
            ownerNotify(data.message || 'ไม่สามารถลบได้', 'error');
        }
    })
    .catch(error => console.error('Error deleting category:', error));
}

// 4. ฟังก์ชันซ่อน/แสดงหมวดหมู่ (ไม่ลบ แค่ไม่โชว์ในเมนูลูกค้า)
function toggleCategory(id) {
    const formData = new FormData();
    formData.append('id', id);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('cat_api.php?action=toggle', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadCategories();
        } else {
            ownerNotify(data.message || 'ไม่สามารถเปลี่ยนสถานะได้', 'error');
        }
    })
    .catch(error => console.error('Error toggling category:', error));
}

// โหลดข้อมูลครั้งแรกเมื่อเปิดหน้า
loadCategories();
