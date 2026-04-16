<?php
require_once __DIR__ . '/config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

// Chỉ chấp nhận POST để tránh mã SV lộ trong URL/logs
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Chỉ hỗ trợ phương thức POST.']);
    exit;
}

require_once __DIR__ . '/GoogleSheetService.php';

$maSv = trim($_POST['ma_sv'] ?? '');

if (empty($maSv)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng cung cấp Mã Sinh Viên.']);
    exit;
}

$service = GoogleSheetService::getInstance();

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

if ($studentInfo === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi kỹ thuật: Không thể kết nối với dịch vụ của Google hoặc Mã SV không tồn tại (Gợi ý: Lỗi do đồng hồ máy tính sai năm 2026 khiến Google chặn).'
    ]);
    exit;
}

// Lưu session bảo mật
session_regenerate_id(true);
$_SESSION['student'] = $studentInfo;

echo json_encode([
    'success' => true,
    'data' => $studentInfo
]);
