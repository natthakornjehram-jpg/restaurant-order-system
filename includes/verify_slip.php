<?php
// includes/verify_slip.php
// ตรวจสอบสลิปโอนเงินอัตโนมัติผ่าน SlipOK API (เช็คยอดเงิน, บัญชีผู้รับตรงกับร้านไหม, สลิปซ้ำไหม)
// ตั้งค่าที่ includes/slipok_config.php - ถ้ายังไม่ได้ตั้งค่า (branch_id/api_key ว่าง) จะข้ามการตรวจสอบไปก่อน
// (ให้ออเดอร์ผ่านได้ปกติเหมือนเดิม) เพื่อไม่ให้ระบบสั่งอาหารพังทั้งหมดก่อนที่เจ้าของร้านจะสมัคร SlipOK เสร็จ

/**
 * ส่งรูปสลิปไปตรวจกับ SlipOK พร้อมยอดเงินที่คาดไว้ (ให้ SlipOK เช็คยอดให้อัตโนมัติผ่านพารามิเตอร์ amount)
 * และ log=true เพื่อเปิดใช้การเช็คบัญชีผู้รับ + กันสลิปซ้ำของฝั่ง SlipOK เอง
 *
 * @return array{success: bool, skipped: bool, message: ?string, trans_ref: ?string}
 */
function verify_slip_with_slipok(string $file_path, float $expected_amount): array
{
    $config = require __DIR__ . '/slipok_config.php';
    $branch_id = trim($config['branch_id'] ?? '');
    $api_key = trim($config['api_key'] ?? '');

    if ($branch_id === '' || $api_key === '') {
        return ['success' => true, 'skipped' => true, 'message' => null, 'trans_ref' => null];
    }

    if (!is_file($file_path)) {
        return ['success' => false, 'skipped' => false, 'message' => 'ไม่พบไฟล์สลิปที่อัปโหลด กรุณาลองแนบสลิปใหม่อีกครั้ง', 'trans_ref' => null];
    }

    $ch = curl_init("https://api.slipok.com/api/line/apikey/{$branch_id}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'files' => new CURLFile($file_path),
            'amount' => $expected_amount,
            'log' => 'true', // เปิดให้ SlipOK เช็คบัญชีผู้รับ + กันสลิปซ้ำ (ไม่ใส่ค่านี้จะไม่เช็ค 2 อย่างนี้ให้)
        ],
        CURLOPT_HTTPHEADER => [
            'x-authorization: ' . $api_key,
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('verify_slip_with_slipok: curl error: ' . $curl_error);
        return [
            'success' => false,
            'skipped' => false,
            'message' => 'ระบบตรวจสอบสลิปขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้งในอีกสักครู่ครับ',
            'trans_ref' => null,
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log('verify_slip_with_slipok: invalid JSON response: ' . $response);
        return [
            'success' => false,
            'skipped' => false,
            'message' => 'ระบบตรวจสอบสลิปขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้งในอีกสักครู่ครับ',
            'trans_ref' => null,
        ];
    }

    $data = $decoded['data'] ?? [];

    if (!empty($decoded['success'])) {
        return [
            'success' => true,
            'skipped' => false,
            'message' => null,
            'trans_ref' => $data['transRef'] ?? null,
        ];
    }

    // ข้อความจาก SlipOK เป็นภาษาไทยอยู่แล้วในหลายกรณี ใช้ตรงๆ ได้เลย ถ้าไม่มีค่อย fallback ตามรหัสที่รู้จัก
    $code = $decoded['code'] ?? ($data['code'] ?? null);
    $api_message = $decoded['message'] ?? ($data['message'] ?? null);

    $fallback_messages = [
        1012 => 'สลิปนี้เคยถูกใช้ไปแล้ว (สลิปซ้ำ) กรุณาตรวจสอบอีกครั้ง',
        1013 => 'ยอดเงินในสลิปไม่ตรงกับยอดออเดอร์ กรุณาตรวจสอบอีกครั้ง',
        1014 => 'บัญชีผู้รับในสลิปไม่ตรงกับบัญชีของร้าน กรุณาโอนเข้าบัญชี/พร้อมเพย์ของร้านที่แจ้งไว้เท่านั้น',
        1010 => 'สลิปนี้ยังไม่เข้าระบบธนาคาร กรุณารอสักครู่แล้วลองแนบสลิปใหม่อีกครั้ง',
    ];

    $message = $api_message ?: ($fallback_messages[$code] ?? 'ไม่สามารถตรวจสอบสลิปนี้ได้ กรุณาตรวจสอบรูปสลิปแล้วลองใหม่อีกครั้ง');

    return ['success' => false, 'skipped' => false, 'message' => $message, 'trans_ref' => null];
}
