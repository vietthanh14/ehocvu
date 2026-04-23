<?php
class CacheManager {
    private string $cacheDir;

    public function __construct(string $cacheDir = __DIR__ . '/cache') {
        $this->cacheDir = rtrim($cacheDir, '/');
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
        
        // Cơ chế dọn rác ngẫu nhiên (10%)
        if (mt_rand(1, 10) === 1) {
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
     * Xóa toàn bộ cache liên quan tới request (sau khi submit).
     */
    public function invalidateRequestsCache(): void {
        // Xóa cache bảo lưu
        $files = glob($this->cacheDir . '/requests_*.json');
        foreach ($files as $f) {
            @unlink($f);
        }
        // Xóa cache hủy học phần
        $hhpFile = $this->cacheDir . '/hhp_requests_all.json';
        if (file_exists($hhpFile)) {
            @unlink($hhpFile);
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
     * Dọn dẹp các file cache quá cũ (hơn 1 giờ).
     */
    private function cleanup(): void {
        $files = glob($this->cacheDir . '/*.json');
        $now = time();
        foreach ($files as $f) {
            // Tránh xóa các file rate_limit đang hoạt động (lockoutTime), nhưng xóa file cache data thông thường
            if (filemtime($f) < $now - 3600) {
                @unlink($f);
            }
        }
    }
}
