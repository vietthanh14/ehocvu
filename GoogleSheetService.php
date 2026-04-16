<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

class GoogleSheetService {
    private static ?self $instance = null;

    /** @var \Google_Service_Sheets */
    private $service;

    /** @var string */
    private $spreadsheetId;

    /** @var string */
    private $cacheDir;

    private const CACHE_TTL = [
        'student_list' => 600,
        'expelled_list' => 900,
        'requests'      => 120,
    ];

    /** Column index mapping cho Sheet3 (DS yêu cầu) */
    private const REQ_COL = [
        'TIMESTAMP'    => 0,
        'MA_SV'        => 1,
        'HO_TEN'       => 2,
        'NGAY_SINH'    => 3,
        'SDT'          => 4,
        'TEN_KHOA'     => 5,
        'TEN_HE'       => 6,
        'TEN_LOP'      => 7,
        'CHUYEN_NGANH' => 8,
        'NIEN_KHOA'    => 9,
        'LOAI_YC'      => 10,
        'BL_DEN'       => 11,
        'LINK_DON'     => 12,
        'TRANG_THAI'   => 13,
        'GHI_CHU'      => 14,
        'SO_QD'        => 15,
        'NGAY_QD'      => 16,
        'LINK_QD'      => 17,
    ];

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

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
            throw new Exception("Lỗi kết nối Google Sheets: " . $e->getMessage());
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
            throw new Exception("Lỗi kết nối Google Sheets: " . $e->getMessage());
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
            throw new Exception("Lỗi kết nối Google Sheets: " . $e->getMessage());
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
     * Cập nhật số điện thoại sinh viên trong Sheet1
     */
    public function updateStudentPhone(string $maSv, string $newPhone): bool {
        $values = $this->fetchStudentListSheet();
        if (empty($values)) return false;

        $headers = $values[0];
        $maSvIndex = -1;
        $sdtIndex = -1;

        // Xác định cột Mã SV và cột SĐT
        foreach ($headers as $colIndex => $colName) {
            $n = strtolower(trim($colName));
            if ($maSvIndex === -1 && (strpos($n, 'mã sv') !== false || strpos($n, 'mã sinh viên') !== false || strpos($n, 'ma sv') !== false)) {
                $maSvIndex = $colIndex;
            }
            if ($sdtIndex === -1 && (strpos($n, 'sđt') !== false || strpos($n, 'sdt') !== false || strpos($n, 'điện thoại') !== false)) {
                $sdtIndex = $colIndex;
            }
        }

        if ($maSvIndex === -1 || $sdtIndex === -1) {
            error_log("Google Sheets Error: Không tìm thấy cột Mã SV hoặc SĐT");
            return false;
        }

        $targetRow = -1;
        foreach ($values as $index => $row) {
            if ($index === 0) continue;
            $currentMaSv = isset($row[$maSvIndex]) ? trim($row[$maSvIndex]) : '';
            if (strtolower($currentMaSv) === strtolower(trim($maSv))) {
                $targetRow = $index + 1; // Google Sheets là 1-based index
                break;
            }
        }

        if ($targetRow === -1) {
            return false;
        }

        // Chuyển đổi $sdtIndex sang ký tự Cột (0=A, 1=B, ..., 26=AA)
        $colChar = '';
        $temp = $sdtIndex;
        while ($temp >= 0) {
            $colChar = chr(65 + ($temp % 26)) . $colChar;
            $temp = intdiv($temp, 26) - 1;
        }

        $sheetParts = explode('!', SHEET_STUDENT_LIST);
        $sheetName = $sheetParts[0];
        $updateRange = $sheetName . '!' . $colChar . $targetRow;

        $body = new \Google_Service_Sheets_ValueRange([
            'values' => [[$newPhone]]
        ]);
        
        try {
            $this->service->spreadsheets_values->update(
                $this->spreadsheetId,
                $updateRange,
                $body,
                ['valueInputOption' => 'USER_ENTERED']
            );
            // Tối ưu In-place cache update thay vì xóa trắng file cache
            // Điều này hạn chế 1 lượng lớn request tải lại Google Sheet
            while (count($values[$targetRow - 1]) <= $sdtIndex) {
                $values[$targetRow - 1][] = '';
            }
            $values[$targetRow - 1][$sdtIndex] = $newPhone;
            $this->cacheSet('student_list', $values);
            
            // --- ĐỒNG BỘ SĐT SANG SHEET 3 ---
            try {
                $reqValues = $this->fetchRequestListSheet();
                if (!empty($reqValues)) {
                    $C = self::REQ_COL;
                    $sheetParts3 = explode('!', SHEET_REQUEST_LIST);
                    $sheet3Name = $sheetParts3[0];
                    $sdtColChar3 = chr(65 + $C['SDT']); // 'E'
                    
                    $batchData = [];
                    $hasChange = false;

                    foreach ($reqValues as $i => $r) {
                        if ($i === 0) continue;
                        $reqMaSv = isset($r[$C['MA_SV']]) ? trim($r[$C['MA_SV']]) : '';
                        if (strtolower($reqMaSv) === strtolower(trim($maSv))) {
                            $rowNum = $i + 1;
                            $range3 = $sheet3Name . '!' . $sdtColChar3 . $rowNum;
                            $batchData[] = new \Google_Service_Sheets_ValueRange([
                                'range' => $range3,
                                'values' => [[$newPhone]]
                            ]);
                            
                            // Edit in-place cache
                            while (count($reqValues[$i]) <= $C['SDT']) {
                                $reqValues[$i][] = '';
                            }
                            $reqValues[$i][$C['SDT']] = $newPhone;
                            $hasChange = true;
                        }
                    }

                    if (!empty($batchData)) {
                        $batchBody = new \Google_Service_Sheets_BatchUpdateValuesRequest([
                            'valueInputOption' => 'USER_ENTERED',
                            'data' => $batchData
                        ]);
                        $this->service->spreadsheets_values->batchUpdate($this->spreadsheetId, $batchBody);
                        
                        if ($hasChange) {
                            $this->cacheSet('requests_all', $reqValues);
                        }
                    }
                }
            } catch (Exception $eSync) {
                // Lỗi đồng bộ Sheet 3 không ảnh hưởng kết quả trả về của thao tác chính
                error_log("Google Sheets Sync Error (Sheet3): " . $eSync->getMessage());
            }

            return true;
        } catch (Exception $e) {
            error_log("Google Sheets Error updateStudentPhone: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Kiểm tra sinh viên có trong danh sách bị xóa tên hay không
     */
    public function isExpelled($maSv) {
        $values = $this->fetchExpelledListSheet();
        if (empty($values)) return false;

        // Header detection linh hoạt thay vì hardcode column index
        $headers = $values[0];
        $maSvIndex = -1;
        foreach ($headers as $colIndex => $colName) {
            $n = strtolower(trim($colName));
            if (strpos($n, 'mã sv') !== false || strpos($n, 'mã sinh viên') !== false ||
                strpos($n, 'ma sv') !== false || strpos($n, 'mssv') !== false) {
                $maSvIndex = $colIndex;
                break;
            }
        }
        if ($maSvIndex === -1 && count($headers) >= 8) {
            $maSvIndex = 7; // Fallback
        }

        foreach ($values as $index => $row) {
            if ($index === 0) continue;
            $currentMaSv = isset($row[$maSvIndex]) ? trim($row[$maSvIndex]) : '';
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
                    $data['ly_do'] ?? '',
                    '', '', ''
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
     * Timeline Analysis: Tính toán trạng thái bảo lưu/đi học dựa trên sự kiện (Event Sourcing).
     */
    public function getStudentSubmitEligibility(string $maSv): array {
        $requests = $this->getStudentRequests($maSv); // Trả về danh sách đã reverse (MỚI NHẤT đứng đầu)
        $pendingTypes = [];
        $coQDBaoLuu = false; // Trạng thái "đang trong thời gian bảo lưu"
        
        // Cờ để chỉ bắt lấy trạng thái đã chốt gần nhất
        $resolvedStateFound = false; 

        foreach ($requests as $req) {
            $ttLower = mb_strtolower(trim($req['trang_thai']));
            $reqType = trim($req['loai_yeu_cau']);
            $isPending = (mb_strpos($ttLower, 'chờ') !== false);

            if ($isPending) {
                // Thu thập TẤT CẢ các đơn đang chờ
                $pendingTypes[] = $reqType;
            }

            // Phân tích trạng thái đã chốt gần nhất (Event Sourcing)
            if (!$resolvedStateFound && !$isPending && (mb_strpos($ttLower, 'duyệt') !== false || mb_strpos($ttLower, 'thành công') !== false || mb_strpos($ttLower, 'xong') !== false)) {
                if ($reqType === 'Bảo lưu kết quả học tập') {
                    $coQDBaoLuu = true; // Sự kiện gần nhất là Bảo lưu => Đang bảo lưu
                } elseif ($reqType === 'Tiếp tục học sau bảo lưu') {
                    $coQDBaoLuu = false; // Sự kiện gần nhất là Tiếp tục học => Hết quyển bảo lưu
                }
                $resolvedStateFound = true; // Đã tìm thấy trạng thái chốt gần nhất
            }
        }

        $isTiepTucHocPending = in_array('Tiếp tục học sau bảo lưu', $pendingTypes);

        return [
            'pendingTypes'        => $pendingTypes,
            'coQDBaoLuu'          => $coQDBaoLuu,
            'isTiepTucHocPending' => $isTiepTucHocPending,
            'canSubmitBaoLuu'     => !$coQDBaoLuu && !$isTiepTucHocPending && !in_array('Bảo lưu kết quả học tập', $pendingTypes),
            'canSubmitTiepTuc'    => $coQDBaoLuu && !in_array('Tiếp tục học sau bảo lưu', $pendingTypes),
        ];
    }

    /**
     * Lấy danh sách các yêu cầu/thủ tục đã đăng ký của một sinh viên
     */
    public function getStudentRequests($maSv) {
        $values = $this->fetchRequestListSheet();
        $requests = [];
        if (empty($values)) return $requests;

        $C = self::REQ_COL;
        foreach ($values as $index => $row) {
            if ($index === 0) continue;
            $currentMaSv = isset($row[$C['MA_SV']]) ? trim($row[$C['MA_SV']]) : '';
            if (strtolower($currentMaSv) === strtolower(trim($maSv))) {
                $requests[] = [
                    'thoi_gian'             => $row[$C['TIMESTAMP']] ?? '',
                    'loai_yeu_cau'          => $row[$C['LOAI_YC']] ?? '',
                    'thoi_gian_bao_luu_den' => isset($row[$C['BL_DEN']]) ? trim($row[$C['BL_DEN']]) : '',
                    'link_don_dang_ky'      => isset($row[$C['LINK_DON']]) ? trim($row[$C['LINK_DON']]) : '',
                    'trang_thai'            => isset($row[$C['TRANG_THAI']]) && trim($row[$C['TRANG_THAI']]) !== '' ? trim($row[$C['TRANG_THAI']]) : 'Chờ xử lý',
                    'ghi_chu'               => isset($row[$C['GHI_CHU']]) ? trim($row[$C['GHI_CHU']]) : '',
                    'so_quyet_dinh'         => isset($row[$C['SO_QD']]) ? trim($row[$C['SO_QD']]) : '',
                    'ngay_quyet_dinh'       => isset($row[$C['NGAY_QD']]) ? trim($row[$C['NGAY_QD']]) : '',
                    'link_file_quyet_dinh'  => isset($row[$C['LINK_QD']]) ? trim($row[$C['LINK_QD']]) : '',
                ];
            }
        }
        return array_reverse($requests);
    }

}
