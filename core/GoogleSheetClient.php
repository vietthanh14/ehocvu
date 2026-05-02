<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/CacheManager.php';

class GoogleSheetClient {
    private static ?self $instance = null;

    /** @var \Google_Service_Sheets */
    private $service;

    /** @var string */
    private $spreadsheetId;

    /** @var CacheManager */
    private CacheManager $cacheManager;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $client = new \Google_Client();
        $client->setApplicationName('QL Bao Luu App');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $client->setAuthConfig(GOOGLE_AUTH_JSON_PATH);
        $client->setAccessType('offline');

        $this->service = new \Google_Service_Sheets($client);
        $this->spreadsheetId = SPREADSHEET_ID;

        $this->cacheManager = new CacheManager();
    }

    /**
     * Get the underlying Google_Service_Sheets instance if needed
     */
    public function getService(): \Google_Service_Sheets {
        return $this->service;
    }

    /**
     * Get the configured spreadsheet ID
     */
    public function getSpreadsheetId(): string {
        return $this->spreadsheetId;
    }

    /**
     * Get CacheManager instance
     */
    public function getCacheManager(): CacheManager {
        return $this->cacheManager;
    }

    /**
     * Fetch data from Google Sheets with caching
     */
    public function fetchSheetDataCached(string $cacheKey, string $range, int $ttl, bool $returnEmptyArrayOnFail = false): ?array {
        $cached = $this->cacheManager->get($cacheKey, $ttl);
        if ($cached !== null) {
            return $cached;
        }

        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
                $values = $response->getValues() ?: [];
                $this->cacheManager->set($cacheKey, $values);
                return empty($values) && !$returnEmptyArrayOnFail ? null : $values;
            } catch (Exception $e) {
                if ($attempt < $maxRetries && (strpos($e->getMessage(), '429') !== false || strpos($e->getMessage(), '503') !== false)) {
                    sleep($attempt); // Exponential backoff: wait 1s, 2s...
                    continue;
                }
                error_log("Google Sheets Error fetching $cacheKey ($range) [Attempt $attempt]: " . $e->getMessage());
                if ($returnEmptyArrayOnFail) return [];
                throw new Exception("Lỗi kết nối Google Sheets khi lấy dữ liệu: " . $e->getMessage());
            }
        }
        return null;
    }

    /**
     * Append a new row to a sheet
     */
    public function appendRowToSheet(string $range, array $rowData): bool {
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $body = new \Google_Service_Sheets_ValueRange(['values' => [$rowData]]);
                $params = ['valueInputOption' => 'USER_ENTERED'];
                $result = $this->service->spreadsheets_values->append($this->spreadsheetId, $range, $body, $params);
                return $result->getUpdates() != null;
            } catch (Exception $e) {
                if ($attempt < $maxRetries && (strpos($e->getMessage(), '429') !== false || strpos($e->getMessage(), '503') !== false)) {
                    sleep($attempt);
                    continue;
                }
                error_log("Google Sheets Error appendRowToSheet ($range) [Attempt $attempt]: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }

    /**
     * Update a specific cell or row in a sheet
     */
    public function updateRowInSheet(string $range, array $rowData): bool {
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $body = new \Google_Service_Sheets_ValueRange(['values' => [$rowData]]);
                $params = ['valueInputOption' => 'USER_ENTERED'];
                $this->service->spreadsheets_values->update($this->spreadsheetId, $range, $body, $params);
                return true;
            } catch (Exception $e) {
                if ($attempt < $maxRetries && (strpos($e->getMessage(), '429') !== false || strpos($e->getMessage(), '503') !== false)) {
                    sleep($attempt);
                    continue;
                }
                error_log("Google Sheets Error updateRowInSheet ($range) [Attempt $attempt]: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }
}
