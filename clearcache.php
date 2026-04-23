<?php
/**
 * Xóa toàn bộ cache của hệ thống.
 * Yêu cầu đăng nhập để sử dụng.
 */
require_once __DIR__ . '/config.php';
session_start();

if (!isset($_SESSION['student'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập trước.']);
    exit;
}

require_once __DIR__ . '/core/CacheManager.php';
$cacheManager = new CacheManager();
$count = $cacheManager->clearAll();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'message' => "Đã xóa $count file cache.",
    'time'    => date('d/m/Y H:i:s')
]);
