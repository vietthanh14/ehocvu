<?php
class CacheManager {
    private string $cacheDir;

    public function __construct(string $cacheDir = __DIR__ . '/../cache') {
        $this->cacheDir = rtrim($cacheDir, '/');
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
        
        // Ghi chú: Throttled Cleanup đồng bộ đã được gỡ bỏ để tránh làm treo tiến trình của người dùng.
        // Thay vào đó, hãy đảm bảo bạn thiết lập cronjob chạy file `cron_cleanup.php` mỗi ngày.
    }

    /**
     * Đọc cache với phương pháp Stale-while-revalidate (Soft Lock).
     * Ngăn chặn hiệu ứng Cache Stampede bằng cách trả về dữ liệu cũ (stale) cho các request đến sau
     * trong lúc có 1 request đang lấy dữ liệu mới từ Google Sheets.
     */
    public function get(string $key, int $ttl): ?array {
        $file = $this->cacheDir . '/' . $key . '.json';
        
        if (!file_exists($file)) {
            // Không có cache nào cả, bắt buộc đi lấy dữ liệu
            return null;
        }

        $mtime = filemtime($file);
        $isExpired = (time() - $mtime) > $ttl;

        if (!$isExpired) {
            // Cache còn hạn, dùng bình thường
            $data = json_decode(file_get_contents($file), true);
            return is_array($data) ? $data : null;
        }

        // CACHE HẾT HẠN - Kích hoạt Soft Lock
        $lockFile = $this->cacheDir . '/' . $key . '.lock';
        
        // Nếu đã có 1 tiến trình khác tạo file lock trong vòng 60 giây qua
        // -> Có người đang đi lấy data mới, chúng ta cứ trả về data cũ (stale) cho sinh viên này để không bị treo.
        if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 60) {
            $data = json_decode(file_get_contents($file), true);
            return is_array($data) ? $data : null;
        }

        // Tiến trình này được "chọn" để đi lấy dữ liệu mới từ API.
        // Tạo file lock cờ hiệu (sống tối đa 60s chờ Google API).
        @touch($lockFile);
        
        return null; // Trả về null ép GoogleSheetClient gọi API lấy dữ liệu mới
    }

    /**
     * Ghi cache. Xóa luôn file lock nếu có.
     */
    public function set(string $key, array $data): void {
        $file = $this->cacheDir . '/' . $key . '.json';
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
        
        // Dữ liệu mới đã ghi xong, xóa cờ hiệu (lock)
        $lockFile = $this->cacheDir . '/' . $key . '.lock';
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
    }

    /**
     * Xóa cache theo key (thường gọi khi có submit form mới).
     */
    public function clear(string $key): void {
        $file = $this->cacheDir . '/' . $key . '.json';
        $lockFile = $this->cacheDir . '/' . $key . '.lock';
        
        if (file_exists($file)) {
            @unlink($file);
        }
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
    }

    /**
     * Xóa tất cả các file cache (Dùng cho clearcache.php)
     */
    public function clearAll(): int {
        $count = 0;
        $files = glob($this->cacheDir . '/*.{json,lock}', GLOB_BRACE);
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Dọn dẹp các file rác cũ. Thường được gọi bởi `cron_cleanup.php`.
     */
    public function cleanup(): void {
        $files = glob($this->cacheDir . '/*.{json,lock}', GLOB_BRACE);
        $now = time();
        if ($files) {
            foreach ($files as $f) {
                // Xóa file nếu đã tồn tại hơn 24 giờ
                if (filemtime($f) < $now - 86400) {
                    @unlink($f);
                }
            }
        }
    }
}
