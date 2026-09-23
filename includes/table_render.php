<?php
// includes/table_render.php
// เรนเดอร์การ์ดโต๊ะ 1 ใบ + modal แก้ไขชื่อที่ผูกกับโต๊ะนั้น ใช้ร่วมกันทั้งตอนโหลดหน้า owner/manage_tables.php
// ปกติ และตอนตอบกลับ JSON หลังเพิ่ม/แก้ไขโต๊ะแบบ AJAX (กันไม่ให้ต้องเขียน HTML โครงสร้างเดียวกันซ้ำสองที่)
// แยกเป็นการ์ดกับ modal คนละฟังก์ชัน เพราะฝั่ง JS ต้องอัปเดตสองตำแหน่งใน DOM แยกจากกัน (คนละ element ไม่ได้อยู่ซ้อนกัน)
function render_owner_table_card($t) {
    $is_busy = ($t['status'] !== 'available');
    $t_id = $t['table_id'];
    ob_start();
    ?>
    <div class="col-6 col-md-4 col-lg-2" id="card-table-<?= $t_id ?>">
        <div class="card h-100 border-0 shadow-sm rounded-4 text-center p-3 <?= $is_busy ? 'bg-danger text-white' : 'bg-white text-dark' ?> status-card">
            <div class="card-body p-0">
                <div class="small opacity-75 mb-1">TABLE</div>
                <h2 class="fw-bold mb-2"><?= htmlspecialchars($t['table_number']) ?></h2>

                <div class="mb-3 status-badge">
                    <?php if ($is_busy): ?>
                        <span class="badge rounded-pill bg-white text-danger px-3">ไม่ว่าง</span>
                    <?php else: ?>
                        <span class="badge rounded-pill bg-success px-3">ว่าง</span>
                    <?php endif; ?>
                </div>

                <div class="d-grid gap-2 btn-area">
                    <?php if ($is_busy): ?>
                        <button type="button" onclick="ajaxCheckout(<?= $t_id ?>)" class="btn btn-sm btn-light text-danger fw-bold rounded-pill shadow-sm">
                            <i class="bi bi-cash-stack"></i> เช็คบิล
                        </button>
                    <?php else: ?>
                        <button class="btn btn-sm btn-outline-primary rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#editTable<?= $t_id ?>">
                            <i class="bi bi-pencil-square"></i> แก้ไขชื่อ
                        </button>
                        <button type="button" onclick="showQR(<?= htmlspecialchars(json_encode($t['table_number']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($t['qr_token'] ?? ''), ENT_QUOTES) ?>)" class="btn btn-sm btn-outline-dark rounded-pill shadow-sm">
                            <i class="bi bi-qr-code"></i> พิมพ์ QR
                        </button>
                    <?php endif; ?>

                    <form method="POST" action="manage_tables.php" class="d-inline table-mini-form" data-table-action="delete">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="delete_table" value="<?= $t_id ?>">
                        <button type="submit" class="btn btn-link btn-sm text-<?= $is_busy ? 'white' : 'danger' ?> text-decoration-none x-small">ลบโต๊ะ</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function render_owner_table_edit_modal($t) {
    $t_id = $t['table_id'];
    ob_start();
    ?>
    <div class="modal fade text-dark" id="editTable<?= $t_id ?>" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <form class="modal-content border-0 rounded-4 shadow table-mini-form" data-table-action="edit" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="modal-header border-0">
                    <h5 class="fw-bold">แก้ไขชื่อโต๊ะ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-0">
                    <div class="edit-table-error"></div>
                    <input type="hidden" name="table_id" value="<?= $t_id ?>">
                    <label class="small fw-bold mb-2">เลขโต๊ะใหม่</label>
                    <input type="text" name="new_table_number" class="form-control rounded-3" value="<?= htmlspecialchars($t['table_number']) ?>" required>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" name="edit_table" class="btn btn-warning w-100 rounded-pill py-2 fw-bold">อัปเดตชื่อโต๊ะ</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
