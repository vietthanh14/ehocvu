<?php
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Security.php';

class ApiHandler {
    /**
     * Khởi tạo API cơ bản: Yêu cầu POST, yêu cầu Auth, trả về thông tin sinh viên
     * @return array Dữ liệu sinh viên từ session
     */
    public static function init(): array {
        Security::requirePost();
        return Security::requireAuth();
    }

    /**
     * Validate file tải lên
     * @param array $file $_FILES['input_name']
     * @param int $maxSizeMB Kích thước tối đa (MB)
     * @param array|null $allowedMimeTypes Danh sách MIME hợp lệ
     * @return void Sẽ ném ra Response::error() nếu file không hợp lệ
     */
    public static function validateUploadFile(array $file, int $maxSizeMB = 10, ?array $allowedMimeTypes = null): void {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('Lỗi khi tải file lên. Mã lỗi: ' . $file['error']);
        }

        $maxBytes = $maxSizeMB * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            Response::error("File quá lớn. Vui lòng chọn file nhỏ hơn {$maxSizeMB}MB.");
        }

        if ($allowedMimeTypes === null) {
            $allowedMimeTypes = [
                'application/pdf', 
                'image/jpeg', 
                'image/png', 
                'image/jpg',
                'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($realType, $allowedMimeTypes)) {
            Response::error('Định dạng file không hợp lệ. Chỉ chấp nhận PDF, JPG, PNG, DOC, DOCX.');
        }
    }
}
