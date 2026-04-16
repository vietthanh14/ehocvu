<?php
require_once __DIR__ . '/config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

if (!isset($_SESSION['student'])) {
    echo json_encode(['success' => false, 'message' => 'Phiên đăng nhập đã hết hạn.']);
    exit;
}

$student = $_SESSION['student'];
$maSv = $student['ma_sv'];
$newPhone = trim($_POST['sdt'] ?? '');

if (empty($newPhone)) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: Số điện thoại không được để trống.']);
    exit;
}

if (!preg_match('/^[0-9]{9,11}$/', $newPhone)) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: Số điện thoại không hợp lệ (Phải từ 9-11 chữ số).']);
    exit;
}

// Kiểm tra chống spam (Lock 10s)
$submitKey = 'last_update_profile_' . md5($maSv);
$now = time();
if (isset($_SESSION[$submitKey]) && ($now - $_SESSION[$submitKey]) < 10) {
    echo json_encode([
        'success' => false,
        'message' => 'Bạn thao tác quá nhanh. Vui lòng chờ vài giây.'
    ]);
    exit;
}

require_once __DIR__ . '/GoogleSheetService.php';
$service = GoogleSheetService::getInstance();

$success = $service->updateStudentPhone($maSv, $newPhone);

if ($success) {
    // Cập nhật lại session
    $_SESSION['student']['sdt'] = $newPhone;
    $_SESSION[$submitKey] = $now;
    
    echo json_encode([
        'success' => true,
        'message' => 'Đã cập nhật Số điện thoại thành công!',
        'new_sdt' => $newPhone
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Đã xảy ra lỗi khi cập nhật thông tin lên hệ thống. Vui lòng thử lại sau.'
    ]);
}
