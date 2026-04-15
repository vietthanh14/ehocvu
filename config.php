<?php
// Tệp cấu hình hệ thống (config.php)

// Múi giờ Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Vui lòng cập nhật ID của Google Sheet nằm ở trên URL
// Ví dụ: https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit -> ID là: 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms
define('SPREADSHEET_ID', '1ksjiYCVIDqfdsWTx260YOpBGqj83PrcM5MCdZQyL5j0');

// Đường dẫn tới file JSON xác thực của Service Account
define('GOOGLE_AUTH_JSON_PATH', __DIR__ . '/serious-app-415103-baf99d68d251.json');

// Tên các Sheet (Tab) trong Google Sheet của bạn
// Bạn cần đổi tên này cho khớp chính xác với tên Tab trên Google Sheet
define('SHEET_STUDENT_LIST', 'Sheet1');
define('SHEET_EXPELLED_LIST', 'Sheet2');
define('SHEET_REQUEST_LIST', 'Sheet3'); // Nơi lưu trữ các đơn bảo lưu / tiếp tục học

// URL của Google Apps Script Web App (dùng để upload file đơn đăng ký)
// Hướng dẫn: Xem file docs/upload_appscript.js → Deploy trên script.google.com → Dán URL vào đây
define('UPLOAD_SCRIPT_URL', 'https://script.google.com/macros/s/AKfycbzJD9-mnr_KLfq8XNiunIH6l9WIpebM8eJ-Z-TaA1GG4ySsU_vem6QAier_klhL5ZY/exec');

