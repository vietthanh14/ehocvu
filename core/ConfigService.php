<?php
require_once __DIR__ . '/GoogleSheetClient.php';

/**
 * ConfigService — Đọc cấu hình đợt đăng ký từ Sheet Config thống nhất.
 * 
 * Cấu trúc Sheet "Config" (5 cột):
 * | A: ChucNang | B: TrangThai | C: TieuDe | D: TuNgay | E: DenNgay |
 */
class ConfigService {
    private GoogleSheetClient $client;
    private static ?array $allConfigs = null;

    public function __construct() {
        $this->client = GoogleSheetClient::getInstance();
    }

    /**
     * Đọc tất cả config từ Sheet (có cache)
     */
    private function fetchAll(): array {
        if (self::$allConfigs !== null) {
            return self::$allConfigs;
        }

        $cacheKey = 'config_all';
        $cached = $this->client->getCacheManager()->get($cacheKey, CACHE_TTL_CONFIG);
        if ($cached !== null) {
            self::$allConfigs = $cached;
            return $cached;
        }

        try {
            $response = $this->client->getService()->spreadsheets_values->get(
                $this->client->getSpreadsheetId(),
                SHEET_CONFIG
            );
            $values = $response->getValues() ?: [];

            $configs = [];
            foreach ($values as $row) {
                $featureName = mb_strtolower(trim($row[0] ?? ''));
                if (empty($featureName)) continue;

                $configs[$featureName] = [
                    'TrangThai' => trim($row[1] ?? 'Đóng'),
                    'TieuDeDot' => trim($row[2] ?? ''),
                    'TuNgay'    => trim($row[3] ?? ''),
                    'DenNgay'   => trim($row[4] ?? ''),
                ];
            }

            $this->client->getCacheManager()->set($cacheKey, $configs);
            self::$allConfigs = $configs;
            return $configs;
        } catch (Exception $e) {
            error_log("Google Sheets Error ConfigService::fetchAll: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Lấy cấu hình cho 1 chức năng cụ thể
     */
    public function getFeatureConfig(string $featureName): array {
        $all = $this->fetchAll();
        $key = mb_strtolower(trim($featureName));

        if (isset($all[$key])) {
            return $all[$key];
        }

        return [
            'TrangThai' => 'Đóng',
            'TieuDeDot' => '',
            'TuNgay'    => '',
            'DenNgay'   => '',
        ];
    }

    /**
     * Kiểm tra nhanh một chức năng có đang mở không
     * Xét cả TrangThai = "Mở" VÀ ngày hiện tại nằm trong khoảng TuNgay - DenNgay (nếu có)
     */
    public function isOpen(string $featureName): bool {
        $config = $this->getFeatureConfig($featureName);

        if (mb_strtolower($config['TrangThai']) !== 'mở') {
            return false;
        }

        $now = time();

        if (!empty($config['TuNgay'])) {
            $from = $this->parseDate($config['TuNgay']);
            if ($from && $now < $from) return false;
        }

        if (!empty($config['DenNgay'])) {
            $to = $this->parseDate($config['DenNgay']);
            if ($to && $now > ($to + 86399)) return false;
        }

        return true;
    }

    /**
     * Parse ngày từ chuỗi dd/mm/yyyy thành timestamp
     */
    private function parseDate(string $dateStr): ?int {
        $dt = DateTime::createFromFormat('d/m/Y', trim($dateStr));
        if ($dt) {
            $dt->setTime(0, 0, 0);
            return $dt->getTimestamp();
        }
        return null;
    }
}
