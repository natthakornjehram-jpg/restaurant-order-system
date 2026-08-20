  </div><!-- /.owner-main -->
</div><!-- /.owner-shell -->

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    //footer_owner.php
/**
 * 1. ฟังก์ชันสลับสถานะ (Toggle) แบบ AJAX + SweetAlert2
 */
function toggleStatus(id, type, currentStatus) {
    const newStatus = (currentStatus == 1) ? 0 : 1;
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('type', type);
    formData.append('new_status', newStatus);

    fetch('update_status_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });

            Toast.fire({
                icon: 'success',
                title: 'อัปเดตสถานะเรียบร้อย'
            });

            // ค้นหาปุ่มเพื่อเปลี่ยนสีและข้อความ
            const btn = document.querySelector(`[data-id="${id}"][data-type="${type}"]`);
            if (btn) {
                if (type === 'shop_status') {
                    if (id === 'shop') {
                        btn.className = (newStatus === 1) ? 'btn btn-toggle bg-shop-open' : 'btn btn-toggle bg-status-closed';
                        btn.innerHTML = (newStatus === 1) ? '<i class="bi bi-shop me-2"></i> ร้านเปิดอยู่' : '<i class="bi bi-shop me-2"></i> ร้านปิดอยู่';
                    } else {
                        btn.className = (newStatus === 1) ? 'btn btn-toggle bg-online-open' : 'btn btn-toggle bg-status-closed';
                        btn.innerHTML = (newStatus === 1) ? '<i class="bi bi-globe me-2"></i> รับออนไลน์' : '<i class="bi bi-globe me-2"></i> ปิดออนไลน์';
                    }
                } else if (type === 'menu' || type === 'topping') {
                    // ปรับแต่งปุ่ม เมนู, ท็อปปิ้ง (มีของ/หมด)
                    if (newStatus == 1) {
                        btn.className = 'btn btn-sm rounded-pill px-3 btn-success';
                        btn.innerHTML = `<i class="bi bi-check-circle me-1"></i> มีของ`;
                    } else {
                        btn.className = 'btn btn-sm rounded-pill px-3 btn-secondary';
                        btn.innerHTML = `<i class="bi bi-x-circle me-1"></i> หมด`;
                    }
                }
                // อัปเดตค่า onclick ให้เป็นค่าใหม่
                btn.setAttribute('onclick', `toggleStatus('${id}', '${type}', ${newStatus})`);
            }
        } else {
            Swal.fire('ผิดพลาด!', data.error || 'เกิดข้อผิดพลาดบางอย่าง', 'error');
        }
    })
    .catch(err => console.error('Error:', err));
}

/**
 * 2. เสียงแจ้งเตือน (สร้างเสียงเองด้วย Web Audio API ไม่ต้องพึ่งไฟล์เสียงภายนอก)
 */
function playNotifySound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const now = ctx.currentTime;
        [880, 1175].forEach((freq, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0, now + i * 0.15);
            gain.gain.linearRampToValueAtTime(0.35, now + i * 0.15 + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.001, now + i * 0.15 + 0.35);
            osc.connect(gain).connect(ctx.destination);
            osc.start(now + i * 0.15);
            osc.stop(now + i * 0.15 + 0.4);
        });
    } catch (e) {
        console.warn('เล่นเสียงแจ้งเตือนไม่ได้:', e);
    }
}

/**
 * 3. ระบบเช็คออเดอร์ใหม่แบบเกือบเรียลไทม์ (โพลทุก 3 วินาที)
 */
let lastPendingCount = null;

function checkNewOrders() {
    fetch('api_check_update.php')
    .then(r => r.json())
    .then(data => {
        if (lastPendingCount !== null && data.pending_count > lastPendingCount) {
            playNotifySound();
            Swal.fire({
                icon: 'info',
                title: 'มีออเดอร์ใหม่เข้า!',
                text: 'ลูกค้าสั่งอาหารมาใหม่ ตรวจสอบหน้าครัวด่วน',
                position: 'top-end',
                toast: true,
                timer: 5000,
                showConfirmButton: false,
                timerProgressBar: true
            });

            // รีโหลดหน้าเฉพาะตอนอยู่หน้า manage_orders.php
            if(window.location.pathname.includes('manage_orders.php')) {
                location.reload();
            }
        }

        lastPendingCount = data.pending_count;
    })
    .catch(err => console.error('API Error:', err));
}

// เช็คทุก 3 วินาที (เกือบเรียลไทม์ โดยไม่ต้องใช้ WebSocket)
if (lastPendingCount === null) checkNewOrders();
setInterval(checkNewOrders, 3000);
</script>
</body>
</html>