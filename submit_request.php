<?php
require_once __DIR__ . '/GoogleSheetService.php';

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

// === Chống double-submit ===
$submitKey = 'last_submit_' . md5($maSv . $loaiYeuCau);
$now = time();
if (isset($_SESSION[$submitKey]) && ($now - $_SESSION[$submitKey]) < 30) {
    echo json_encode([
        'success' => false,
        'message' => 'Bạn vừa nộp đơn cách đây chưa đầy 30 giây. Vui lòng chờ một chút.'
    ]);
    exit;
}

// === Upload file CHỈ SAU KHI pass hết validation ===
$linkDonDangKy = '';
if ($hasFile) {
    $uploadResult = uploadToGoogleDrive($file, $maSv);

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

$closePrevious = ($loaiYeuCau === 'Tiếp tục học sau bảo lưu');
$success = $service->submitRequestAtomic($data, $closePrevious);

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

// =============================================
//  HELPER: Upload file lên Google Drive
// =============================================
function uploadToGoogleDrive($file, $maSv) {
    $scriptUrl = UPLOAD_SCRIPT_URL;

    if (empty($scriptUrl) || $scriptUrl === 'YOUR_APPS_SCRIPT_URL_HERE') {
        error_log("UPLOAD_SCRIPT_URL chưa được cấu hình trong config.php");
        return ['success' => false, 'message' => 'Hệ thống upload chưa được cấu hình. Vui lòng liên hệ quản trị viên.'];
    }

    $fileContent = file_get_contents($file['tmp_name']);
    $fileBase64 = base64_encode($fileContent);

    $payload = json_encode([
        'fileName'   => $file['name'],
        'mimeType'   => $file['type'],
        'fileBase64' => $fileBase64,
        'maSv'       => $maSv
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $scriptUrl,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,  // Apps Script redirects
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("cURL error uploading to Drive: $error");
        return ['success' => false, 'message' => 'Lỗi kết nối đến Google Drive: ' . $error];
    }

    $result = json_decode($response, true);

    if (!$result) {
        error_log("Invalid response from Apps Script: HTTP $httpCode - $response");
        return ['success' => false, 'message' => 'Phản hồi không hợp lệ từ Google Drive.'];
    }

    return $result;
}
