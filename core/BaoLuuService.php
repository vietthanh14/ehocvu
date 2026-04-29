<?php
require_once __DIR__ . '/GoogleSheetClient.php';

class BaoLuuService {
    private GoogleSheetClient $client;
    
    private const CACHE_TTL = [
        'requests' => 120
    ];

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

    public function __construct() {
        $this->client = GoogleSheetClient::getInstance();
    }

    private function fetchRequestListSheet(): ?array {
        return $this->client->fetchSheetDataCached('requests_all', SHEET_REQUEST_LIST, self::CACHE_TTL['requests']);
    }

    public function getStudentSubmitEligibility(string $maSv): array {
        $requests = $this->getStudentRequests($maSv);
        $pendingTypes = [];
        $coQDBaoLuu = false;
        $resolvedStateFound = false; 

        foreach ($requests as $req) {
            $ttLower = mb_strtolower(trim($req['trang_thai']));
            $reqType = trim($req['loai_yeu_cau']);
            $isPending = (mb_strpos($ttLower, 'chờ') !== false);

            if ($isPending) {
                $pendingTypes[] = $reqType;
            }

            if (!$resolvedStateFound && !$isPending && (mb_strpos($ttLower, 'duyệt') !== false || mb_strpos($ttLower, 'thành công') !== false || mb_strpos($ttLower, 'xong') !== false)) {
                if ($reqType === 'Bảo lưu kết quả học tập') {
                    $coQDBaoLuu = true;
                } elseif ($reqType === 'Tiếp tục học sau bảo lưu') {
                    $coQDBaoLuu = false;
                }
                $resolvedStateFound = true;
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

        $success = $this->client->appendRowToSheet(SHEET_REQUEST_LIST, $rowData);
        if ($success) {
            $this->client->getCacheManager()->delete('requests_all');
        }
        return $success;
    }
}
