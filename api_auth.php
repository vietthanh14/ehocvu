<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/GoogleSheetService.php';

// Chấp nhận cả GET (backward compat) và POST
$maSv = trim($_POST['ma_sv'] ?? $_GET['ma_sv'] ?? '');

if (empty($maSv)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng cung cấp Mã Sinh Viên.']);
    exit;
}

$service = new GoogleSheetService();

// 1. Kiểm tra dính án kỉ luật
if ($service->isExpelled($maSv)) {
    echo json_encode([
        'success' => false,
        'message' => 'Sinh viên đang trong danh sách bị xóa tên hoặc buộc thôi học! Không thể đăng ký thủ tục.'
    ]);
    exit;
}

// 2. Lấy thông tin
$studentInfo = $service->getStudentInfo($maSv);

if (!$studentInfo) {
    echo json_encode([
        'success' => false,
        'message' => 'Mã Sinh Viên không tồn tại trong hệ thống.'
    ]);
    exit;
}

// Lưu session
$_SESSION['student'] = $studentInfo;

echo json_encode([
    'success' => true,
    'data' => $studentInfo
]);
