// assets/js/auto-hide-header.js
// ซ่อน header อัตโนมัติตอนเลื่อนหน้าจอลง (ให้พื้นที่จอมากขึ้น) แล้วเลื่อนกลับมาโชว์ตอนเลื่อนขึ้น
// ใช้กับทุก header ที่มี class "auto-hide-header" (ฝั่งลูกค้าใน includes/nav_dinein.php,
// ฝั่งเจ้าของร้านใน includes/nav_owner.php) ใช้ GSAP + ScrollTrigger เหมือนกันทั้งสองฝั่ง
document.addEventListener('DOMContentLoaded', function () {
    var header = document.querySelector('.auto-hide-header');
    if (!header || typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') return;

    gsap.registerPlugin(ScrollTrigger);

    // เตรียมอนิเมชัน "เลื่อน header ขึ้นพ้นจอ" ไว้ก่อน (paused) แล้วตั้ง progress(1) ให้เริ่มที่สถานะโชว์ปกติ
    var showAnim = gsap.from(header, {
        yPercent: -100,
        paused: true,
        duration: 0.2
    }).progress(1);

    ScrollTrigger.create({
        start: 'top top',
        end: 'max',
        onUpdate: function (self) {
            // เลื่อนขึ้น (direction -1) โชว์ header, เลื่อนลง (direction 1) ซ่อน header
            self.direction === -1 ? showAnim.play() : showAnim.reverse();
        }
    });
});
