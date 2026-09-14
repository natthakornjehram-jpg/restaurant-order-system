  </div><!-- /.owner-main -->
</div><!-- /.owner-shell -->

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/auto-hide-header.js"></script>
<script src="https://cdn.jsdelivr.net/npm/animejs@3.2.2/lib/anime.min.js"></script>

<script>
    //footer_owner.php
/**
 * 0. กล่องแจ้งเตือนกลาง ใช้แทน alert()/confirm() ของเบราว์เซอร์ทั้งหมด
 *    เด้งเป็นกรอบเข้มที่ขอบบนจอ ปิดเองอัตโนมัติ ไม่บล็อกการทำงาน ลื่นไหลกว่าป็อปอัปเบราว์เซอร์
 */
function ownerNotify(message, icon = 'success') {
    Swal.fire({
        toast: true,
        position: 'top',
        showConfirmButton: false,
        timer: 2200,
        timerProgressBar: true,
        icon: icon,
        title: message,
        background: '#212529',
        color: '#fff'
    });
}

// อ่านข้อความแจ้งเตือนที่ค้างไว้ก่อนหน้า reload (เช่น หลังบันทึก/ลบข้อมูลผ่าน AJAX แล้วรีโหลดหน้า)
(function () {
    try {
        const pending = sessionStorage.getItem('ownerFlashMsg');
        if (pending) {
            sessionStorage.removeItem('ownerFlashMsg');
            ownerNotify(pending);
        }
    } catch (e) { /* sessionStorage ใช้ไม่ได้ก็แค่ข้ามไป ไม่กระทบการทำงานหลัก */ }
})();

/**
 * 0.5 จำตำแหน่งเลื่อนหน้าจอไว้ก่อนหน้าจะ reload/เปลี่ยนหน้า แล้วเลื่อนกลับไปที่เดิมให้อัตโนมัติ
 *     แก้ปัญหาบันทึก/ลบ/แก้ไขรายการที่อยู่ล่างๆ หน้า (เช่น จัดการตัวเลือกเสริม, จัดการเมนู) แล้วหน้าเด้งขึ้นบนสุดทุกครั้ง
 *     ใช้ beforeunload ดักทุกกรณีที่หน้าออกไป ไม่ว่าจะ submit ฟอร์มปกติ, fetch() แล้วสั่ง location.href/reload เอง, หรือกดลิงก์
 */
(function () {
    const scrollKey = 'ownerScrollPos:' + location.pathname;

    window.addEventListener('beforeunload', function () {
        try { sessionStorage.setItem(scrollKey, String(window.scrollY)); } catch (e) {}
    });

    try {
        const saved = sessionStorage.getItem(scrollKey);
        if (saved !== null) {
            sessionStorage.removeItem(scrollKey);
            const y = parseInt(saved, 10) || 0;
            // ต้องระบุ behavior: 'instant' เท่านั้น (ไม่ใช่ 'auto' ซึ่งแปลว่า "ให้ใช้ค่าตาม CSS scroll-behavior"
            // ยังคงเลื่อนแบบมีแอนิเมชันเหมือนเดิม) เพื่อบังคับข้าม scroll-behavior: smooth ที่ Bootstrap
            // ตั้งไว้ที่ :root แล้วเด้งไปตำแหน่งเดิมทันทีแบบไม่มีการเลื่อนให้เห็นเลย
            const jump = () => window.scrollTo({ top: y, left: 0, behavior: 'instant' });
            // เผื่อเนื้อหาบางส่วนยังไม่ทัน render เต็มความสูงตอน DOM พร้อม ลองเลื่อนซ้ำอีกครั้งหลัง load เสร็จ
            requestAnimationFrame(jump);
            window.addEventListener('load', jump);
        }
    } catch (e) {}
})();

/**
 * 0.6 ใช้แทน confirm() ของเบราว์เซอร์ทุกจุด เพราะเบราว์เซอร์/เว็บวิวบางตัว (เช่น แอปในเครือข่ายสังคม,
 *     การตั้งค่า "block additional dialogs" ของ Chrome หลังเจอ popup ถี่ๆ) บล็อก confirm()/alert() แบบเงียบๆ
 *     โดยคืนค่า false ทันทีโดยไม่ถามผู้ใช้เลย ทำให้ปุ่มที่พึ่ง onclick="return confirm(...)" ไม่ทำงานเลย
 *     แต่ไม่มีสัญญาณเตือนอะไรให้เห็นว่าทำไม (บั๊กที่ตรวจจับยากมาก เจอจากผู้ใช้รายงานว่า "ออกจากระบบไม่ได้")
 *     ใช้ modal ของ SweetAlert2 แทน เพราะเป็น modal ในหน้าเว็บจริงๆ ไม่ใช่ dialog ของเบราว์เซอร์ จึงไม่โดนบล็อก
 */
function ownerConfirm(message) {
    return Swal.fire({
        title: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#dc3545',
        reverseButtons: true
    }).then((result) => result.isConfirmed);
}

// ใช้แทน onclick="return confirm(...)" บนลิงก์ <a> (เช่น ปุ่มออกจากระบบ)
function ownerConfirmNavigate(event, message, url) {
    event.preventDefault();
    ownerConfirm(message).then(function (ok) {
        if (ok) window.location.href = url;
    });
    return false;
}

// ใช้แทน onsubmit="return confirm(...)" บนฟอร์ม (เช่น ปุ่มลบ)
function ownerConfirmSubmit(event, message) {
    event.preventDefault();
    const form = event.target;
    ownerConfirm(message).then(function (ok) {
        if (ok) form.submit();
    });
    return false;
}

/**
 * 1. ฟังก์ชันสลับสถานะ (Toggle) แบบ AJAX + SweetAlert2
 */
function toggleStatus(id, type, currentStatus) {
    const newStatus = (currentStatus == 1) ? 0 : 1;
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('type', type);
    formData.append('new_status', newStatus);
    formData.append('csrf_token', CSRF_TOKEN);

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
                    btn.className = 'btn btn-toggle bg-toggle-shop' + (newStatus === 1 ? '' : ' is-closed');
                    btn.innerHTML = (newStatus === 1) ? '<i class="bi bi-shop me-1"></i> ร้านเปิดอยู่ (รับออเดอร์)' : '<i class="bi bi-shop me-1"></i> ร้านปิดอยู่';
                } else if (type === 'dinein_status') {
                    btn.className = 'btn btn-toggle bg-toggle-dinein' + (newStatus === 1 ? '' : ' is-closed');
                    btn.innerHTML = (newStatus === 1) ? '<i class="bi bi-cup-hot me-1"></i> รับทานที่ร้าน' : '<i class="bi bi-cup-hot me-1"></i> งดรับทานที่ร้าน';
                } else if (type === 'takeaway_status') {
                    btn.className = 'btn btn-toggle bg-toggle-takeaway' + (newStatus === 1 ? '' : ' is-closed');
                    btn.innerHTML = (newStatus === 1) ? '<i class="bi bi-bag-check me-1"></i> รับสั่งกลับบ้าน' : '<i class="bi bi-bag-check me-1"></i> งดรับสั่งกลับบ้าน';
                } else if (type === 'menu' || type === 'topping' || type === 'item') {
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
 * 2.5 พูดแจ้งเตือนด้วยเสียง (Web Speech API) ใช้คู่กับ playNotifySound()
 */
function speakThai(text) {
    try {
        if (!('speechSynthesis' in window)) return;
        speechSynthesis.cancel(); // กันเสียงพูดซ้อนกันถ้าแจ้งเตือนเข้ามาถี่ๆ
        const utter = new SpeechSynthesisUtterance(text);
        utter.lang = 'th-TH';
        utter.rate = 1;
        speechSynthesis.speak(utter);
    } catch (e) {
        console.warn('พูดแจ้งเตือนไม่ได้:', e);
    }
}

/**
 * 3. ระบบเช็คออเดอร์ใหม่แบบเกือบเรียลไทม์ (โพลทุก 2 วินาที)
 */
let lastPendingCount = null;
let lastPaymentsCount = null;

function checkNewOrders() {
    fetch('api_check_update.php')
    .then(r => r.json())
    .then(data => {
        if (lastPendingCount !== null && data.pending_count > lastPendingCount) {
            playNotifySound();
            speakThai('ออเดอร์เข้าแล้ว');
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

        // หน้าจัดการชำระเงิน: รีโหลดอัตโนมัติเมื่อมีรายการเปลี่ยนแปลง (สลิปใหม่เข้ามา / โต๊ะปิดบิลจากเครื่องอื่น)
        if (lastPaymentsCount !== null && data.payments_count !== lastPaymentsCount
            && window.location.pathname.includes('manage_payments.php')) {
            location.reload();
        }

        lastPendingCount = data.pending_count;
        lastPaymentsCount = data.payments_count;
    })
    .catch(err => console.error('API Error:', err));
}

// เช็คทุก 2 วินาที (เกือบเรียลไทม์ โดยไม่ต้องใช้ WebSocket)
if (lastPendingCount === null) checkNewOrders();
setInterval(checkNewOrders, 2000);
</script>
</body>
</html>