<?php
require_once __DIR__ . '/GoogleSheetClient.php';

class StudentRepository {
    private GoogleSheetClient $client;

    public function __construct() {
        $this->client = GoogleSheetClient::getInstance();
    }

    private function fetchStudentListSheet(): ?array {
        return $this->client->fetchSheetDataCached('student_list', SHEET_STUDENT_LIST, CACHE_TTL_STUDENT_LIST);
    }

    private function fetchExpelledListSheet(): ?array {
        return $this->client->fetchSheetDataCached('expelled_list', SHEET_EXPELLED_LIST, CACHE_TTL_EXPELLED_LIST);
    }

    private function fetchNotificationSheet(): ?array {
        return $this->client->fetchSheetDataCached('notifications', SHEET_NOTIFICATION, CACHE_TTL_NOTIFICATIONS, true);
    }
    
    private function fetchRequestListSheet(): ?array {
        return $this->client->fetchSheetDataCached('requests_all', SHEET_REQUEST_LIST, CACHE_TTL_REQUESTS);
    }

    public function getStudentInfo(string $maSv): ?array {
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
                        
                        $isTargeted = (strtolower($nMaSv) === strtolower($currentMaSv) || strtoupper($nMaSv) === 'ALL' || $nMaSv === '*');
                        
                        if ($isTargeted && $nMsg !== '') {
                            $safeMsg = htmlspecialchars($nMsg, ENT_QUOTES, 'UTF-8');
                            $processedMsg = preg_replace_callback(
                                '#\bhttps?://[^\s()<>]+#i',
                                function($matches) {
                                    $url = $matches[0];
                                    return '<a href="'.$url.'" target="_blank" style="text-decoration: underline; color: #991b1b; font-weight: 700; background: rgba(255,255,255,0.4); padding: 2px 6px; border-radius: 4px;">[<i class="fas fa-external-link-alt" style="font-size: 0.8rem;"></i> Truy cập Link]</a>';
                                },
                                $safeMsg
                            );
                            $messages[] = nl2br($processedMsg);
                        }
                    }
                    if (!empty($messages)) {
                        $studentData['thong_bao_rieng'] = implode("<hr style='border: 0; border-top: 1px dashed rgba(239, 68, 68, 0.3); margin: 12px 0;'>", $messages);
                    }
                }

                return $studentData;
            }
        }

        return null;
    }

    public function isExpelled(string $maSv): bool {
        $values = $this->fetchExpelledListSheet();
        if (empty($values)) return false;

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

    public function updateStudentPhone(string $maSv, string $newPhone): bool {
        $values = $this->fetchStudentListSheet();
        if (empty($values)) return false;

        $headers = $values[0];
        $maSvIndex = -1;
        $sdtIndex = -1;

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
                $targetRow = $index + 1; // 1-based index
                break;
            }
        }

        if ($targetRow === -1) {
            return false;
        }

        $colChar = '';
        $temp = $sdtIndex;
        while ($temp >= 0) {
            $colChar = chr(65 + ($temp % 26)) . $colChar;
            $temp = intdiv($temp, 26) - 1;
        }

        $sheetParts = explode('!', SHEET_STUDENT_LIST);
        $sheetName = $sheetParts[0];
        $updateRange = $sheetName . '!' . $colChar . $targetRow;

        try {
            $this->client->updateRowInSheet($updateRange, [$newPhone]);
            
            // Clear cache để fetch lại data mới nhất từ sheet
            $this->client->getCacheManager()->clear('student_list');

            return true;
        } catch (Exception $e) {
            error_log("Google Sheets Error updateStudentPhone: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cập nhật Họ tên sinh viên trên Google Sheets
     */
    public function updateStudentName(string $maSv, string $newName): bool {
        $values = $this->fetchStudentListSheet();
        if (empty($values)) return false;

        $headers = $values[0];
        $maSvIndex = -1;
        $nameIndex = -1;

        foreach ($headers as $colIndex => $colName) {
            $n = strtolower(trim($colName));
            if ($maSvIndex === -1 && (strpos($n, 'mã sv') !== false || strpos($n, 'mã sinh viên') !== false || strpos($n, 'ma sv') !== false)) {
                $maSvIndex = $colIndex;
            }
            if ($nameIndex === -1 && (strpos($n, 'họ tên') !== false || strpos($n, 'họ và tên') !== false || strpos($n, 'ho ten') !== false)) {
                $nameIndex = $colIndex;
            }
        }

        if ($maSvIndex === -1 || $nameIndex === -1) {
            error_log("Google Sheets Error: Không tìm thấy cột Mã SV hoặc Họ tên");
            return false;
        }

        $targetRow = -1;
        foreach ($values as $index => $row) {
            if ($index === 0) continue;
            $currentMaSv = isset($row[$maSvIndex]) ? trim($row[$maSvIndex]) : '';
            if (strtolower($currentMaSv) === strtolower(trim($maSv))) {
                $targetRow = $index + 1;
                break;
            }
        }

        if ($targetRow === -1) return false;

        $colChar = '';
        $temp = $nameIndex;
        while ($temp >= 0) {
            $colChar = chr(65 + ($temp % 26)) . $colChar;
            $temp = intdiv($temp, 26) - 1;
        }

        $sheetParts = explode('!', SHEET_STUDENT_LIST);
        $sheetName = $sheetParts[0];
        $updateRange = $sheetName . '!' . $colChar . $targetRow;

        try {
            $this->client->updateRowInSheet($updateRange, [$newName]);
            $this->client->getCacheManager()->clear('student_list');
            return true;
        } catch (Exception $e) {
            error_log("Google Sheets Error updateStudentName: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cập nhật Ngày sinh sinh viên trên Google Sheets
     */
    public function updateStudentDob(string $maSv, string $newDob): bool {
        $values = $this->fetchStudentListSheet();
        if (empty($values)) return false;

        $headers = $values[0];
        $maSvIndex = -1;
        $dobIndex = -1;

        foreach ($headers as $colIndex => $colName) {
            $n = strtolower(trim($colName));
            if ($maSvIndex === -1 && (strpos($n, 'mã sv') !== false || strpos($n, 'mã sinh viên') !== false || strpos($n, 'ma sv') !== false)) {
                $maSvIndex = $colIndex;
            }
            if ($dobIndex === -1 && (strpos($n, 'ngày sinh') !== false || strpos($n, 'ngay sinh') !== false || strpos($n, 'năm sinh') !== false)) {
                $dobIndex = $colIndex;
            }
        }

        if ($maSvIndex === -1 || $dobIndex === -1) {
            error_log("Google Sheets Error: Không tìm thấy cột Mã SV hoặc Ngày sinh");
            return false;
        }

        $targetRow = -1;
        foreach ($values as $index => $row) {
            if ($index === 0) continue;
            $currentMaSv = isset($row[$maSvIndex]) ? trim($row[$maSvIndex]) : '';
            if (strtolower($currentMaSv) === strtolower(trim($maSv))) {
                $targetRow = $index + 1;
                break;
            }
        }

        if ($targetRow === -1) return false;

        $colChar = '';
        $temp = $dobIndex;
        while ($temp >= 0) {
            $colChar = chr(65 + ($temp % 26)) . $colChar;
            $temp = intdiv($temp, 26) - 1;
        }

        $sheetParts = explode('!', SHEET_STUDENT_LIST);
        $sheetName = $sheetParts[0];
        $updateRange = $sheetName . '!' . $colChar . $targetRow;

        try {
            $this->client->updateRowInSheet($updateRange, [$newDob]);
            $this->client->getCacheManager()->clear('student_list');
            return true;
        } catch (Exception $e) {
            error_log("Google Sheets Error updateStudentDob: " . $e->getMessage());
            return false;
        }
    }
}
