<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/ApiHandler.php';

$student = ApiHandler::init();
$maSv = $student['ma_sv'];
$action = trim($_POST['action'] ?? 'update_phone');

require_once __DIR__ . '/../core/StudentRepository.php';
$repo = new StudentRepository();

switch ($action) {

    // === CẬP NHẬT SỐ ĐIỆN THOẠI ===
    case 'update_phone':
        $newPhone = trim($_POST['sdt'] ?? '');
        if (empty($newPhone)) {
            Response::error('Lỗi: Số điện thoại không được để trống.');
        }
        if (!preg_match('/^[0-9]{9,11}$/', $newPhone)) {
            Response::error('Lỗi: Số điện thoại không hợp lệ (Phải từ 9-11 chữ số).');
        }

        Security::checkSessionLock('update_profile', 10, $maSv);

        $success = $repo->updateStudentPhone($maSv, $newPhone);
        if ($success) {
            $_SESSION['student']['sdt'] = $newPhone;
            Response::success('Đã cập nhật Số điện thoại thành công!', ['new_sdt' => $newPhone]);
        } else {
            Response::error('Đã xảy ra lỗi khi cập nhật thông tin lên hệ thống. Vui lòng thử lại sau.');
        }
        break;

    // === CẬP NHẬT HỌ TÊN ===
    case 'update_name':
        $newName = trim($_POST['ho_ten'] ?? '');
        if (empty($newName)) {
            Response::error('Lỗi: Họ tên không được để trống.');
        }
        if (mb_strlen($newName) < 3 || mb_strlen($newName) > 100) {
            Response::error('Lỗi: Họ tên phải từ 3 đến 100 ký tự.');
        }
        // Chỉ cho phép chữ cái (bao gồm tiếng Việt), khoảng trắng
        if (!preg_match('/^[\p{L}\s]+$/u', $newName)) {
            Response::error('Lỗi: Họ tên chỉ được chứa chữ cái và khoảng trắng.');
        }

        Security::checkSessionLock('update_name', 15, $maSv);

        // Sanitize trước khi ghi vào Sheet
        $newName = Security::sanitizeForSheet($newName);

        $success = $repo->updateStudentName($maSv, $newName);
        if ($success) {
            $_SESSION['student']['ho_ten'] = $newName;
            Response::success('Đã cập nhật Họ tên thành công!', ['new_ho_ten' => $newName]);
        } else {
            Response::error('Đã xảy ra lỗi khi cập nhật thông tin lên hệ thống. Vui lòng thử lại sau.');
        }
        break;

    // === CẬP NHẬT NGÀY SINH ===
    case 'update_dob':
        $newDob = trim($_POST['ngay_sinh'] ?? '');
        if (empty($newDob)) {
            Response::error('Lỗi: Ngày sinh không được để trống.');
        }
        // Chuẩn hóa định dạng ngày sinh
        $normalizedDob = Security::normalizeDate($newDob);
        // Kiểm tra định dạng dd/mm/yyyy
        if (!preg_match('#^\d{2}/\d{2}/\d{4}$#', $normalizedDob)) {
            Response::error('Lỗi: Ngày sinh không hợp lệ. Vui lòng nhập theo định dạng dd/mm/yyyy.');
        }
        // Kiểm tra ngày hợp lệ
        $parts = explode('/', $normalizedDob);
        if (!checkdate((int)$parts[1], (int)$parts[0], (int)$parts[2])) {
            Response::error('Lỗi: Ngày sinh không tồn tại. Vui lòng kiểm tra lại.');
        }

        Security::checkSessionLock('update_dob', 15, $maSv);

        $success = $repo->updateStudentDob($maSv, $normalizedDob);
        if ($success) {
            $_SESSION['student']['ngay_sinh'] = $normalizedDob;
            Response::success('Đã cập nhật Ngày sinh thành công!', ['new_ngay_sinh' => $normalizedDob]);
        } else {
            Response::error('Đã xảy ra lỗi khi cập nhật thông tin lên hệ thống. Vui lòng thử lại sau.');
        }
        break;

    default:
        Response::error('Hành động không hợp lệ.');
}
