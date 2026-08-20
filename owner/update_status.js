// update_status.js

function toggleStatus(id, type, currentStatus) {
    const newStatus = (currentStatus == 1) ? 0 : 1;
    let reason = "";

    // 1. ถ้าเป็นการปิดรับออเดอร์ออนไลน์ (id === 'online' และกดปิด) ให้เด้งถามสาเหตุ
    if (id === 'online' && newStatus === 0) {
        reason = prompt("ระบุสาเหตุที่ปิดรับออเดอร์ออนไลน์ชั่วคราว:", "ขออภัย ขณะนี้คิวเต็มแล้ว");
        // ถ้าผู้ใช้กด Cancel (ยกเลิก) ในหน้าต่าง Prompt ให้หยุดการทำงานไปเลย
        if (reason === null) return; 
    }

    // 2. แพ็กข้อมูลใส่ FormData ให้ตรงกับที่ PHP รอรับ
    const formData = new FormData();
    formData.append('id', id);             // ส่ง 'shop' หรือ 'online'
    formData.append('type', type);         // ส่ง 'shop_status'
    formData.append('new_status', newStatus); // ส่ง 0 หรือ 1
    formData.append('reason', reason);     // ส่งเหตุผล (ถ้ามี)

    // 3. ส่งข้อมูลไปที่ API
    fetch('api_update_status.php', { 
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // ถ้าอัปเดตสำเร็จ ให้รีโหลดหน้าเว็บเพื่อเปลี่ยนสีปุ่ม
            window.location.reload(); 
        } else {
            alert('เกิดข้อผิดพลาด: ' + data.error);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ ตรวจสอบชื่อไฟล์ api_update_status.php');
    });
}