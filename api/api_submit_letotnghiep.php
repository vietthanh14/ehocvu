<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/ApiHandler.php';
require_once __DIR__ . '/../core/LeTotNghiepService.php';

$student = ApiHandler::init();
$maSv = $student['ma_sv'];

$xacNhan = trim($_POST['xac_nhan'] ?? '');
$soKhachMoi = trim($_POST['so_khach_moi'] ?? '0');
$ghiChu = trim($_POST['ghi_chu'] ?? '');

// Số lượng dịch vụ
$epVanbang   = (int)($_POST['ep_vanbang'] ?? 0);
$epBangdiem  = (int)($_POST['ep_bangdiem'] ?? 0);
$bansaoBd    = (int)($_POST['bansao_bd'] ?? 0);
$epBansaoBd  = (int)($_POST['ep_bansao_bd'] ?? 0);

// === Validate ===
if (!in_array($xacNhan, ['Tham gia', 'Không tham gia'])) {
    Response::error('Vui lòng chọn xác nhận Tham gia hoặc Không tham gia.');
}

// Nếu không tham gia → chỉ reset khách mời, dịch vụ vẫn giữ
if ($xacNhan === 'Không tham gia') {
    $soKhachMoi = '0';
}

if (!in_array($soKhachMoi, ['0', '1', '2'])) {
    Response::error('Số khách mời không hợp lệ (chỉ được 0, 1 hoặc 2).');
}

// Validate số lượng dịch vụ
foreach (['ep_vanbang' => $epVanbang, 'ep_bangdiem' => $epBangdiem, 'bansao_bd' => $bansaoBd, 'ep_bansao_bd' => $epBansaoBd] as $qty) {
    if ($qty < 0 || $qty > 10) {
        Response::error('Số lượng dịch vụ không hợp lệ (từ 0 đến 10).');
    }
}

// Sanitize
$ghiChu = Security::sanitizeForSheet($ghiChu);

// === Kiểm tra đợt đang mở ===
$service = new LeTotNghiepService();
$config = $service->getConfig();

if (mb_strtolower($config['TrangThai']) !== 'mở') {
    Response::error($config['ThongBaoDong'] ?: 'Đợt đăng ký dự lễ tốt nghiệp hiện không mở.');
}

$tieuDeDot = $config['TieuDeDot'];
if (empty($tieuDeDot)) {
    Response::error('Cấu hình đợt tốt nghiệp chưa đầy đủ. Vui lòng liên hệ Phòng Đào tạo.');
}

// === Kiểm tra danh sách được duyệt tốt nghiệp (Whitelist) ===
if (!$service->isEligible($maSv)) {
    Response::error('Bạn không có tên trong danh sách được công nhận tốt nghiệp đợt này. Vui lòng liên hệ Phòng Đào tạo nếu có sai sót.');
}

// === Chống double-submit ===
Security::checkSessionLock('submit_ltn', 10, $maSv);

// === Kiểm tra đã đăng ký chưa → update hoặc insert ===
$existing = $service->findRegistration($maSv, $tieuDeDot);

$submitData = [
    'xac_nhan'     => $xacNhan,
    'so_khach_moi' => $soKhachMoi,
    'ep_vanbang'   => $epVanbang,
    'ep_bangdiem'  => $epBangdiem,
    'bansao_bd'    => $bansaoBd,
    'ep_bansao_bd' => $epBansaoBd,
    'ghi_chu'      => $ghiChu,
];

if ($existing !== null) {
    // Đã có bản ghi → cập nhật (đổi ý)
    $success = $service->updateRegistration($maSv, $tieuDeDot, $submitData);

    if ($success) {
        $msg = $xacNhan === 'Tham gia'
            ? 'Đã cập nhật xác nhận tham gia Lễ Tốt nghiệp thành công!'
            : 'Đã cập nhật xác nhận không tham gia.';
        Response::success($msg);
    } else {
        Response::error('Có lỗi xảy ra khi cập nhật. Vui lòng thử lại sau.');
    }
} else {
    // Chưa có → thêm mới
    $data = array_merge([
        'ma_sv'        => $student['ma_sv'],
        'ho_ten'       => $student['ho_ten'],
        'ngay_sinh'    => $student['ngay_sinh'],
        'sdt'          => $student['sdt'],
        'ten_khoa'     => $student['ten_khoa'],
        'ten_he'       => $student['ten_he'],
        'ten_lop'      => $student['ten_lop'],
        'chuyen_nganh' => $student['chuyen_nganh'],
        'nien_khoa'    => $student['nien_khoa'],
        'tieu_de_dot'  => $tieuDeDot,
    ], $submitData);

    $success = $service->appendRegistration($data);

    if ($success) {
        $msg = $xacNhan === 'Tham gia'
            ? 'Đã xác nhận tham gia Lễ Tốt nghiệp thành công!'
            : 'Đã ghi nhận xác nhận không tham gia.';
        Response::success($msg);
    } else {
        Response::error('Có lỗi xảy ra khi lưu dữ liệu. Vui lòng thử lại sau.');
    }
}
