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
}
