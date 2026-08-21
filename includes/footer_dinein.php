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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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
    function checkMyFoodStatus() {
        if (hasNotified) return;
        fetch('../member/api_check_my_order.php')
            .then(response => response.json())
            .then(data => {
                if (data.food_ready === true) {
                    playFoodReadySound();

                    let toastEl = document.getElementById('foodReadyToast');
                    let toast = new bootstrap.Toast(toastEl);
                    toast.show();
                    
                    hasNotified = true;
                }
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
                    alert('ขอบคุณค่ะ แล้วพบกันใหม่ 🙏');
                    fetch('../qr_table/end_session.php').finally(function() {
                        window.location.replace('https://www.google.com/');
                    });
                }
            }).catch(err => console.error(err));
    }

    // เริ่มทำงานเมื่อโหลดหน้าเว็บเสร็จ
    document.addEventListener("DOMContentLoaded", function() {
        setInterval(checkMyFoodStatus, 5000);
        setInterval(checkBillClosed, 5000);
    });
    </script>

</body>
</html>