<?php
/**
 * Xóa toàn bộ cache của hệ thống.
 * Yêu cầu đăng nhập để sử dụng.
 */
session_start();

if (!isset($_SESSION['student'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập trước.']);
    exit;
}

$cacheDir = __DIR__ . '/cache';
$count = 0;

if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '/*.json');
    foreach ($files as $file) {
        unlink($file);
        $count++;
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'message' => "Đã xóa $count file cache.",
    'time'    => date('d/m/Y H:i:s')
]);
