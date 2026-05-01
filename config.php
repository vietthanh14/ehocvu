<?php
// Tệp cấu hình hệ thống (config.php)

// Múi giờ Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Cấu hình an toàn tương thích tốt với Cloudflare và cPanel
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly'  => true,
    'samesite' => 'Lax'
]);

// Vui lòng cập nhật ID của Google Sheet nằm ở trên URL
// Ví dụ: https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit -> ID là: 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms
define('SPREADSHEET_ID', '1ksjiYCVIDqfdsWTx260YOpBGqj83PrcM5MCdZQyL5j0');

// Đường dẫn tới file JSON xác thực của Service Account
define('GOOGLE_AUTH_JSON_PATH', __DIR__ . '/config/credentials/serious-app-415103-05cba52d248a.json');

// === Cấu hình Tên Sheet ===
// Chú ý: Đảm bảo tên sheet trong Google Spreadsheet giống y hệt chuỗi này
define('SHEET_STUDENT_LIST', 'Sheet1');
define('SHEET_EXPELLED_LIST', 'Sheet2');
define('SHEET_REQUEST_LIST', 'Sheet3');
define('SHEET_NOTIFICATION', 'ThongBao');

// === Cấu hình đợt đăng ký (Sheet Config thống nhất) ===
// Sheet "Config" chứa tất cả cấu hình đợt, mỗi chức năng 1 dòng
// Cấu trúc: ChucNang | TrangThai | TieuDe | TuNgay | DenNgay
define('SHEET_CONFIG', 'Config!A2:E');
define('CACHE_TTL_CONFIG',            300);   // 5 phút

// Cấu hình tính năng Hủy học phần
define('SHEET_COURSES_CATALOG', 'DanhSachMonHoc!A2:B');
define('SHEET_HUY_HOC_PHAN_REQUESTS', 'HuyHocPhan_Requests');

// Cấu hình tính năng Lễ Tốt Nghiệp (bao gồm cả đăng ký ép plastic / bản sao)
define('SHEET_LE_TOT_NGHIEP_REQUESTS', 'LeTotNghiep_Requests');
define('SHEET_DS_TOT_NGHIEP', 'DS_TotNghiep!A2:A');

// URL của Google Apps Script Web App (dùng để upload file đơn đăng ký)
// Hướng dẫn: Xem file docs/upload_appscript.js → Deploy trên script.google.com → Dán URL vào đây
define('UPLOAD_SCRIPT_URL', 'https://script.google.com/macros/s/AKfycbzJD9-mnr_KLfq8XNiunIH6l9WIpebM8eJ-Z-TaA1GG4ySsU_vem6QAier_klhL5ZY/exec');

// === Cấu hình Cache TTL (giây) ===
// Tập trung tất cả TTL tại đây để tránh trùng lặp giữa các Service
define('CACHE_TTL_STUDENT_LIST',     600);   // 10 phút
define('CACHE_TTL_EXPELLED_LIST',    900);   // 15 phút
define('CACHE_TTL_NOTIFICATIONS',    120);   // 2 phút
define('CACHE_TTL_REQUESTS',         120);   // 2 phút
define('CACHE_TTL_COURSES_CATALOG',  86400); // 24 giờ
define('CACHE_TTL_HHP_REQUESTS',     180);   // 3 phút
define('CACHE_TTL_LTN_REQUESTS',     180);   // 3 phút
define('CACHE_TTL_DS_TOT_NGHIEP',    900);   // 15 phút
