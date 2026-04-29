<?php
require_once __DIR__ . '/../core/StudentRepository.php';
require_once __DIR__ . '/../core/BaoLuuService.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/ApiHandler.php';

$student = ApiHandler::init();
$maSv = $student['ma_sv'];

// Chỉ lấy phần user nhập từ POST
$loaiYeuCau = trim($_POST['loai_yeu_cau'] ?? '');
$lyDo = trim($_POST['ly_do'] ?? '');
$thoiGianBaoLuuDen = trim($_POST['thoi_gian_bao_luu_den'] ?? '');

// Sanitize chống Formula Injection (chỉ các trường user nhập tay)
$lyDo = Security::sanitizeForSheet($lyDo);

// Chuyển đổi ngày bảo lưu từ yyyy-mm-dd sang dd/mm/yyyy
if (!empty($thoiGianBaoLuuDen)) {
    $dt = DateTime::createFromFormat('Y-m-d', $thoiGianBaoLuuDen);
    if ($dt) {
        $thoiGianBaoLuuDen = $dt->format('d/m/Y');
    }
}

if (empty($loaiYeuCau) || empty($lyDo)) {
    Response::error('Vui lòng điền đầy đủ các thông tin bắt buộc.');
}

// === Kiểm tra nghiệp vụ TRƯỚC khi upload ===
$studentRepo = new StudentRepository();
$baoLuuService = new BaoLuuService();

if ($studentRepo->isExpelled($maSv)) {
    Response::error('Lỗi: Sinh viên đang thuộc diện bị xóa tên!');
}

$eligibility = $baoLuuService->getStudentSubmitEligibility($maSv);

if (in_array($loaiYeuCau, $eligibility['pendingTypes'])) {
    Response::error('Bạn đã có đơn "' . htmlspecialchars($loaiYeuCau) . '" đang chờ xử lý. Vui lòng chờ kết quả trước khi nộp đơn mới cùng loại.');
}

if ($loaiYeuCau === 'Bảo lưu kết quả học tập' && !$eligibility['canSubmitBaoLuu']) {
    $reason = $eligibility['isTiepTucHocPending'] ? 'đang có đơn xin Tiếp tục học chờ duyệt' : 'đang trong thời gian bảo lưu';
    Response::error("Thao tác bị từ chối: Bạn $reason nên không thể nộp thêm đơn bảo lưu mới.");
}

if ($loaiYeuCau === 'Tiếp tục học sau bảo lưu' && !$eligibility['canSubmitTiepTuc']) {
    Response::error('Thao tác bị từ chối: Không có Quyết định bảo lưu hợp lệ để tiến hành tiếp tục học.');
}

// === Chống double-submit (Lock 30s) ===
Security::checkSessionLock('submit_baoluu', 30, $maSv);

// === Upload file đơn đăng ký (bắt buộc) — CHỈ SAU KHI pass hết validation ===
$linkDonDangKy = ApiHandler::handleFileUpload('file_don', $maSv, true);

// === Ghi vào Google Sheet (atomic batch) ===
$data = [
    'ma_sv'        => $student['ma_sv'],
    'ho_ten'       => $student['ho_ten'],
    'ngay_sinh'    => $student['ngay_sinh'],
    'sdt'          => $student['sdt'],
    'ten_khoa'     => $student['ten_khoa'],
    'ten_he'       => $student['ten_he'],
    'ten_lop'      => $student['ten_lop'],
    'chuyen_nganh' => $student['chuyen_nganh'],
    'nien_khoa'    => $student['nien_khoa'],
    'loai_yeu_cau'          => $loaiYeuCau,
    'thoi_gian_bao_luu_den' => $thoiGianBaoLuuDen,
    'link_don_dang_ky'      => $linkDonDangKy,
    'ly_do'                 => $lyDo
];

// Ghi dữ liệu vào Google Sheet
$success = $baoLuuService->appendRequest($data);

if ($success) {
    Response::success('Đã nộp đơn yêu cầu "' . htmlspecialchars($loaiYeuCau) . '" thành công. Vui lòng chờ phản hồi từ nhà trường.');
} else {
    Response::error('Có lỗi xảy ra khi lưu dữ liệu. Vui lòng thử lại sau.');
}

