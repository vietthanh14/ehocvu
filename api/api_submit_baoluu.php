<?php
require_once __DIR__ . '/../core/GoogleSheetService.php';
require_once __DIR__ . '/../core/DriveUploader.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/ApiHandler.php';

$student = ApiHandler::init();
$maSv = $student['ma_sv'];

// Chỉ lấy phần user nhập từ POST
$loaiYeuCau = trim($_POST['loai_yeu_cau'] ?? '');
$lyDo = trim($_POST['ly_do'] ?? '');
$thoiGianBaoLuuDen = trim($_POST['thoi_gian_bao_luu_den'] ?? '');

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

// === Validate file metadata — KHÔNG upload vội ===
$hasFile = isset($_FILES['file_don']) && $_FILES['file_don']['error'] === UPLOAD_ERR_OK;
if ($hasFile) {
    $file = $_FILES['file_don'];
    ApiHandler::validateUploadFile($file);
} elseif (isset($_FILES['file_don']) && $_FILES['file_don']['error'] !== UPLOAD_ERR_NO_FILE) {
    Response::error('Lỗi khi tải file lên. Mã lỗi: ' . $_FILES['file_don']['error']);
} else {
    // Bắt buộc phải có file minh chứng theo đúng luồng
    Response::error('Lỗi: Vui lòng đính kèm file đơn đăng ký.');
}

// === Kiểm tra nghiệp vụ TRƯỚC khi upload ===
$service = GoogleSheetService::getInstance();

if ($service->isExpelled($maSv)) {
    Response::error('Lỗi: Sinh viên đang thuộc diện bị xóa tên!');
}

$eligibility = $service->getStudentSubmitEligibility($maSv);

// Kiểm tra trùng đơn cùng loại đang chờ
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

// === Chống double-submit (Lock 30s trên toàn tài khoản) ===
Security::checkSessionLock('submit_baoluu', 30, $maSv);

// === Upload file CHỈ SAU KHI pass hết validation ===
$linkDonDangKy = '';
if ($hasFile) {
    // Mở khóa Session để tránh làm treo các request khác của user trong 5s upload
    session_write_close();
    
    $uploadResult = DriveUploader::upload($file, $maSv, UPLOAD_SCRIPT_URL);

    // Mở lại Session để ghi nhận log thời gian nộp đơn
    session_start();

    if ($uploadResult && $uploadResult['success']) {
        $linkDonDangKy = $uploadResult['fileUrl'];
    } else {
        $errorMsg = $uploadResult['message'] ?? 'Lỗi không xác định khi upload file.';
        Response::error('Lỗi upload file: ' . $errorMsg);
    }
}

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
$success = $service->appendRequest($data);

if ($success) {
    Response::success('Đã nộp đơn yêu cầu "' . htmlspecialchars($loaiYeuCau) . '" thành công. Vui lòng chờ phản hồi từ nhà trường.');
} else {
    Response::error('Có lỗi xảy ra khi lưu dữ liệu. Vui lòng thử lại sau.');
}

