<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/ApiHandler.php';

$student = ApiHandler::init();
$maSv = $student['ma_sv'];
$newPhone = trim($_POST['sdt'] ?? '');

if (empty($newPhone)) {
    Response::error('Lỗi: Số điện thoại không được để trống.');
}

if (!preg_match('/^[0-9]{9,11}$/', $newPhone)) {
    Response::error('Lỗi: Số điện thoại không hợp lệ (Phải từ 9-11 chữ số).');
}

Security::checkSessionLock('update_profile', 10, $maSv);

require_once __DIR__ . '/../core/GoogleSheetService.php';
$service = GoogleSheetService::getInstance();

$success = $service->updateStudentPhone($maSv, $newPhone);

if ($success) {
    // Cập nhật lại session
    $_SESSION['student']['sdt'] = $newPhone;
    
    Response::success('Đã cập nhật Số điện thoại thành công!', ['new_sdt' => $newPhone]);
} else {
    Response::error('Đã xảy ra lỗi khi cập nhật thông tin lên hệ thống. Vui lòng thử lại sau.');
}
