<?php
class DriveUploader {
    /**
     * Upload file lên Google Drive qua Apps Script
     *
     * @param array $file $_FILES array element
     * @param string $maSv Mã sinh viên
     * @param string $scriptUrl Apps Script Web App URL
     * @return array Kết quả trả về gồm 'success' và 'message' hoặc 'fileUrl'
     */
    public static function upload(array $file, string $maSv, string $scriptUrl): array {
        if (empty($scriptUrl) || $scriptUrl === 'YOUR_APPS_SCRIPT_URL_HERE') {
            error_log("UPLOAD_SCRIPT_URL chưa được cấu hình trong config.php");
            return ['success' => false, 'message' => 'Hệ thống upload chưa được cấu hình. Vui lòng liên hệ quản trị viên.'];
        }

        $fileContent = file_get_contents($file['tmp_name']);
        $fileBase64 = base64_encode($fileContent);

        $payload = json_encode([
            'fileName'   => $file['name'],
            'mimeType'   => $file['type'],
            'fileBase64' => $fileBase64,
            'maSv'       => $maSv
        ]);

        $maxRetries = 3;
        $response = false;
        $httpCode = 0;
        $error = '';

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $scriptUrl,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,  // Apps Script redirects
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            // Retry on HTTP errors (like 429 Too Many Requests or 500/503)
            if ($httpCode >= 400 && $attempt < $maxRetries) {
                sleep($attempt);
                continue;
            }
            break; // Success or max retries reached
        }

        if ($error) {
            error_log("cURL error uploading to Drive: $error");
            return ['success' => false, 'message' => 'Lỗi kết nối đến Google Drive: ' . $error];
        }

        $result = json_decode($response, true);

        if (!$result) {
            error_log("Invalid response from Apps Script: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Phản hồi không hợp lệ từ máy chủ.'];
        }

        return $result;
    }
}
