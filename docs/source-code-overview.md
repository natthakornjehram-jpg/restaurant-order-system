# เอกสารอธิบาย Source Code — ระบบจัดการร้านอาหาร (QR Ordering + Owner Dashboard)

เอกสารนี้สรุปโครงสร้าง, การทำงาน, และจุดออกแบบที่สำคัญของโปรเจกต์ทั้งหมด จากการไล่อ่านซอร์สโค้ดทุกไฟล์ในระบบ
(ไม่รวมไฟล์ asset/css/js และไลบรารี PHPMailer ที่เป็นโค้ดบุคคลที่สาม)

---

## 1. ภาพรวมระบบ

ระบบนี้เป็น **ระบบร้านอาหารร้านเดียว (single-tenant)** ที่รองรับ 2 ช่องทางการสั่งอาหาร:

1. **ทานที่ร้าน (Dine-in)** — ลูกค้าสแกน QR Code ที่ติดอยู่บนโต๊ะ เข้าสู่เมนู สั่งอาหาร แล้วรอเสิร์ฟ/เช็คบิลที่โต๊ะ
2. **สั่งกลับบ้าน (Takeaway)** — ลูกค้าเข้าลิงก์เมนูตรงๆ (ไม่ต้องสแกน QR) สั่งแล้วเลือกวิธีจ่ายเงิน (เงินสด/โอน/QR เคาน์เตอร์) มารับที่ร้าน

ฝั่ง **เจ้าของร้าน** มีแดชบอร์ดสำหรับจัดการออเดอร์แบบเกือบเรียลไทม์ (โพลทุก 2 วินาที), อนุมัติการชำระเงิน, จัดการเมนู/ท็อปปิ้ง/คลังสินค้า/โต๊ะ, และดูรายงานยอดขาย

ระบบไม่มีระบบสมาชิกลูกค้า — ลูกค้าไม่ต้องสมัคร/ล็อกอิน ความเป็นเจ้าของออเดอร์อ้างอิงจาก PHP session เท่านั้น มีเพียงบัญชี **เจ้าของร้านคนเดียว** ที่ล็อกอินเข้าระบบจัดการได้

---

## 2. เทคโนโลยีที่ใช้

| ส่วนประกอบ | รายละเอียด |
|---|---|
| ภาษา/รูปแบบ | PHP (procedural, ไม่ใช้ framework) + MySQLi (prepared statements) |
| ฐานข้อมูล | MySQL/MariaDB (`database/restaurant_db.sql`) |
| Frontend | Bootstrap 5.3.8, Bootstrap Icons, SweetAlert2 (แจ้งเตือน/ยืนยัน), GSAP/anime.js (แอนิเมชัน) — โหลดผ่าน CDN ทั้งหมด |
| ฟอนต์ | Google Fonts: Mitr (หัวข้อ), Sarabun (เนื้อหา) |
| อีเมล | PHPMailer (vendor แบบไม่ใช้ Composer) ผ่าน Gmail SMTP สำหรับส่ง OTP |
| Realtime (จำลอง) | Polling ผ่าน `fetch()` ทุก 2–5 วินาที ไม่มี WebSocket |

---

## 3. โครงสร้างไฟล์

```
restaurant/
├── index.php                  หน้าแรกของเว็บ (เช็คสถานะร้าน + ลิงก์เข้าเมนู/เจ้าของร้าน)
├── login.php                  ล็อกอินเจ้าของร้าน (มีระบบกัน brute-force)
├── logout.php                 ล้าง session ออกจากระบบ
├── forgot_password.php        ลืมรหัสผ่าน (OTP 3 ขั้นตอน ผ่านอีเมล)
├── cleanup_slips.php          ลบไฟล์สลิปเก่าอายุเกิน 3 ปี (รันผ่าน cron/CLI)
│
├── includes/                  ไฟล์ share ใช้ร่วมกันทุกหน้า
│   ├── db.php                 เชื่อมต่อ DB, ตั้ง timezone, auto-migration, BASE_URL, โหลดข้อมูลร้าน
│   ├── csrf.php                สร้าง/ตรวจ CSRF token
│   ├── upload_helper.php      อัปโหลดรูปภาพแบบปลอดภัย (เช็ก MIME จริง ไม่เชื่อนามสกุลไฟล์)
│   ├── send_email_otp.php     ส่งอีเมล OTP ผ่าน PHPMailer
│   ├── owner_flash.php        แสดง toast แจ้งเตือนค้างจาก session (หลัง redirect)
│   ├── header_owner.php / footer_owner.php / nav_owner.php     เลย์เอาต์ฝั่งเจ้าของร้าน (รวม auto-polling ออเดอร์ใหม่)
│   └── header_dinein.php / footer_dinein.php / nav_dinein.php  เลย์เอาต์ฝั่งลูกค้า (รวม auto-polling สถานะอาหาร/บิล)
│
├── owner/                     ฝั่งเจ้าของร้าน (ต้องล็อกอิน — คุมสิทธิ์ผ่าน auth_owner.php)
│   ├── auth_owner.php         Gatekeeper กลาง เช็ค session ทุกหน้า/ทุก API ฝั่งเจ้าของร้าน
│   ├── register_owner.php     สมัครบัญชีเจ้าของร้าน (ทำได้ครั้งเดียว ระบบร้านเดียว)
│   ├── dashboard.php          หน้าแรกเจ้าของร้าน: สถิติวันนี้ + สวิตช์เปิด/ปิดร้าน (รวม/ทานที่ร้าน/กลับบ้าน)
│   ├── manage_orders.php      คิวออเดอร์ที่ต้องทำ (pending/cooking) + ปุ่มรับ/ทำเสร็จ
│   ├── manage_payments.php    อนุมัติชำระเงิน (ทานที่ร้าน & กลับบ้าน แยกแท็บ)
│   ├── manage_menu.php        จัดการเมนูอาหาร + ผูกท็อปปิ้งกับเมนู
│   ├── manage_categories.php  จัดการหมวดหมู่เมนู (CRUD ผ่าน cat_api.php)
│   ├── manage_toppings.php    จัดการกลุ่ม/ตัวเลือกเสริม (ท็อปปิ้ง)
│   ├── manage_stock.php       จัดการคลังสินค้า (เมนู + ท็อปปิ้งที่ติดตามสต็อก)
│   ├── manage_tables.php      จัดการโต๊ะ + สร้าง QR Code ต่อโต๊ะ
│   ├── reports.php            รายงานยอดขายรายวัน + เมนู/ท็อปปิ้งขายดี
│   ├── settings.php           ตั้งค่าร้าน (โลโก้, ข้อมูลติดต่อ, QR พร้อมเพย์, เปิด/ปิดร้าน)
│   ├── print_receipt.php      พิมพ์ใบเสร็จรายออเดอร์
│   ├── print_daily_report.php พิมพ์สรุปยอดขายรายวัน
│   └── api_*.php / *_ajax.php / cat_api.php   เอนด์พอยต์ AJAX (คืนค่า JSON) สำหรับหน้าเว็บฝั่งเจ้าของร้าน
│
├── qr_table/                  ฝั่งลูกค้าทานที่ร้าน (dine-in)
│   ├── menu_dinein.php        หน้าเมนูหลัก (รวมเมนู + ตะกร้าเป็นแท็บเดียว) — ยังใช้กับลูกค้ากลับบ้านแบบไม่ผ่าน QR ด้วย
│   ├── join_table.php         หน้ากรอกรหัสร่วมโต๊ะ 4 หลัก (กันคนแปลกหน้ามาสั่งปนโต๊ะ)
│   ├── my_bill.php            บิลรวมของโต๊ะ (เฉพาะทานที่ร้าน)
│   ├── api_check_bill.php     ให้หน้าเว็บลูกค้าโพลเช็คว่าร้านปิดบิลให้แล้วหรือยัง
│   ├── end_session.php        เคลียร์ session โต๊ะหลังปิดบิล (กันกดย้อนกลับแล้วสั่งซ้ำ)
│   └── cart_dinein.php        ไฟล์ redirect เก่า (ตะกร้าย้ายไปรวมใน menu_dinein.php แล้ว)
│
└── member/                    ตะกร้า/ส่งออเดอร์ (ใช้ร่วมกันทั้งทานที่ร้าน & กลับบ้าน)
    ├── cart_action.php        เพิ่ม/ลบ/ปรับจำนวนสินค้าในตะกร้า (session-based)
    ├── submit_order.php       ยืนยันส่งออเดอร์ (validate ราคา/สต็อกฝั่งเซิร์ฟเวอร์ + บันทึกลง DB)
    ├── order_detail.php       ดูสถานะออเดอร์ของตัวเอง (เฉพาะลูกค้ากลับบ้าน)
    └── api_check_my_order.php โพลเช็คว่าอาหารพร้อมเสิร์ฟหรือยัง (เฉพาะทานที่ร้าน)
```

---

## 4. ฐานข้อมูล

ไฟล์ schema: [`database/restaurant_db.sql`](../database/restaurant_db.sql) — มี 16 ตาราง

**ตารางข้อมูลหลัก**
- `owner` — ข้อมูลร้าน/เจ้าของร้าน (มีแถวเดียวเสมอ, `owner_id = 1`) รวมสวิตช์เปิด/ปิดร้าน 3 ตัว (`is_shop_open`, `is_dinein_open`, `is_takeaway_open`), ข้อมูลรับเงิน (`promptpay_qr`, `bank_info`)
- `restauranttable` — โต๊ะอาหาร: `status` (available/occupied), `qr_token` (โทเค็นลับกันเดา URL), `join_code` (รหัสร่วมโต๊ะ 4 หลัก)
- `category` / `subcategory` — หมวดหมู่เมนู
- `item` — เมนูอาหาร: ราคา, รูป, `is_active`, `is_featured`, `stock_qty`/`use_stock`
- `topping_categories` / `topping` — กลุ่มตัวเลือกเสริมและตัวเลือกย่อย (มี `use_stock` แยกต่อรายการ — เช่น "ระดับความเผ็ด" ไม่ต้องนับสต็อก แต่ "หมูกรอบเพิ่ม" นับ)
- `menu_toppings` — ตารางเชื่อม เมนู ↔ ท็อปปิ้งที่ใช้ได้
- `orders` — หัวออเดอร์: `order_type` (dine_in/takeaway), `order_status` (pending→cooking→served/ready), `payment_status`, `daily_order_no` (เลขคิวรีเซ็ตทุกวัน)
- `orderdetail` / `orderdetail_topping` — รายการอาหารในออเดอร์ + ท็อปปิ้งที่เลือก
- `payment` — การชำระเงิน (เฉพาะออเดอร์กลับบ้าน/ออนไลน์; ทานที่ร้านจ่ายตอนปิดบิลแทน)

**ตารางความปลอดภัย (rate-limit / audit)**
- `login_attempts` — กัน brute-force ล็อกอินเจ้าของร้าน
- `otp_verify_attempts` / `password_reset` — กันสุ่ม OTP ตอนลืมรหัสผ่าน
- `join_pin_attempts` — กันสุ่มรหัสร่วมโต๊ะ 4 หลัก

---

## 5. Flow การทำงานหลัก

### 5.1 ลูกค้าทานที่ร้าน (Dine-in)

1. สแกน QR ที่โต๊ะ → เข้า `qr_table/menu_dinein.php?table=A1&t=<qr_token>`
2. ระบบตรวจ `qr_token` กับค่าที่ผูกไว้ในฐานข้อมูล (กันเดาเลขโต๊ะ) → ผูก `table_id` ลง session
3. เลือกประเภทออเดอร์ (ทานที่ร้าน/กลับบ้าน — ถ้าร้านเปิดรับทั้งสองแบบ)
4. ถ้าเป็นโต๊ะที่มีคนอื่นสั่งไปก่อนแล้ว (`join_code` ถูกสุ่มไว้) และยังไม่เคยยืนยันรหัส → เด้งไป `join_table.php` ให้กรอกรหัส 4 หลัก
5. เลือกเมนู + ท็อปปิ้ง → เพิ่มลงตะกร้า (`member/cart_action.php`, เก็บใน `$_SESSION['cart']`)
6. กด "ยืนยันส่งออเดอร์" → `member/submit_order.php`
   - ตรวจสอบสถานะร้าน/ประเภทออเดอร์ซ้ำฝั่งเซิร์ฟเวอร์
   - **คำนวณราคาใหม่จากฐานข้อมูลเสมอ** (ไม่เชื่อราคาที่ client ส่งมา หรือแคชไว้ในตะกร้า)
   - เปิด transaction: สร้างออเดอร์ + ตัดสต็อก (ล้มเหลว/rollback ถ้าของไม่พอ) + สุ่ม `join_code` (ถ้าเป็นออเดอร์แรกของโต๊ะ)
7. เจ้าของร้านเห็นออเดอร์ใหม่ที่ `manage_orders.php` (auto-refresh ทุก 2 วิ) → กด "รับออเดอร์" → "ปรุงเสร็จแล้ว"
8. ลูกค้าเช็คสถานะ/บิลรวมที่ `my_bill.php` (auto-poll ผ่าน `api_check_my_order.php`)
9. เจ้าของร้านกด "ยืนยันรับเงิน & ปิดบิล" ที่ `manage_payments.php` → `api_approve_payment.php` → คืนสถานะโต๊ะเป็นว่างถ้าไม่มีออเดอร์ค้างจ่ายแล้ว
10. ฝั่งลูกค้าโพลเจอบิลปิดแล้ว (`api_check_bill.php`) → เรียก `end_session.php` เคลียร์ session ก่อนแจ้งเตือน (กันกดย้อนกลับสั่งซ้ำ)

### 5.2 ลูกค้าสั่งกลับบ้าน (Takeaway)

เหมือนข้างต้นแต่ไม่มีโต๊ะผูก, ไม่มีรหัสร่วมโต๊ะ, ต้องกรอกชื่อ-เบอร์โทร, เลือกวิธีจ่ายเงิน (เงินสด/QR เคาน์เตอร์/โอนเงินเอง+แนบสลิปทันที) และดูสถานะออเดอร์ของตัวเองที่ `member/order_detail.php` (ผูกสิทธิ์ด้วย `$_SESSION['guest_order_ids']` กัน IDOR)

### 5.3 เจ้าของร้าน

`login.php` → `owner/dashboard.php` (สถิติวันนี้ + สวิตช์เปิด/ปิดร้าน 3 ระดับ) → จัดการออเดอร์/ชำระเงิน/เมนู/สต็อก/โต๊ะ/รายงาน ตามเมนูด้านข้าง ทุกหน้า/ทุก API ผ่าน `auth_owner.php` เป็นด่านตรวจสิทธิ์กลาง

---

## 6. จุดออกแบบด้านความปลอดภัยที่น่าสนใจ

โค้ดมีคอมเมนต์อธิบายเหตุผลของแต่ละมาตรการไว้ค่อนข้างละเอียด สรุปที่สำคัญ:

| มาตรการ | อยู่ที่ไฟล์ | ป้องกันอะไร |
|---|---|---|
| CSRF token ทุกฟอร์ม/AJAX ที่แก้ข้อมูล | `includes/csrf.php` + ทุกหน้า POST | ป้องกัน Cross-Site Request Forgery |
| Rate-limit ล็อกอิน (5 ครั้ง/15 นาที) | `login.php` + ตาราง `login_attempts` | Brute-force รหัสผ่าน |
| Rate-limit ขอ/ยืนยัน OTP | `forgot_password.php` + `password_reset`, `otp_verify_attempts` | สุ่ม OTP 6 หลัก |
| Rate-limit รหัสร่วมโต๊ะ (10 ครั้ง/15 นาที) | `join_table.php` + `join_pin_attempts` | สุ่มรหัส PIN 4 หลัก |
| `qr_token` ต่อโต๊ะ | `manage_tables.php`, `menu_dinein.php` | เดาเลขโต๊ะแล้วยิง URL ตรงเข้าเมนู/บิลโต๊ะอื่น |
| ตรวจ MIME จริงของไฟล์อัปโหลด (ไม่เชื่อนามสกุล) | `includes/upload_helper.php` | อัปโหลดไฟล์อันตรายปลอมเป็นรูปภาพ |
| แยก dev/production ด้วย `REMOTE_ADDR` (ไม่ใช้ `HTTP_HOST`) | `includes/db.php` | ปลอม Host header เพื่อเปิด error/migration บนโฮสต์จริง |
| คำนวณราคา/สต็อกใหม่จาก DB เสมอตอน submit order | `member/submit_order.php` | ลูกค้าแก้ราคา/สต็อกจาก client-side |
| ผูกสิทธิ์ดูออเดอร์ด้วย session แทน table_id | `member/order_detail.php` | IDOR — เดา order_id ของลูกค้าคนอื่นที่โต๊ะเดียวกัน |
| Transaction + `FOR UPDATE` ตอนตัดสต็อก/สร้างเลขคิว | `member/submit_order.php` | Race condition เมื่อมีออเดอร์เข้าพร้อมกัน |
| Legacy password auto-upgrade เป็น `password_hash` | `login.php` | รองรับข้อมูลเก่าที่อาจเก็บรหัสผ่านแบบ plain-text ให้ปลอดภัยขึ้นทันทีที่ล็อกอินสำเร็จ |
| `cleanup_slips.php` เช็ก session/CLI ก่อนรัน | `cleanup_slips.php` | เปิด URL ตรงๆ แล้วสั่งลบไฟล์ได้โดยไม่ต้องล็อกอิน |

---

## 7. ตารางอ้างอิงไฟล์ทั้งหมด (Quick Reference)

### Root
| ไฟล์ | หน้าที่ |
|---|---|
| `index.php` | หน้าแรก แสดงสถานะร้าน + ทางเข้าเมนู/หลังบ้าน |
| `login.php` | ฟอร์มล็อกอินเจ้าของร้าน + brute-force protection |
| `logout.php` | ล้าง session/cookie แล้ว redirect ไป login |
| `forgot_password.php` | รีเซ็ตรหัสผ่าน 3 ขั้นตอน (เบอร์โทร → OTP → รหัสใหม่) |
| `cleanup_slips.php` | Cron script ลบไฟล์สลิปเก่า |

### includes/
| ไฟล์ | หน้าที่ |
|---|---|
| `db.php` | เชื่อมต่อ DB, timezone, self-healing migration, BASE_URL, โหลด `$store` |
| `csrf.php` | `csrf_token()` / `csrf_verify()` |
| `upload_helper.php` | `handle_image_upload()` — validate + ย้ายไฟล์รูปอย่างปลอดภัย |
| `send_email_otp.php` | ส่งอีเมล OTP ผ่าน PHPMailer/Gmail SMTP |
| `owner_flash.php` | แสดง toast จาก `$_SESSION['success_msg']`/`error_msg'` |
| `header_owner.php`, `nav_owner.php`, `footer_owner.php` | เลย์เอาต์ + JS กลางฝั่งเจ้าของร้าน (toast, confirm modal, polling ออเดอร์ใหม่) |
| `header_dinein.php`, `nav_dinein.php`, `footer_dinein.php` | เลย์เอาต์ + JS กลางฝั่งลูกค้า (badge สถานะร้าน, แท็บเมนู/ตะกร้า, polling อาหารพร้อม/บิลปิด) |

### owner/
| ไฟล์ | หน้าที่ |
|---|---|
| `auth_owner.php` | ด่านตรวจสิทธิ์กลางของทุกหน้า/API ฝั่งเจ้าของร้าน |
| `register_owner.php` | สมัครบัญชีเจ้าของร้าน (ครั้งเดียว) |
| `dashboard.php` | สถิติวันนี้ + สวิตช์เปิด/ปิดร้าน 3 ระดับ |
| `manage_orders.php` | คิวออเดอร์ pending/cooking |
| `manage_payments.php` | อนุมัติชำระเงิน (แยกทานที่ร้าน/กลับบ้าน) |
| `manage_menu.php` | CRUD เมนู + ผูกท็อปปิ้ง |
| `manage_categories.php` | CRUD หมวดหมู่ (หน้าเว็บ, เรียก `cat_api.php`) |
| `manage_toppings.php` | CRUD กลุ่ม/ตัวเลือกเสริม |
| `manage_stock.php` | ปรับสต็อกเมนู/ท็อปปิ้ง, เปิด/ปิดขาย, ติดดาวเมนูแนะนำ |
| `manage_tables.php` | CRUD โต๊ะ + สร้าง/ดาวน์โหลด QR Code |
| `reports.php` | รายงานยอดขายรายวัน + อันดับเมนู/ท็อปปิ้ง |
| `settings.php` | ตั้งค่าร้าน, โลโก้, ช่องทางรับเงิน |
| `print_receipt.php` | ใบเสร็จพิมพ์ (auto `window.print()`) |
| `print_daily_report.php` | สรุปยอดขายพิมพ์ |
| `api_save_menu.php` | บันทึกเมนู (แยกจาก `manage_menu.php`) |
| `api_toggle_featured.php` | สลับสถานะเมนูแนะนำ |
| `api_approve_payment.php` | อนุมัติชำระเงิน + คืนสถานะโต๊ะ (transaction) |
| `api_update_order_status.php` | เปลี่ยนสถานะออเดอร์ pending→cooking→served |
| `api_check_update.php` | นับออเดอร์ใหม่/รายการรอชำระ (ใช้ polling) |
| `update_status_ajax.php` | Toggle เปิด/ปิดร้าน, เมนู, ท็อปปิ้ง |
| `update_table_status_ajax.php` | รีเซ็ตสถานะโต๊ะเป็นว่างด้วยมือ |
| `cat_api.php` | CRUD หมวดหมู่ผ่าน AJAX |

### qr_table/
| ไฟล์ | หน้าที่ |
|---|---|
| `menu_dinein.php` | หน้าเมนู+ตะกร้ารวม, เลือกประเภทออเดอร์, ตรวจ qr_token |
| `join_table.php` | กรอกรหัสร่วมโต๊ะ 4 หลัก |
| `my_bill.php` | บิลรวมของโต๊ะ (เฉพาะทานที่ร้าน) |
| `api_check_bill.php` | โพลเช็คบิลปิดหรือยัง |
| `end_session.php` | เคลียร์ session โต๊ะ |
| `cart_dinein.php` | Redirect ไฟล์เก่า |

### member/
| ไฟล์ | หน้าที่ |
|---|---|
| `cart_action.php` | เพิ่ม/ลบ/ปรับจำนวนในตะกร้า (session) |
| `submit_order.php` | Validate + บันทึกออเดอร์จริงลง DB (transaction) |
| `order_detail.php` | ดูสถานะออเดอร์ของตัวเอง (กลับบ้าน) |
| `api_check_my_order.php` | โพลเช็คอาหารพร้อมเสิร์ฟ (ทานที่ร้าน) |

---

## 8. โค้ดสำคัญ (Key Code Snippets)

ส่วนนี้แปะโค้ดจริงเฉพาะจุดที่เป็นหัวใจของระบบ — ด่านตรวจสิทธิ์, การป้องกัน CSRF, การตรวจ QR/PIN, และ transaction ตอนสั่งอาหาร/รับเงิน

### 8.1 ด่านตรวจสิทธิ์กลางฝั่งเจ้าของร้าน — `owner/auth_owner.php`

ทุกหน้าและทุก API ฝั่งเจ้าของร้าน `require_once` ไฟล์นี้เป็นบรรทัดแรกๆ เสมอ ถ้าไม่ได้ล็อกอิน จะแยกพฤติกรรมตามว่าเรียกมาจากหน้าเว็บ (redirect ไป login) หรือ AJAX/API (ตอบ JSON 401):

```php
<?php
// Shared access check for every owner page and owner API.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (($_SESSION['role'] ?? '') !== 'owner' || empty($_SESSION['owner_id'])) {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_starts_with($script, 'api_') || str_ends_with($script, '_ajax.php') || str_ends_with($script, '_api.php')) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    header('Location: ../login.php');
    exit;
}

$owner_id = (int) $_SESSION['owner_id'];
```

### 8.2 CSRF token — `includes/csrf.php`

ใช้ `hash_equals()` เทียบ token กันปัญหา timing attack:

```php
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify($token) {
    if (!is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
```

### 8.3 กันสุ่มรหัสผ่าน (brute-force) — `login.php`

เช็กจำนวนครั้งที่ล็อกอินผิดของ username นั้นในฐานข้อมูล (ไม่ใช่ session) ภายใน 15 นาทีล่าสุด ก่อนจะยอมให้ลองอีกครั้ง:

```php
$fail_stmt = $conn->prepare("SELECT COUNT(*) AS fails, MAX(created_at) AS last_fail FROM login_attempts WHERE username = ? AND success = 0 AND created_at >= (NOW() - INTERVAL 15 MINUTE)");
$fail_stmt->bind_param("s", $username);
$fail_stmt->execute();
$fail_row = $fail_stmt->get_result()->fetch_assoc();
$recent_fails = intval($fail_row['fails']);

if ($recent_fails >= 5) {
    $wait_minutes = max(1, (int) ceil((900 - (time() - strtotime($fail_row['last_fail']))) / 60));
    $error = "เข้าสู่ระบบผิดพลาดหลายครั้งเกินไป ... กรุณารออีกประมาณ $wait_minutes นาที";
} else {
    // ตรวจ password_verify() ตามปกติ พร้อม auto-upgrade legacy hash เป็น password_hash()
}
```

### 8.4 ตรวจ QR token กันเดาเลขโต๊ะ — `qr_table/menu_dinein.php`

ตอนสแกน QR ครั้งแรก (โต๊ะเปลี่ยนจาก session เดิม) ต้องมีพารามิเตอร์ `t` ตรงกับ `qr_token` ที่สุ่มเก็บไว้ในฐานข้อมูลตอนสร้างโต๊ะเท่านั้น ถึงจะยอมผูก session:

```php
if ($is_new_table_scan) {
    $provided_token = $_GET['t'] ?? '';
    if (empty($row['qr_token']) || !hash_equals((string) $row['qr_token'], (string) $provided_token)) {
        echo "<script>alert('QR Code ไม่ถูกต้อง ...'); window.location='../index.php';</script>";
        exit;
    }
}
```

### 8.5 อัปโหลดรูปภาพอย่างปลอดภัย — `includes/upload_helper.php`

ตรวจ **MIME type จริงของเนื้อไฟล์** ด้วย `finfo` แทนการเชื่อนามสกุลไฟล์ที่ client ส่งมา และตั้งชื่อไฟล์ใหม่เองเสมอ:

```php
function handle_image_upload(array $file, string $targetDir, string $prefix): string|false
{
    if (empty($file['name']) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!isset($allowed_mimes[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return false;
    }

    $filename = $prefix . '_' . time() . '_' . uniqid() . '.' . $allowed_mimes[$mime];
    move_uploaded_file($file['tmp_name'], rtrim($targetDir, '/') . '/' . $filename);
    return $filename;
}
```

### 8.6 คำนวณราคา/สต็อกใหม่ตอนสั่งอาหารจริง — `member/submit_order.php`

**ไม่เชื่อ** ราคาที่ client ส่งมา หรือราคาที่แคชไว้ในตะกร้าตอนกดเพิ่ม — ดึงราคาปัจจุบันจากฐานข้อมูลมาคำนวณใหม่ทุกครั้ง:

```php
foreach ($_SESSION['cart'] as $item) {
    $item_id = intval($item['item_id']);
    $stmt_item->bind_param("i", $item_id);
    $stmt_item->execute();
    $item_row = $stmt_item->get_result()->fetch_assoc();

    // เมนูถูกลบ/ปิดขายไปแล้วระหว่างที่ลูกค้ากำลังสั่ง ให้ข้ามรายการนี้ไป
    if (!$item_row || intval($item_row['is_active']) !== 1) {
        continue;
    }

    $unit_price = floatval($item_row['price']);
    // ... วนตรวจท็อปปิ้งแต่ละตัวว่ายังผูกกับเมนูนี้และเปิดขายอยู่จริง ค่อยบวกราคาเพิ่ม
    $total_amount += $unit_price * $quantity;
}
```

ตอนตัดสต็อก ใช้เงื่อนไขใน `WHERE` แทนการ clamp ด้วย `GREATEST(0, ...)` เพื่อให้ MySQL ล็อกแถวกันแย่งสต็อกกันเอง (race condition) ได้ในตัว:

```php
$stmt_stock = $conn->prepare("UPDATE item SET stock_qty = stock_qty - ? WHERE item_id = ? AND use_stock = 1 AND stock_qty >= ?");
$stmt_stock->execute();
if ($stmt_stock->affected_rows === 0) {
    // อาจเพราะเมนูนี้ไม่ได้ติดตามสต็อก (ปกติ) หรือของไม่พอ (throw แล้ว rollback ทั้งออเดอร์)
}
```

### 8.7 อนุมัติชำระเงิน + คืนสถานะโต๊ะ (transaction) — `owner/api_approve_payment.php`

```php
$conn->begin_transaction();
try {
    $check = $conn->prepare('SELECT total_amount FROM orders WHERE order_id = ? FOR UPDATE');
    // ... insert/update ตาราง payment, ตั้ง orders.payment_status = 'paid'

    // เช็กว่าโต๊ะนี้จ่ายเงินหมดทุกออเดอร์แล้วหรือยัง ถ้าหมดแล้วคืนสถานะโต๊ะเป็นว่าง
    $unpaid_check = $conn->prepare("SELECT COUNT(*) as unpaid FROM orders WHERE table_id = ? AND payment_status = 'unpaid' AND order_status != 'canceled' AND order_id != ?");
    if (intval($unpaid_count) === 0) {
        $reset_table = $conn->prepare("UPDATE restauranttable SET status = 'available', join_code = NULL WHERE table_id = ?");
    }

    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
}
```

### 8.8 กันดูออเดอร์คนอื่น (IDOR) — `member/order_detail.php`

ผูกสิทธิ์การดูออเดอร์กลับบ้านด้วย `$_SESSION['guest_order_ids']` ที่ `submit_order.php` บันทึกไว้ตอนสั่งสำเร็จเท่านั้น — ไม่เชื่อ `table_id` เพียงอย่างเดียว เพราะลูกค้าโต๊ะเดียวกันจะเดา `order_id` ของกันและกันได้:

```php
$guest_order_ids = $_SESSION['guest_order_ids'] ?? [];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (in_array($order_id, $guest_order_ids, true)) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    // ...
} else {
    $order = null; // ไม่ใช่ออเดอร์ของ session นี้ ไม่ให้ดู
}
```

---

*เอกสารนี้จัดทำจากการอ่านซอร์สโค้ดทั้งหมดในโปรเจกต์ ณ วันที่จัดทำ หากมีการแก้ไขโค้ดภายหลัง เนื้อหาบางส่วนอาจไม่ตรงกับเวอร์ชันล่าสุด*
