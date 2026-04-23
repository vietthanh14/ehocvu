<?php
require_once __DIR__ . '/Response.php';

class Security {
    /**
     * Bắt buộc phương thức POST
     */
    public static function requirePost(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Chỉ hỗ trợ phương thức POST.');
        }
    }

    /**
     * Kiểm tra quyền truy cập (Đăng nhập)
     */
    public static function requireAuth(): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['student'])) {
            Response::error('Phiên đăng nhập đã hết hạn.');
        }
        return $_SESSION['student'];
    }

    /**
     * Khóa phiên (Session Lock) chống Spam
     */
    public static function checkSessionLock(string $actionKey, int $cooldownSeconds, string $studentId): void {
        $submitKey = 'lock_' . $actionKey . '_' . md5($studentId);
        $now = time();
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION[$submitKey]) && ($now - $_SESSION[$submitKey]) < $cooldownSeconds) {
            Response::error("Hệ thống đang xử lý tác vụ của bạn. Vui lòng không thao tác liên tục.");
        }
        
        // Khóa ngay lập tức để block các request concurrent (double-click)
        $_SESSION[$submitKey] = $now;
    }

    /**
     * Chuẩn hóa định dạng ngày sinh
     */
    public static function normalizeDate(string $dateStr): string {
        $clean = str_replace(['-', '.', ' '], '/', trim($dateStr));
        $parts = explode('/', $clean);
        if (count($parts) === 3) {
            $d = str_pad((int)$parts[0], 2, '0', STR_PAD_LEFT);
            $m = str_pad((int)$parts[1], 2, '0', STR_PAD_LEFT);
            $y = $parts[2];
            if (strlen($y) == 2) {
                $y = ($y > 30 ? '19' : '20') . $y;
            }
            return "$d/$m/$y";
        }
        return $dateStr;
    }

    // =========================================
    //  CSRF TOKEN PROTECTION
    // =========================================

    /**
     * Tạo CSRF Token và lưu vào session
     */
    public static function generateCsrfToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Xác thực CSRF Token từ request POST
     */
    public static function validateCsrfToken(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            Response::error('Yêu cầu không hợp lệ (CSRF). Vui lòng tải lại trang và thử lại.');
        }
    }

    // =========================================
    //  FORMULA INJECTION PROTECTION
    // =========================================

    /**
     * Chống Formula Injection khi ghi dữ liệu vào Google Sheets.
     * Thêm dấu nháy đơn trước các ký tự nguy hiểm mà Google Sheets hiểu là công thức.
     */
    public static function sanitizeForSheet(string $value): string {
        $value = trim($value);
        if ($value === '') return $value;
        $dangerousChars = ['=', '+', '-', '@', "\t", "\r", "\n"];
        if (in_array(mb_substr($value, 0, 1), $dangerousChars, true)) {
            $value = "'" . $value;
        }
        return $value;
    }

    /**
     * Sanitize một mảng dữ liệu trước khi ghi vào Google Sheet
     * @param array $data Mảng key => value
     * @param array $keysToSanitize Chỉ sanitize các key cụ thể (user input)
     */
    public static function sanitizeArrayForSheet(array $data, array $keysToSanitize): array {
        foreach ($keysToSanitize as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = self::sanitizeForSheet($data[$key]);
            }
        }
        return $data;
    }
}
