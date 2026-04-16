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

// === Rate Limiting (Chống Brute Force / Auto Submissions) ===
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ipHash = md5($ip);
$rateLimitFile = __DIR__ . '/cache/rate_limit_' . $ipHash . '.json';
$attempts = 0;
$lockoutTime = 0;

if (file_exists($rateLimitFile)) {
    $rData = json_decode(file_get_contents($rateLimitFile), true);
    if ($rData) {
        $attempts = $rData['attempts'] ?? 0;
        $lockoutTime = $rData['lockoutTime'] ?? 0;
    }
}

if ($lockoutTime > time()) {
    $remain = ceil(($lockoutTime - time()) / 60);
    echo json_encode(['success' => false, 'message' => "Hệ thống phát hiện bất thường: IP bị khóa tạm thời $remain phút do nhập sai quá nhiều lần."]);
    exit;
}

$maSv = trim($_POST['ma_sv'] ?? '');

if (empty($maSv)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng cung cấp Mã Sinh Viên.']);
    exit;
}

$service = GoogleSheetService::getInstance();

// 1. Kiểm tra dính án kỉ luật
try {
    if ($service->isExpelled($maSv)) {
        echo json_encode([
            'success' => false,
            'message' => 'Sinh viên đang trong danh sách bị xóa tên hoặc buộc thôi học! Không thể đăng ký thủ tục.'
        ]);
        exit;
    }

    // 2. Lấy thông tin
    $studentInfo = $service->getStudentInfo($maSv);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi kết nối Server: Google API từ chối quyền truy cập hoặc cấu hình sai (Chi tiết: ' . $e->getMessage() . ')'
    ]);
    exit;
}

if ($studentInfo === null) {
    // Tăng số lần sai
    $attempts++;
    if ($attempts >= 10) {
        $lockoutTime = time() + 300; // Khóa 5 phút
        $attempts = 0;
        $msg = 'Bạn đã nhập sai quá 10 lần. IP bị tạm khóa 5 phút.';
    } else {
        $msg = 'Mã Sinh viên không tồn tại trong hệ thống. Vui lòng kiểm tra lại.';
    }
    file_put_contents($rateLimitFile, json_encode(['attempts' => $attempts, 'lockoutTime' => $lockoutTime]), LOCK_EX);

    echo json_encode([
        'success' => false,
        'message' => $msg
    ]);
    exit;
}

// Xóa bộ đếm nếu đăng nhập thành công
if (file_exists($rateLimitFile)) {
    @unlink($rateLimitFile);
}

// Lưu session bảo mật
$_SESSION['student'] = $studentInfo;

echo json_encode([
    'success' => true,
    'data' => $studentInfo
]);
