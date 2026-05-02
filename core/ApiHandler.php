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
        $student = Security::requireAuth();
        Security::validateCsrfToken();
        return $student;
    }

    /**
     * Validate file tải lên
     * @param array $file $_FILES['input_name']
     * @param int $maxSizeMB Kích thước tối đa (MB)
     * @param array|null $allowedMimeTypes Danh sách MIME hợp lệ
     * @return void Sẽ ném ra Response::error() nếu file không hợp lệ
     */
    public static function validateUploadFile(array $file, int $maxSizeMB = 15, ?array $allowedMimeTypes = null): void {
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

    /**
     * Xử lý upload file lên Google Drive (dùng chung cho mọi API submit)
     * 
     * @param string $fieldName Tên field trong $_FILES (VD: 'file_don', 'file_minh_chung')
     * @param string $maSv Mã sinh viên
     * @param bool $required Có bắt buộc phải upload không
     * @return string URL file trên Google Drive, hoặc '' nếu không upload
     */
    public static function handleFileUpload(string $fieldName, string $maSv, bool $required = false): string {
        require_once __DIR__ . '/DriveUploader.php';

        $hasFile = isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK;

        if (!$hasFile) {
            if ($required) {
                // Kiểm tra lỗi upload cụ thể (không phải "không chọn file")
                if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] !== UPLOAD_ERR_NO_FILE) {
                    Response::error('Lỗi khi tải file lên. Mã lỗi: ' . $_FILES[$fieldName]['error']);
                }
                Response::error('Vui lòng đính kèm file đơn đăng ký.');
            }
            return '';
        }

        $file = $_FILES[$fieldName];
        self::validateUploadFile($file);

        // Mở khóa Session để tránh block request khác trong lúc upload
        session_write_close();
        $uploadResult = DriveUploader::upload($file, $maSv, UPLOAD_SCRIPT_URL);
        session_start();

        if ($uploadResult && $uploadResult['success']) {
            return $uploadResult['fileUrl'];
        }

        Response::error('Lỗi upload file: ' . ($uploadResult['message'] ?? 'Lỗi không xác định khi upload file.'));
        return ''; // Unreachable — Response::error() calls exit
    }
}
