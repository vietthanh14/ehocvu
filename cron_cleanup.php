<?php
/**
 * Script dọn dẹp Cache (Garbage Collection)
 * Có thể thiết lập chạy qua CronJob (ví dụ mỗi đêm 2h sáng)
 * Command: php /path/to/cron_cleanup.php
 * Hoặc truy cập qua URL: http://domain.com/cron_cleanup.php
 */

require_once __DIR__ . '/core/CacheManager.php';

$cacheManager = new CacheManager(__DIR__ . '/cache');

// Gọi hàm cleanup để xóa các file JSON cũ hơn 24 giờ
$cacheManager->cleanup();

echo "Cache cleanup completed at " . date('Y-m-d H:i:s');
