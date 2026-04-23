<?php
require_once __DIR__ . '/GoogleSheetService.php';
require_once __DIR__ . '/DriveUploader.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
    exit;
}

// === Kiểm tra đăng nhập ===
if (!isset($_SESSION['student'])) {
    echo json_encode(['success' => false, 'message' => 'Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.']);
    exit;
}

$student = $_SESSION['student'];
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
    echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ các thông tin bắt buộc.']);
    exit;
}

// === Validate file metadata — KHÔNG upload vội ===
$hasFile = isset($_FILES['file_don']) && $_FILES['file_don']['error'] === UPLOAD_ERR_OK;
if ($hasFile) {
    $file = $_FILES['file_don'];
    $maxSize = 10 * 1024 * 1024; // 10MB

    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File quá lớn. Vui lòng chọn file nhỏ hơn 10MB.']);
        exit;
    }

    // Validate MIME type thực tế bằng finfo (không tin client)
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg',
                     'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($realType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Định dạng file không hợp lệ. Chỉ chấp nhận PDF, JPG, PNG, DOC, DOCX.']);
        exit;
    }
} elseif (isset($_FILES['file_don']) && $_FILES['file_don']['error'] !== UPLOAD_ERR_NO_FILE) {
    echo json_encode(['success' => false, 'message' => 'Lỗi khi tải file lên. Mã lỗi: ' . $_FILES['file_don']['error']]);
    exit;
} else {
    // Bắt buộc phải có file minh chứng theo đúng luồng
    echo json_encode(['success' => false, 'message' => 'Lỗi: Vui lòng đính kèm file đơn đăng ký.']);
    exit;
}

// === Kiểm tra nghiệp vụ TRƯỚC khi upload ===
$service = GoogleSheetService::getInstance();

if ($service->isExpelled($maSv)) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: Sinh viên đang thuộc diện bị xóa tên!']);
    exit;
}

$eligibility = $service->getStudentSubmitEligibility($maSv);

// Kiểm tra trùng đơn cùng loại đang chờ
if (in_array($loaiYeuCau, $eligibility['pendingTypes'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Bạn đã có đơn "' . htmlspecialchars($loaiYeuCau) . '" đang chờ xử lý. Vui lòng chờ kết quả trước khi nộp đơn mới cùng loại.'
    ]);
    exit;
}

if ($loaiYeuCau === 'Bảo lưu kết quả học tập' && !$eligibility['canSubmitBaoLuu']) {
    $reason = $eligibility['isTiepTucHocPending'] ? 'đang có đơn xin Tiếp tục học chờ duyệt' : 'đang trong thời gian bảo lưu';
    echo json_encode([
        'success' => false,
        'message' => "Thao tác bị từ chối: Bạn $reason nên không thể nộp thêm đơn bảo lưu mới."
    ]);
    exit;
}

if ($loaiYeuCau === 'Tiếp tục học sau bảo lưu' && !$eligibility['canSubmitTiepTuc']) {
    echo json_encode([
        'success' => false,
        'message' => 'Thao tác bị từ chối: Không có Quyết định bảo lưu hợp lệ để tiến hành tiếp tục học.'
    ]);
    exit;
}

// === Chống double-submit (Lock 30s trên toàn tài khoản) ===
$submitKey = 'last_submit_' . md5($maSv);
$now = time();
if (isset($_SESSION[$submitKey]) && ($now - $_SESSION[$submitKey]) < 30) {
    echo json_encode([
        'success' => false,
        'message' => 'Hệ thống đang xử lý đơn của bạn. Vui lòng không ấn gửi liên tục.'
    ]);
    exit;
}

// Khóa session ngay lập tức để block các request song song (double-click)
$_SESSION[$submitKey] = $now;

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
        echo json_encode(['success' => false, 'message' => 'Lỗi upload file: ' . $errorMsg]);
        exit;
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
    $_SESSION[$submitKey] = $now;

    echo json_encode([
        'success' => true,
        'message' => 'Đã nộp đơn yêu cầu "' . htmlspecialchars($loaiYeuCau) . '" thành công. Vui lòng chờ phản hồi từ nhà trường.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi lưu dữ liệu. Vui lòng thử lại sau.'
    ]);
}

