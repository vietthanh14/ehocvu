<?php
require_once __DIR__ . '/../core/GoogleSheetService.php';
require_once __DIR__ . '/../core/DriveUploader.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/ApiHandler.php';

$student = ApiHandler::init();
$maSv = $student['ma_sv'];

// === Lấy dữ liệu từ form ===
$danhSachMon = trim($_POST['danh_sach_mon'] ?? '');
$lyDo        = trim($_POST['ly_do'] ?? '');

// Sanitize chống Formula Injection
$danhSachMon = Security::sanitizeForSheet($danhSachMon);
$lyDo        = Security::sanitizeForSheet($lyDo);

if (empty($danhSachMon) || empty($lyDo)) {
    Response::error('Vui lòng điền đầy đủ danh sách môn cần hủy và lý do.');
}

$service = GoogleSheetService::getInstance();

// === Gate Check: Kiểm tra Đợt mở ===
$config = $service->getHuyHocPhanConfig();

if (mb_strtolower(trim($config['TrangThai'])) !== 'mở') {
    Response::error('Đợt đăng ký hủy học phần hiện đã đóng.');
}

// Kiểm tra thời gian (TuNgay - DenNgay)
$today = strtotime(date('Y-m-d'));
$tuNgay = strtotime(str_replace('/', '-', $config['TuNgay']));
$denNgay = strtotime(str_replace('/', '-', $config['DenNgay']));

if ($tuNgay && $denNgay && ($today < $tuNgay || $today > $denNgay)) {
    Response::error('Thời gian nộp đơn đã kết thúc (Từ ' . $config['TuNgay'] . ' đến ' . $config['DenNgay'] . ').');
}

$tieuDeDot = $config['TieuDeDot'];

// === Kiểm tra trùng: Mỗi SV chỉ nộp 1 đơn / đợt ===
if ($service->checkHuyHocPhanSubmitted($maSv, $tieuDeDot)) {
    Response::error('Bạn đã nộp đơn hủy học phần trong đợt "' . htmlspecialchars($tieuDeDot) . '" rồi. Không thể nộp thêm.');
}

// === Chống Double-submit ===
Security::checkSessionLock('submit_huyhocphan', 30, $maSv);

// === Upload file minh chứng (Tùy chọn) ===
$linkMinhChung = '';
$hasFile = isset($_FILES['file_minh_chung']) && $_FILES['file_minh_chung']['error'] === UPLOAD_ERR_OK;

if ($hasFile) {
    $file = $_FILES['file_minh_chung'];
    ApiHandler::validateUploadFile($file);

    session_write_close();
    $uploadResult = DriveUploader::upload($file, $maSv, UPLOAD_SCRIPT_URL);
    session_start();

    if ($uploadResult && $uploadResult['success']) {
        $linkMinhChung = $uploadResult['fileUrl'];
    } else {
        $errorMsg = $uploadResult['message'] ?? 'Lỗi không xác định khi upload file.';
        Response::error('Lỗi upload file: ' . $errorMsg);
    }
}

// === Ghi dữ liệu vào Google Sheet ===
$data = [
    'ma_sv'           => $student['ma_sv'],
    'ho_ten'          => $student['ho_ten'],
    'khoa'            => $student['ten_khoa'] ?? '',
    'he'              => $student['ten_he'] ?? '',
    'nganh'           => $student['chuyen_nganh'] ?? '',
    'lop'             => $student['ten_lop'] ?? '',
    'tieu_de_dot'     => $tieuDeDot,
    'danh_sach_mon'   => $danhSachMon,
    'ly_do'           => $lyDo,
    'link_minh_chung' => $linkMinhChung,
];

$success = $service->appendHuyHocPhanRequest($data);

if ($success) {
    Response::success('Đã nộp đơn đề nghị hủy học phần thành công. Vui lòng chờ phản hồi từ phòng Đào tạo.');
} else {
    Response::error('Có lỗi xảy ra khi lưu dữ liệu. Vui lòng thử lại sau.');
}
