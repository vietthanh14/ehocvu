<?php
require_once __DIR__ . '/GoogleSheetClient.php';

class LeTotNghiepService {
    private GoogleSheetClient $client;

    // Danh sách dịch vụ
    public const DICH_VU = [
        'ep_vanbang'    => 'Ép văn bằng',
        'ep_bangdiem'   => 'Ép bảng điểm',
        'bansao_bd'     => 'Bản sao bảng điểm',
        'ep_bansao_bd'  => 'Ép bản sao bảng điểm',
    ];

    public function __construct() {
        $this->client = GoogleSheetClient::getInstance();
    }

    /**
     * Đọc cấu hình đợt Lễ Tốt Nghiệp từ ConfigService thống nhất
     */
    public function getConfig(): array {
        require_once __DIR__ . '/ConfigService.php';
        $configService = new ConfigService();
        $raw = $configService->getFeatureConfig('letotnghiep');

        return [
            'TrangThai'    => $raw['TrangThai'],
            'TieuDeDot'    => $raw['TieuDeDot'],
            'TuNgay'       => $raw['TuNgay'],
            'DenNgay'      => $raw['DenNgay'],
            'ThongBaoDong' => 'Hệ thống hiện không mở đợt đăng ký dự lễ tốt nghiệp.',
        ];
    }

    private function fetchRequestsSheet(): ?array {
        return $this->client->fetchSheetDataCached(
            'ltn_requests_all',
            SHEET_LE_TOT_NGHIEP_REQUESTS,
            CACHE_TTL_LTN_REQUESTS,
            true
        );
    }

    /**
     * Tìm bản ghi đăng ký của SV trong đợt hiện tại
     * 
     * Cấu trúc sheet 18 cột (A-R):
     * A:Timestamp B:MaSV C:HoTen D:NgaySinh E:SĐT F:Khoa G:Hệ H:Lớp 
     * I:ChuyenNganh J:NienKhoa K:TieuDeDot L:XacNhan M:SoKhachMoi
     * N:EpVanBang O:BangDiem P:BanSao Q:EpBanSao R:GhiChu
     */
    public function findRegistration(string $maSv, string $tieuDeDot): ?array {
        $values = $this->fetchRequestsSheet() ?: [];
        foreach ($values as $row) {
            $rowMaSv = trim($row[1] ?? '');
            $rowDot  = trim($row[10] ?? '');
            if (strtolower($rowMaSv) === strtolower(trim($maSv)) && $rowDot === $tieuDeDot) {
                return [
                    'timestamp'    => $row[0] ?? '',
                    'xac_nhan'     => $row[11] ?? '',
                    'so_khach_moi' => $row[12] ?? '0',
                    'ep_vanbang'   => (int)($row[13] ?? 0),
                    'ep_bangdiem'  => (int)($row[14] ?? 0),
                    'bansao_bd'    => (int)($row[15] ?? 0),
                    'ep_bansao_bd' => (int)($row[16] ?? 0),
                    'ghi_chu'      => $row[17] ?? '',
                ];
            }
        }
        return null;
    }

    /**
     * Lấy toàn bộ lịch sử đăng ký của SV (mọi đợt)
     */
    public function getHistory(string $maSv): array {
        $values = $this->fetchRequestsSheet() ?: [];
        $history = [];

        foreach ($values as $row) {
            $rowMaSv = trim($row[1] ?? '');
            if (strtolower($rowMaSv) === strtolower(trim($maSv))) {
                $history[] = [
                    'timestamp'    => $row[0] ?? '',
                    'tieu_de_dot'  => $row[10] ?? '',
                    'xac_nhan'     => $row[11] ?? '',
                    'so_khach_moi' => $row[12] ?? '0',
                    'ep_vanbang'   => (int)($row[13] ?? 0),
                    'ep_bangdiem'  => (int)($row[14] ?? 0),
                    'bansao_bd'    => (int)($row[15] ?? 0),
                    'ep_bansao_bd' => (int)($row[16] ?? 0),
                    'ghi_chu'      => $row[17] ?? '',
                ];
            }
        }
        return $history;
    }

    /**
     * Thêm bản đăng ký mới (18 cột A-R)
     */
    public function appendRegistration(array $data): bool {
        $rowData = [
            date('d/m/Y H:i:s'),           // A: Timestamp
            $data['ma_sv'],                // B: MaSV
            $data['ho_ten'],               // C: HoTen
            $data['ngay_sinh'] ?? '',       // D: NgaySinh
            $data['sdt'] ?? '',            // E: SĐT
            $data['ten_khoa'] ?? '',       // F: Khoa
            $data['ten_he'] ?? '',         // G: Hệ
            $data['ten_lop'] ?? '',        // H: Lớp
            $data['chuyen_nganh'] ?? '',   // I: Chuyên ngành
            $data['nien_khoa'] ?? '',      // J: Niên khóa
            $data['tieu_de_dot'],          // K: Tiêu đề đợt
            $data['xac_nhan'],             // L: Tham gia / Không tham gia
            $data['so_khach_moi'],         // M: Số khách mời (0-2)
            $data['ep_vanbang'] ?? 0,      // N: Ép văn bằng
            $data['ep_bangdiem'] ?? 0,     // O: Ép bảng điểm
            $data['bansao_bd'] ?? 0,       // P: SL bản sao bảng điểm
            $data['ep_bansao_bd'] ?? 0,    // Q: SL ép bản sao bảng điểm
            $data['ghi_chu'] ?? '',        // R: Ghi chú
        ];

        $success = $this->client->appendRowToSheet(SHEET_LE_TOT_NGHIEP_REQUESTS . '!A:R', $rowData);
        if ($success) {
            $this->client->getCacheManager()->clear('ltn_requests_all');
        }
        return $success;
    }

    /**
     * Cập nhật xác nhận của SV (đổi ý Tham gia ↔ Không tham gia + dịch vụ)
     */
    public function updateRegistration(string $maSv, string $tieuDeDot, array $newData): bool {
        $values = $this->fetchRequestsSheet() ?: [];

        $targetRowIndex = -1;
        foreach ($values as $i => $row) {
            $rowMaSv = trim($row[1] ?? '');
            $rowDot  = trim($row[10] ?? '');
            if (strtolower($rowMaSv) === strtolower(trim($maSv)) && $rowDot === $tieuDeDot) {
                $targetRowIndex = $i;
                break;
            }
        }

        if ($targetRowIndex === -1) return false;

        // Dữ liệu từ fetchRequestsSheet() bao gồm header (index 0 = row 1 trên Sheet)
        // Nên row trong Sheet = index + 1
        $sheetRow = $targetRowIndex + 1;

        try {
            // Cập nhật: A (Timestamp) + L:R (XacNhan, SoKhachMoi, 4 dịch vụ, GhiChu)
            $batchData = [
                new \Google_Service_Sheets_ValueRange([
                    'range' => SHEET_LE_TOT_NGHIEP_REQUESTS . '!A' . $sheetRow,
                    'values' => [[date('d/m/Y H:i:s')]]
                ]),
                new \Google_Service_Sheets_ValueRange([
                    'range' => SHEET_LE_TOT_NGHIEP_REQUESTS . '!L' . $sheetRow . ':R' . $sheetRow,
                    'values' => [[
                        $newData['xac_nhan'],
                        $newData['so_khach_moi'],
                        $newData['ep_vanbang'] ?? 0,
                        $newData['ep_bangdiem'] ?? 0,
                        $newData['bansao_bd'] ?? 0,
                        $newData['ep_bansao_bd'] ?? 0,
                        $newData['ghi_chu'] ?? ''
                    ]]
                ]),
            ];

            $batchBody = new \Google_Service_Sheets_BatchUpdateValuesRequest([
                'valueInputOption' => 'USER_ENTERED',
                'data' => $batchData
            ]);

            $this->client->getService()->spreadsheets_values->batchUpdate(
                $this->client->getSpreadsheetId(),
                $batchBody
            );

            $this->client->getCacheManager()->clear('ltn_requests_all');
            return true;
        } catch (Exception $e) {
            error_log("Google Sheets Error updateRegistration LeTotNghiep: " . $e->getMessage());
            return false;
        }
    }
}
