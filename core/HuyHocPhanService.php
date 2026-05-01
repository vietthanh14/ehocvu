<?php
require_once __DIR__ . '/GoogleSheetClient.php';

class HuyHocPhanService {
    private GoogleSheetClient $client;

    public function __construct() {
        $this->client = GoogleSheetClient::getInstance();
    }

    public function getHuyHocPhanConfig(): array {
        require_once __DIR__ . '/ConfigService.php';
        $configService = new ConfigService();
        $raw = $configService->getFeatureConfig('huyhocphan');

        return [
            'TrangThai'    => $raw['TrangThai'],
            'TieuDeDot'    => $raw['TieuDeDot'],
            'TuNgay'       => $raw['TuNgay'],
            'DenNgay'      => $raw['DenNgay'],
            'ThongBaoDong' => 'Hệ thống hiện không mở đợt đăng ký hủy học phần.',
        ];
    }

    public function getCoursesCatalog(): array {
        try {
            $values = $this->client->fetchSheetDataCached('courses_catalog', SHEET_COURSES_CATALOG, CACHE_TTL_COURSES_CATALOG, true);
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

    private function fetchHuyHocPhanSheet(): ?array {
        return $this->client->fetchSheetDataCached('hhp_requests_all', SHEET_HUY_HOC_PHAN_REQUESTS, CACHE_TTL_HHP_REQUESTS, true);
    }

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

        $success = $this->client->appendRowToSheet(SHEET_HUY_HOC_PHAN_REQUESTS . '!A:N', $rowData);
        if ($success) {
            $this->client->getCacheManager()->clear('hhp_requests_all');
        }
        return $success;
    }
}
