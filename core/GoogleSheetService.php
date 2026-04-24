<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/CacheManager.php';
class GoogleSheetService {
    private static ?self $instance = null;

    /** @var \Google_Service_Sheets */
    private $service;

    /** @var string */
    private $spreadsheetId;

    /** @var CacheManager */
    private CacheManager $cacheManager;

    private const CACHE_TTL = [
        'student_list'      => 600,
        'expelled_list'     => 900,
        'requests'          => 120,
        'notifications'     => 120,
        'courses_catalog'   => 86400, // 24 giờ — danh mục môn học hiếm khi thay đổi
        'config_huyhocphan' => 300,   // 5 phút — cấu hình đợt hủy
        'hhp_requests'      => 180,   // 3 phút — đơn hủy học phần
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

        $this->cacheManager = new CacheManager();
    }

    // =========================================
    //  SHEET DATA METHODS (có cache)
    // =========================================

    /**
     * Hàm dùng chung để fetch data từ Google Sheets và lưu Cache
     */
    private function fetchSheetDataCached(string $cacheKey, string $range, int $ttl, bool $returnEmptyArrayOnFail = false): ?array {
        $cached = $this->cacheManager->get($cacheKey, $ttl);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
            $values = $response->getValues() ?: [];
            $this->cacheManager->set($cacheKey, $values);
            return empty($values) && !$returnEmptyArrayOnFail ? null : $values;
        } catch (Exception $e) {
            error_log("Google Sheets Error fetching $cacheKey ($range): " . $e->getMessage());
            if ($returnEmptyArrayOnFail) return [];
            throw new Exception("Lỗi kết nối Google Sheets khi lấy $cacheKey: " . $e->getMessage());
        }
    }

    /**
     * Hàm dùng chung để ghi 1 dòng mới vào Google Sheets
     */
    public function appendRowToSheet(string $range, array $rowData): bool {
        try {
            $body = new \Google_Service_Sheets_ValueRange(['values' => [$rowData]]);
            $params = ['valueInputOption' => 'USER_ENTERED'];
            $result = $this->service->spreadsheets_values->append($this->spreadsheetId, $range, $body, $params);
            return $result->getUpdates() != null;
        } catch (Exception $e) {
            error_log("Google Sheets Error appendRowToSheet ($range): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lấy toàn bộ dữ liệu Sheet 1 (DS sinh viên) — cache chung.
     */
    private function fetchStudentListSheet(): ?array {
        return $this->fetchSheetDataCached('student_list', SHEET_STUDENT_LIST, self::CACHE_TTL['student_list']);
    }

    /**
     * Lấy toàn bộ dữ liệu Sheet 2 (DS bị xóa tên) — cache chung.
     */
    private function fetchExpelledListSheet(): ?array {
        return $this->fetchSheetDataCached('expelled_list', SHEET_EXPELLED_LIST, self::CACHE_TTL['expelled_list']);
    }

    /**
     * Lấy toàn bộ dữ liệu Sheet 3 (DS yêu cầu) — cache chung.
     */
    private function fetchRequestListSheet(): ?array {
        return $this->fetchSheetDataCached('requests_all', SHEET_REQUEST_LIST, self::CACHE_TTL['requests']);
    }

    /**
     * Lấy toàn bộ dữ liệu Sheet HuyHocPhan_Requests — cache chung.
     */
    private function fetchHuyHocPhanSheet(): ?array {
        return $this->fetchSheetDataCached('hhp_requests_all', SHEET_HUY_HOC_PHAN_REQUESTS . '!A2:N', self::CACHE_TTL['hhp_requests'], true);
    }

    // =========================================
    //  PUBLIC API
    // =========================================

    /**
     * Lấy toàn bộ dữ liệu Sheet Thông báo
     */
    private function fetchNotificationSheet(): ?array {
        if (!defined('SHEET_NOTIFICATION')) return null;
        try {
            return $this->fetchSheetDataCached('notifications', SHEET_NOTIFICATION, self::CACHE_TTL['notifications']);
        } catch (Exception $e) {
            return null;
        }
    }

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

                $studentData = [
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

                // Đọc thông báo riêng (nếu có)
                $studentData['thong_bao_rieng'] = '';
                $notiValues = $this->fetchNotificationSheet();
                if (!empty($notiValues)) {
                    $messages = [];
                    foreach ($notiValues as $nRow) {
                        $nMaSv = isset($nRow[0]) ? trim($nRow[0]) : '';
                        $nMsg = isset($nRow[1]) ? trim($nRow[1]) : '';
                        
                        // Hỗ trợ mã ALL hoặc * để gửi thông báo cho toàn bộ sinh viên
                        $isTargeted = (strtolower($nMaSv) === strtolower($currentMaSv) || strtoupper($nMaSv) === 'ALL' || $nMaSv === '*');
                        
                        if ($isTargeted && $nMsg !== '') {
                            // Mã hóa HTML để chống XSS
                            $safeMsg = htmlspecialchars($nMsg, ENT_QUOTES, 'UTF-8');
                            
                            // Auto-link: Tìm và biến URL thành chữ [Truy cập Link]
                            $processedMsg = preg_replace_callback(
                                '#\bhttps?://[^\s()<>]+#i',
                                function($matches) {
                                    $url = $matches[0];
                                    return '<a href="'.$url.'" target="_blank" style="text-decoration: underline; color: #991b1b; font-weight: 700; background: rgba(255,255,255,0.4); padding: 2px 6px; border-radius: 4px;">[<i class="fas fa-external-link-alt" style="font-size: 0.8rem;"></i> Truy cập Link]</a>';
                                },
                                $safeMsg
                            );
                            
                            // Giữ nguyên khoảng xuống dòng (Alt+Enter) từ Google Sheets
                            $messages[] = nl2br($processedMsg);
                        }
                    }
                    if (!empty($messages)) {
                        // Nối các thông báo bằng thẻ <hr> mờ mờ nếu có nhiều thông báo
                        $studentData['thong_bao_rieng'] = implode("<hr style='border: 0; border-top: 1px dashed rgba(239, 68, 68, 0.3); margin: 12px 0;'>", $messages);
                    }
                }

                return $studentData;
            }
        }

        return null;
    }

    /**
     * Lấy danh sách thông báo chung (ALL hoặc *)
     */
    public function getGlobalNotifications(): array {
        $notiValues = $this->fetchNotificationSheet();
        $messages = [];
        if (!empty($notiValues)) {
            foreach ($notiValues as $nRow) {
                $nMaSv = isset($nRow[0]) ? trim($nRow[0]) : '';
                $nMsg = isset($nRow[1]) ? trim($nRow[1]) : '';
                
                if ((strtoupper($nMaSv) === 'ALL' || $nMaSv === '*') && $nMsg !== '') {
                    $safeMsg = htmlspecialchars($nMsg, ENT_QUOTES, 'UTF-8');
                    $processedMsg = preg_replace_callback(
                        '#\bhttps?://[^\s()<>]+#i',
                        function($matches) {
                            $url = $matches[0];
                            return '<a href="'.$url.'" target="_blank" style="text-decoration: underline; color: #14b8a6; font-weight: 700;">[<i class="fas fa-external-link-alt" style="font-size: 0.8rem;"></i> Truy cập Link]</a>';
                        },
                        $safeMsg
                    );
                    $messages[] = nl2br($processedMsg);
                }
            }
        }
        return $messages;
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
            $this->cacheManager->set('student_list', $values);
            
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
                            $this->cacheManager->set('requests_all', $reqValues);
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
        $rowData = [
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
        ];

        $success = $this->appendRowToSheet(SHEET_REQUEST_LIST, $rowData);
        if ($success) {
            $this->cacheManager->invalidateRequestsCache();
        }
        return $success;
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

    // =========================================
    //  HỦY HỌC PHẦN — PUBLIC API
    // =========================================

    /**
     * Lấy cấu hình Đợt hủy học phần (có Cache 5 phút)
     * @return array ['TrangThai', 'TieuDeDot', 'TuNgay', 'DenNgay', 'ThongBaoDong']
     */
    public function getHuyHocPhanConfig(): array {
        $cacheKey = 'config_huyhocphan';
        $cached = $this->cacheManager->get($cacheKey, self::CACHE_TTL['config_huyhocphan']);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, SHEET_CONFIG_HUY_HOC_PHAN);
            $values = $response->getValues() ?: [];

            $activeRow = [];
            // Tìm đợt có trạng thái "Mở"
            foreach ($values as $row) {
                if (mb_strtolower(trim($row[0] ?? '')) === 'mở') {
                    $activeRow = $row;
                    break;
                }
            }

            // Nếu không có đợt nào "Mở", lấy đợt cuối cùng trong danh sách
            if (empty($activeRow) && !empty($values)) {
                $activeRow = end($values);
            }

            $config = [
                'TrangThai'    => trim($activeRow[0] ?? 'Đóng'),
                'TieuDeDot'    => trim($activeRow[1] ?? ''),
                'TuNgay'       => trim($activeRow[2] ?? ''),
                'DenNgay'      => trim($activeRow[3] ?? ''),
                'ThongBaoDong' => trim($activeRow[4] ?? 'Hệ thống hiện không mở đợt đăng ký hủy học phần.'),
            ];

            $this->cacheManager->set($cacheKey, $config);
            return $config;
        } catch (Exception $e) {
            error_log("Google Sheets Error getHuyHocPhanConfig: " . $e->getMessage());
            return [
                'TrangThai'    => 'Đóng',
                'TieuDeDot'    => '',
                'TuNgay'       => '',
                'DenNgay'      => '',
                'ThongBaoDong' => 'Không thể tải cấu hình. Vui lòng thử lại sau.',
            ];
        }
    }

    /**
     * Lấy danh mục Môn học (có Cache 24 giờ)
     * @return array [['id' => 'INT123', 'name' => 'Toán cao cấp'], ...]
     */
    public function getCoursesCatalog(): array {
        try {
            $values = $this->fetchSheetDataCached('courses_catalog', SHEET_COURSES_CATALOG, self::CACHE_TTL['courses_catalog'], true);
            $courses = [];
            foreach ($values as $row) {
                $id = trim($row[0] ?? '');
                $name = trim($row[1] ?? '');
                if ($id !== '') {
                    $courses[] = ['id' => $id, 'name' => $name];
                }
            }
            return $courses;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Kiểm tra xem SV đã nộp đơn hủy học phần trong đợt hiện tại chưa
     * Dùng chung dữ liệu đã cache từ fetchHuyHocPhanSheet()
     */
    public function checkHuyHocPhanSubmitted(string $maSv, string $tieuDeDot): bool {
        $values = $this->fetchHuyHocPhanSheet() ?: [];
        foreach ($values as $row) {
            $rowMaSv = trim($row[1] ?? '');
            $rowDot  = trim($row[8] ?? '');
            if (strtolower($rowMaSv) === strtolower(trim($maSv)) && $rowDot === $tieuDeDot) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ghi đơn Hủy học phần mới vào Google Sheet
     */
    public function appendHuyHocPhanRequest(array $data): bool {
        $rowData = [
            date('d/m/Y H:i:s'),           // A: Timestamp
            $data['ma_sv'],            // B: MaSV
            $data['ho_ten'],           // C: HoTen
            $data['ngay_sinh'] ?? '',   // D: NgaySinh
            $data['khoa'] ?? '',       // E: Khoa
            $data['he'] ?? '',         // F: Hệ
            $data['nganh'] ?? '',      // G: Ngành
            $data['lop'] ?? '',        // H: Lớp
            $data['tieu_de_dot'],      // I: TieuDeDot
            $data['danh_sach_mon'],    // J: DanhSachMonHuy
            $data['ly_do'],            // K: LyDo
            $data['link_minh_chung'] ?? '', // L: LinkMinhChung
            'Chờ xử lý',              // M: TrangThai
            '',                        // N: GhiChuAdmin
        ];

        $success = $this->appendRowToSheet(SHEET_HUY_HOC_PHAN_REQUESTS . '!A:N', $rowData);
        if ($success) {
            $this->cacheManager->clear('hhp_requests_all');
        }
        return $success;
    }

    /**
     * Lấy lịch sử đơn hủy học phần của sinh viên
     * @return array Danh sách đơn đã nộp
     */
    public function getHuyHocPhanHistory(string $maSv): array {
        $values = $this->fetchHuyHocPhanSheet() ?: [];
        $history = [];

        foreach ($values as $row) {
            $rowMaSv = trim($row[1] ?? '');
            if (strtolower($rowMaSv) === strtolower(trim($maSv))) {
                $history[] = [
                    'timestamp'      => $row[0] ?? '',
                    'tieu_de_dot'    => $row[8] ?? '',
                    'danh_sach_mon'  => $row[9] ?? '',
                    'ly_do'          => $row[10] ?? '',
                    'link_minh_chung'=> $row[11] ?? '',
                    'trang_thai'     => $row[12] ?? 'Chờ xử lý',
                    'ghi_chu_admin'  => $row[13] ?? '',
                ];
            }
        }
        return $history;
    }

}
