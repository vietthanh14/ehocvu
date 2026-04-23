<?php
require_once __DIR__ . '/config.php';
session_start();
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Security.php';

Security::requirePost();

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
    Response::error("Hệ thống phát hiện bất thường: IP bị khóa tạm thời $remain phút do nhập sai quá nhiều lần.");
}

$maSv = trim($_POST['ma_sv'] ?? '');
$ngaySinh = trim($_POST['ngay_sinh'] ?? '');

if (empty($maSv) || empty($ngaySinh)) {
    Response::error('Vui lòng cung cấp đầy đủ Mã Sinh Viên và Ngày sinh.');
}

$service = GoogleSheetService::getInstance();

// 1. Kiểm tra dính án kỉ luật
try {
    if ($service->isExpelled($maSv)) {
        Response::error('Sinh viên đang trong danh sách bị xóa tên hoặc buộc thôi học! Không thể đăng ký thủ tục.');
    }

    // 2. Lấy thông tin
    $studentInfo = $service->getStudentInfo($maSv);
} catch (Exception $e) {
    Response::error('Lỗi kết nối Server: Google API từ chối quyền truy cập hoặc cấu hình sai (Chi tiết: ' . $e->getMessage() . ')');
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
    Response::error($msg);
}

$dbNgaySinh = $studentInfo['ngay_sinh'] ?? '';

if (Security::normalizeDate($ngaySinh) !== Security::normalizeDate($dbNgaySinh)) {
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
    Response::error($msg);
}

// Xóa bộ đếm nếu đăng nhập thành công
if (file_exists($rateLimitFile)) {
    @unlink($rateLimitFile);
}

// Lưu session bảo mật
$_SESSION['student'] = $studentInfo;

Response::success('', ['data' => $studentInfo]);
