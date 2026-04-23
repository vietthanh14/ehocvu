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

// Tự động dọn file rate_limit cũ hơn 1 giờ (xác suất 1/10 request để không ảnh hưởng hiệu năng)
if (mt_rand(1, 10) === 1) {
    $oldFiles = glob(__DIR__ . '/cache/rate_limit_*.json');
    foreach ($oldFiles as $f) {
        if (filemtime($f) < time() - 3600) {
            @unlink($f);
        }
    }
}

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
$ngaySinh = trim($_POST['ngay_sinh'] ?? '');

if (empty($maSv) || empty($ngaySinh)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng cung cấp đầy đủ Mã Sinh Viên và Ngày sinh.']);
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

    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// Hàm chuẩn hóa ngày sinh (05/08/2002 và 5/8/2002 sẽ được coi là giống nhau)
function normalizeDate($dateStr) {
    $clean = str_replace(['-', '.', ' '], '/', trim($dateStr));
    $parts = explode('/', $clean);
    if (count($parts) === 3) {
        $d = str_pad((int)$parts[0], 2, '0', STR_PAD_LEFT);
        $m = str_pad((int)$parts[1], 2, '0', STR_PAD_LEFT);
        $y = $parts[2];
        if (strlen($y) == 2) {
            $y = ($y > 30 ? '19' : '20') . $y; // Đoán năm nếu chỉ có 2 số cuối
        }
        return "$d/$m/$y";
    }
    return $dateStr;
}

$dbNgaySinh = $studentInfo['ngay_sinh'] ?? '';

if (normalizeDate($ngaySinh) !== normalizeDate($dbNgaySinh)) {
    // Tăng số lần sai vì sai "mật khẩu"
    $attempts++;
    if ($attempts >= 10) {
        $lockoutTime = time() + 300; // Khóa 5 phút
        $attempts = 0;
        $msg = 'Bạn đã nhập sai quá 10 lần. IP bị tạm khóa 5 phút.';
    } else {
        $msg = 'Ngày sinh (Mật khẩu) không đúng. Vui lòng kiểm tra lại.';
    }
    file_put_contents($rateLimitFile, json_encode(['attempts' => $attempts, 'lockoutTime' => $lockoutTime]), LOCK_EX);

    echo json_encode(['success' => false, 'message' => $msg]);
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
