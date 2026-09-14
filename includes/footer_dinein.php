<div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 9999; margin-top: 20px;">
        <div id="foodReadyToast" class="toast align-items-center text-bg-warning border-0 shadow-lg rounded-4" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="15000">
            <div class="d-flex p-2">
                <div class="toast-body d-flex align-items-center text-dark">
                    <i class="bi bi-bell-ringing-fill fs-1 me-3 text-danger"></i>
                    <div>
                        <h5 class="fw-bold mb-1">อาหารของคุณพร้อมแล้ว! 🍽️</h5>
                        <span class="small">กำลังนำไปเสิร์ฟที่โต๊ะ หรือติดต่อรับที่เคาน์เตอร์ครับ</span>
                    </div>
                </div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/auto-hide-header.js"></script>

    <script>
    // เสียงแจ้งเตือน สร้างเองด้วย Web Audio API (ไม่ต้องพึ่งไฟล์เสียงภายนอก เหมือนฝั่งเจ้าของร้าน)
    function playFoodReadySound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const now = ctx.currentTime;
            [660, 990, 1320].forEach((freq, i) => {
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

    let hasNotified = false;
    let lastStatusHash = null;
    function checkMyFoodStatus() {
        fetch('../member/api_check_my_order.php')
            .then(response => response.json())
            .then(data => {
                if (!hasNotified && data.food_ready === true) {
                    playFoodReadySound();

                    let toastEl = document.getElementById('foodReadyToast');
                    let toast = new bootstrap.Toast(toastEl);
                    toast.show();

                    hasNotified = true;
                }

                // หน้าบิล: รีเฟรชอัตโนมัติเมื่อสถานะออเดอร์ไหนก็ตามเปลี่ยน (ไม่ต้องกดรีเฟรชเอง)
                if (lastStatusHash !== null && data.status_hash !== lastStatusHash
                    && window.location.pathname.includes('my_bill.php')) {
                    location.reload();
                }
                lastStatusHash = data.status_hash;
            }).catch(err => console.error(err));
    }

    let billClosed = false;
    function checkBillClosed() {
        if (billClosed) return;
        fetch('../qr_table/api_check_bill.php')
            .then(response => response.json())
            .then(data => {
                if (data.closed === true) {
                    billClosed = true;

                    // ตัดสิทธิ์ฝั่งเซิร์ฟเวอร์ก่อนเป็นอันดับแรกเสมอ (สำคัญ! ต้องมาก่อน alert() ด้านล่าง เพราะ
                    // alert() บล็อกการทำงานของสคริปต์ไว้จนกว่าลูกค้าจะกด OK ถ้าลูกค้าวางมือถือทิ้งไว้เฉยๆ
                    // ไม่กด OK จะทำให้ fetch นี้ไม่มีวันถูกยิงเลย ช่องโหว่นี้ต้องกันไว้ก่อน)
                    fetch('../qr_table/end_session.php');

                    alert('ชำระเงินเรียบร้อยแล้ว ขอบคุณที่มาอุดหนุนครับ 🙏');

                    // รอ 5 นาทีค่อยเด้งออกจริง ให้เวลาลูกค้าดูหน้าจอต่อได้อีกสักพักโดยไม่รู้สึกโดนไล่กะทันหัน
                    // (สิทธิ์การสั่งอาหาร/ดูบิลถูกตัดไปตั้งแต่ตอน fetch end_session.php ข้างบนแล้ว
                    // ต่อให้กดรีเฟรชหรือย้อนกลับระหว่างรอ 5 นาทีนี้ ก็จะโดนเด้งออกจาก server ทันทีอยู่ดี)
                    setTimeout(function () {
                        window.location.replace('https://www.google.com');
                    }, 5 * 60 * 1000);
                }
            }).catch(err => console.error(err));
    }

    // กันกดปุ่มย้อนกลับแล้วเจอหน้าเก่าที่เบราว์เซอร์ cache ไว้ (bfcache) หลังบิลปิด/session หมดอายุไปแล้ว
    // Cache-Control header กันได้ส่วนใหญ่ แต่บางเบราว์เซอร์ยังคืนหน้าจาก bfcache ตอนกดย้อนอยู่ดี
    // เช็ก event.persisted แล้วสั่งโหลดใหม่ ให้ server เช็ก session ซ้ำทุกครั้งที่กดย้อนมาหน้านี้
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    // เริ่มทำงานเมื่อโหลดหน้าเว็บเสร็จ
    document.addEventListener("DOMContentLoaded", function() {
        setInterval(checkMyFoodStatus, 5000);
        setInterval(checkBillClosed, 5000);
    });
    </script>

</body>
</html>