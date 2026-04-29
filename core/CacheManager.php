<?php
class CacheManager {
    private string $cacheDir;

    public function __construct(string $cacheDir = __DIR__ . '/../cache') {
        $this->cacheDir = rtrim($cacheDir, '/');
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
        
        // Throttled Cleanup: Tự động dọn rác tối đa 1 lần / 24 giờ
        // Dùng file lock thay vì mt_rand để tránh bottleneck
        $lockFile = $this->cacheDir . '/.last_cleanup';
        if (!file_exists($lockFile) || (time() - filemtime($lockFile)) > 86400) {
            @touch($lockFile);
            $this->cleanup();
        }
    }

    /**
     * Đọc cache. Trả về dữ liệu nếu cache còn hạn, null nếu hết hạn/chưa có.
     */
    public function get(string $key, int $ttl): ?array {
        $file = $this->cacheDir . '/' . $key . '.json';
        if (!file_exists($file)) {
            return null;
        }

        $mtime = filemtime($file);
        if ((time() - $mtime) > $ttl) {
            return null; // Cache hết hạn
        }

        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Ghi cache.
     */
    public function set(string $key, array $data): void {
        $file = $this->cacheDir . '/' . $key . '.json';
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Xóa cache theo key.
     */
    public function clear(string $key): void {
        $file = $this->cacheDir . '/' . $key . '.json';
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Xóa tất cả các file cache (Dùng cho clearcache.php)
     */
    public function clearAll(): int {
        $count = 0;
        $files = glob($this->cacheDir . '/*.json');
        foreach ($files as $file) {
            @unlink($file);
            $count++;
        }
        return $count;
    }

    /**
     * Dọn dẹp các file cache quá cũ (hơn 24 giờ) để tránh đầy ổ cứng.
     */
    public function cleanup(): void {
        $files = glob($this->cacheDir . '/*.json');
        $now = time();
        foreach ($files as $f) {
            // Xóa file cache nếu đã tồn tại hơn 24 giờ (thời gian sống tối đa của cache là 24h đối với courses_catalog)
            if (filemtime($f) < $now - 86400) {
                @unlink($f);
            }
        }
    }
}
