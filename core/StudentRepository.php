<?php
require_once __DIR__ . '/GoogleSheetClient.php';

class StudentRepository {
    private GoogleSheetClient $client;
    
    // Config values from old service
    private const CACHE_TTL = [
        'student_list' => 600,
        'expelled_list' => 900,
        'notifications' => 120,
        'requests' => 120
    ];

    public function __construct() {
        $this->client = GoogleSheetClient::getInstance();
    }

    private function fetchStudentListSheet(): ?array {
        return $this->client->fetchSheetDataCached('student_list', SHEET_STUDENT_LIST, self::CACHE_TTL['student_list']);
    }

    private function fetchExpelledListSheet(): ?array {
        return $this->client->fetchSheetDataCached('expelled_list', SHEET_EXPELLED_LIST, self::CACHE_TTL['expelled_list']);
    }

    private function fetchNotificationSheet(): ?array {
        return $this->client->fetchSheetDataCached('notifications', SHEET_NOTIFICATION, self::CACHE_TTL['notifications'], true);
    }
    
    private function fetchRequestListSheet(): ?array {
        return $this->client->fetchSheetDataCached('requests_all', SHEET_REQUEST_LIST, self::CACHE_TTL['requests']);
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
            
            // In-place cache update
            while (count($values[$targetRow - 1]) <= $sdtIndex) {
                $values[$targetRow - 1][] = '';
            }
            $values[$targetRow - 1][$sdtIndex] = $newPhone;
            $this->client->getCacheManager()->set('student_list', $values);
            
            // --- SYNC PHONE TO SHEET 3 ---
            try {
                $reqValues = $this->fetchRequestListSheet();
                if (!empty($reqValues)) {
                    // Column index 4 is SDT for Request List
                    $SDT_COL_INDEX = 4;
                    $MA_SV_COL_INDEX = 1;

                    $sheetParts3 = explode('!', SHEET_REQUEST_LIST);
                    $sheet3Name = $sheetParts3[0];
                    $sdtColChar3 = chr(65 + $SDT_COL_INDEX); // 'E'
                    
                    $batchData = [];
                    $hasChange = false;

                    foreach ($reqValues as $i => $r) {
                        if ($i === 0) continue;
                        $reqMaSv = isset($r[$MA_SV_COL_INDEX]) ? trim($r[$MA_SV_COL_INDEX]) : '';
                        if (strtolower($reqMaSv) === strtolower(trim($maSv))) {
                            $rowNum = $i + 1;
                            $range3 = $sheet3Name . '!' . $sdtColChar3 . $rowNum;
                            $batchData[] = new \Google_Service_Sheets_ValueRange([
                                'range' => $range3,
                                'values' => [[$newPhone]]
                            ]);
                            
                            while (count($reqValues[$i]) <= $SDT_COL_INDEX) {
                                $reqValues[$i][] = '';
                            }
                            $reqValues[$i][$SDT_COL_INDEX] = $newPhone;
                            $hasChange = true;
                        }
                    }

                    if (!empty($batchData)) {
                        $batchBody = new \Google_Service_Sheets_BatchUpdateValuesRequest([
                            'valueInputOption' => 'USER_ENTERED',
                            'data' => $batchData
                        ]);
                        $this->client->getService()->spreadsheets_values->batchUpdate($this->client->getSpreadsheetId(), $batchBody);
                        
                        if ($hasChange) {
                            $this->client->getCacheManager()->set('requests_all', $reqValues);
                        }
                    }
                }
            } catch (Exception $eSync) {
                error_log("Google Sheets Sync Error (Sheet3): " . $eSync->getMessage());
            }

            return true;
        } catch (Exception $e) {
            error_log("Google Sheets Error updateStudentPhone: " . $e->getMessage());
            return false;
        }
    }
}
