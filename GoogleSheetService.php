<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

class GoogleSheetService {
    private $service;
    private $spreadsheetId;

    /** Thư mục lưu cache */
    private $cacheDir;

    /** TTL cache (giây) cho từng loại dữ liệu */
    private const CACHE_TTL = [
        'student_list' => 600,   // 10 phút — thông tin SV ít thay đổi
        'expelled_list' => 900,  // 15 phút — DS bị xóa tên rất ít đổi
        'requests'      => 120,  // 2 phút  — cập nhật thường xuyên hơn
    ];

    public function __construct() {
        $client = new \Google_Client();
        $client->setApplicationName('QL Bao Luu App');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $client->setAuthConfig(GOOGLE_AUTH_JSON_PATH);
        $client->setAccessType('offline');

        $this->service = new \Google_Service_Sheets($client);
        $this->spreadsheetId = SPREADSHEET_ID;

        $this->cacheDir = __DIR__ . '/cache';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    // =========================================
    //  CACHE LAYER
    // =========================================

    /**
     * Đọc cache. Trả về dữ liệu nếu cache còn hạn, null nếu hết hạn/chưa có.
     */
    private function cacheGet(string $key, int $ttl): ?array {
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
    private function cacheSet(string $key, array $data): void {
        $file = $this->cacheDir . '/' . $key . '.json';
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Xóa cache theo key (dùng khi cần invalidation).
     */
    private function cacheClear(string $key): void {
        $file = $this->cacheDir . '/' . $key . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Xóa toàn bộ cache liên quan tới request (sau khi submit).
     */
    public function invalidateRequestsCache(): void {
        // Xóa tất cả file cache có prefix "requests_"
        $files = glob($this->cacheDir . '/requests_*.json');
        foreach ($files as $f) {
            unlink($f);
        }
    }

    // =========================================
    //  SHEET DATA METHODS (có cache)
    // =========================================

    /**
     * Lấy toàn bộ dữ liệu Sheet 1 (DS sinh viên) — cache chung.
     */
    private function fetchStudentListSheet(): ?array {
        $cacheKey = 'student_list';
        $cached = $this->cacheGet($cacheKey, self::CACHE_TTL['student_list']);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $range = SHEET_STUDENT_LIST;
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
            $values = $response->getValues();

            if (!empty($values)) {
                $this->cacheSet($cacheKey, $values);
            }

            return $values ?: null;
        } catch (Exception $e) {
            error_log("Google Sheets Error fetchStudentListSheet: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Lấy toàn bộ dữ liệu Sheet 2 (DS bị xóa tên) — cache chung.
     */
    private function fetchExpelledListSheet(): ?array {
        $cacheKey = 'expelled_list';
        $cached = $this->cacheGet($cacheKey, self::CACHE_TTL['expelled_list']);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $range = SHEET_EXPELLED_LIST;
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
            $values = $response->getValues();

            if (!empty($values)) {
                $this->cacheSet($cacheKey, $values);
            }

            return $values ?: null;
        } catch (Exception $e) {
            error_log("Google Sheets Error fetchExpelledListSheet: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Lấy toàn bộ dữ liệu Sheet 3 (DS yêu cầu) — cache chung.
     */
    private function fetchRequestListSheet(): ?array {
        $cacheKey = 'requests_all';
        $cached = $this->cacheGet($cacheKey, self::CACHE_TTL['requests']);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $range = SHEET_REQUEST_LIST;
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
            $values = $response->getValues();

            if (!empty($values)) {
                $this->cacheSet($cacheKey, $values);
            }

            return $values ?: null;
        } catch (Exception $e) {
            error_log("Google Sheets Error fetchRequestListSheet: " . $e->getMessage());
            return null;
        }
    }

    // =========================================
    //  PUBLIC API
    // =========================================

    /**
     * Tìm thông tin sinh viên dựa vào mã sinh viên
     */
    public function getStudentInfo($maSv) {
        $values = $this->fetchStudentListSheet();

        if (empty($values)) {
            return null;
        }

        $headers = $values[0];
        $maSvIndex = -1;
        foreach ($headers as $colIndex => $colName) {
            $colNameLower = strtolower(trim($colName));
            if (strpos($colNameLower, 'mã sv') !== false || strpos($colNameLower, 'mã sinh viên') !== false || strpos($colNameLower, 'ma sv') !== false) {
                $maSvIndex = $colIndex;
                break;
            }
        }

        if ($maSvIndex === -1 && count($headers) >= 2) {
            $maSvIndex = 1; // Fallback
        }

        foreach ($values as $index => $row) {
            if ($index === 0) continue;

            $currentMaSv = isset($row[$maSvIndex]) ? trim($row[$maSvIndex]) : '';

            if (strtolower($currentMaSv) === strtolower(trim($maSv))) {
                $map = [];
                foreach ($headers as $cIdx => $cName) {
                    $n = strtolower(trim($cName));
                    $map[$n] = $cIdx;
                }

                $getVal = function($keys) use ($map, $row) {
                    foreach ((array)$keys as $k) {
                        if (isset($map[$k]) && isset($row[$map[$k]])) {
                            return $row[$map[$k]];
                        }
                    }
                    return '';
                };

                return [
                    'ma_sv' => $currentMaSv,
                    'ho_ten' => $getVal(['họ tên', 'họ và tên', 'ho ten']),
                    'ngay_sinh' => $getVal(['ngày sinh', 'ngay sinh', 'năm sinh']),
                    'sdt' => $getVal(['sđt', 'sdt', 'điện thoại', 'số điện thoại']),
                    'ten_khoa' => $getVal(['tên khoa', 'khoa']),
                    'ten_he' => $getVal(['tên hệ', 'hệ']),
                    'ten_lop' => $getVal(['tên lớp', 'lớp']),
                    'chuyen_nganh' => $getVal(['chuyên ngành', 'ngành']),
                    'nien_khoa' => $getVal(['niên khóa', 'khoá'])
                ];
            }
        }

        return null;
    }

    /**
     * Kiểm tra sinh viên có trong danh sách bị xóa tên hay không
     */
    public function isExpelled($maSv) {
        $values = $this->fetchExpelledListSheet();

        if (empty($values)) {
            return false;
        }

        foreach ($values as $index => $row) {
            if ($index === 0) continue;

            $currentMaSv = isset($row[7]) ? trim($row[7]) : '';

            if (strtolower($currentMaSv) === strtolower(trim($maSv))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ghi yêu cầu đăng ký mới
     */
    public function appendRequest($data) {
        try {
            $range = SHEET_REQUEST_LIST;

            $values = [
                [
                    date('d/m/Y H:i:s'),
                    $data['ma_sv'],
                    $data['ho_ten'],
                    $data['ngay_sinh'] ?? '',
                    $data['sdt'] ?? '',
                    $data['ten_khoa'] ?? '',
                    $data['ten_he'] ?? '',
                    $data['ten_lop'],
                    $data['chuyen_nganh'],
                    $data['nien_khoa'] ?? '',
                    $data['loai_yeu_cau'],
                    $data['thoi_gian_bao_luu_den'] ?? '',
                    $data['link_don_dang_ky'] ?? '',
                    'Chờ duyệt',
                    '', '', '', ''
                ]
            ];

            $body = new \Google_Service_Sheets_ValueRange([
                'values' => $values
            ]);
            $params = [
                'valueInputOption' => 'USER_ENTERED'
            ];

            $result = $this->service->spreadsheets_values->append($this->spreadsheetId, $range, $body, $params);

            // Xóa cache request để lần sau lấy dữ liệu mới
            $this->invalidateRequestsCache();

            return $result->getUpdates() != null;
        } catch (Exception $e) {
            error_log("Google Sheets Error appendRequest: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lấy danh sách các yêu cầu/thủ tục đã đăng ký của một sinh viên
     */
    public function getStudentRequests($maSv) {
        $values = $this->fetchRequestListSheet();

        $requests = [];

        if (empty($values)) {
            return $requests;
        }

        foreach ($values as $index => $row) {
            if ($index === 0) continue;

            $currentMaSv = isset($row[1]) ? trim($row[1]) : '';

            if (strtolower($currentMaSv) === strtolower(trim($maSv))) {
                $requests[] = [
                    'thoi_gian' => isset($row[0]) ? $row[0] : '',
                    'loai_yeu_cau' => isset($row[10]) ? $row[10] : '',
                    'thoi_gian_bao_luu_den' => isset($row[11]) ? trim($row[11]) : '',
                    'link_don_dang_ky' => isset($row[12]) ? trim($row[12]) : '',
                    'trang_thai' => isset($row[13]) && trim($row[13]) !== '' ? trim($row[13]) : 'Chờ xử lý',
                    'ghi_chu' => isset($row[14]) ? trim($row[14]) : '',
                    'so_quyet_dinh' => isset($row[15]) ? trim($row[15]) : '',
                    'ngay_quyet_dinh' => isset($row[16]) ? trim($row[16]) : '',
                    'link_file_quyet_dinh' => isset($row[17]) ? trim($row[17]) : '',
                ];
            }
        }

        return array_reverse($requests);
    }

    /**
     * Chuyển trạng thái Đóng hồ sơ đối với đơn Bảo lưu trước đó (nếu có)
     */
    public function closePreviousApprovedBaoLuu($maSv) {
        $values = $this->fetchRequestListSheet();
        if (empty($values)) return false;

        $targetRow = -1;
        // Duyệt ngược để tìm đơn Bảo lưu gần nhất đã Duyệt
        for ($i = count($values) - 1; $i >= 1; $i--) {
            $row = $values[$i];
            $currentMaSv = isset($row[1]) ? trim($row[1]) : '';
            if (strtolower($currentMaSv) === strtolower(trim($maSv))) {
                $loai = isset($row[10]) ? trim($row[10]) : '';
                $tt = isset($row[13]) ? trim($row[13]) : '';
                
                if ($loai === 'Bảo lưu kết quả học tập' && 
                    (mb_strpos(mb_strtolower($tt), 'duyệt') !== false || mb_strpos(mb_strtolower($tt), 'thành công') !== false)) {
                    $targetRow = $i + 1; // Google Sheets row (1-based index)
                    break;
                }
            }
        }

        if ($targetRow !== -1) {
            // Cột N là cột trạng thái (index 13)
            $sheetParts = explode('!', SHEET_REQUEST_LIST);
            $sheetName = $sheetParts[0];
            $updateRange = $sheetName . '!N' . $targetRow;

            $body = new \Google_Service_Sheets_ValueRange([
                'values' => [['Đã đóng (Xin tiếp tục học)']]
            ]);
            $params = ['valueInputOption' => 'USER_ENTERED'];

            try {
                $this->service->spreadsheets_values->update($this->spreadsheetId, $updateRange, $body, $params);
                $this->invalidateRequestsCache();
                return true;
            } catch (Exception $e) {
                error_log("Google Sheets Error closePreviousApprovedBaoLuu: " . $e->getMessage());
                return false;
            }
        }

        return false;
    }
}
