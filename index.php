<?php
require_once __DIR__ . '/config.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
session_start();
if (isset($_SESSION['student'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Hệ thống Quản lý Sinh viên</title>
    <meta name="description" content="Hệ thống đăng ký thủ tục bảo lưu và tiếp tục học dành cho sinh viên">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/main.js"></script>
    <link href="assets/login.css" rel="stylesheet">

</head>
<body>
    <div class="bg-gradient"></div>
    <script>
        for (let i = 0; i < 20; i++) {
            const p = document.createElement('div');
            p.className = 'particle';
            p.style.left = Math.random() * 100 + '%';
            p.style.animationDuration = (Math.random() * 8 + 6) + 's';
            p.style.animationDelay = (Math.random() * 5) + 's';
            p.style.width = p.style.height = (Math.random() * 3 + 1) + 'px';
            document.body.appendChild(p);
        }
    </script>

    <div class="login-wrapper">
        <div class="logo-area">
            <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
            <h1>Cổng Sinh Viên</h1>
            <p>Hệ thống Quản lý Thủ tục Học vụ</p>
        </div>

        <div class="login-card">
            <form id="login-form">
                <div class="form-field">
                    <label for="masv">Mã sinh viên</label>
                    <div class="input-wrapper">
                        <input type="text" id="masv" placeholder="Nhập mã sinh viên của bạn..." required autofocus autocomplete="off">
                        <i class="fas fa-id-card"></i>
                    </div>
                </div>
                <div class="form-field" style="margin-top: 16px;">
                    <label for="ngaysinh">Ngày sinh (Mật khẩu)</label>
                    <div class="input-wrapper">
                        <input type="text" id="ngaysinh" placeholder="VD: 15/08/2002" required autocomplete="off">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="error-msg" id="error-msg"></div>
                <button type="submit" class="btn-login" id="btn-login">
                    <span class="spinner" id="spinner"></span>
                    <i class="fas fa-arrow-right-to-bracket"></i> Tra cứu & Đăng nhập
                </button>
            </form>
        </div>

        <div class="login-footer">
            <p>© 2026 Phòng Đào tạo — Trường Đại học</p>
        </div>
    </div>

    <script>
        document.getElementById('login-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const masv = document.getElementById('masv').value.trim();
            const ngaysinh = document.getElementById('ngaysinh').value.trim();
            const btn = document.getElementById('btn-login');
            const spinner = document.getElementById('spinner');
            const errorMsg = document.getElementById('error-msg');
            if (!masv || !ngaysinh) return;

            btn.disabled = true;
            spinner.style.display = 'inline-block';
            errorMsg.style.display = 'none';

            fetch('api/api_auth.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'ma_sv=' + encodeURIComponent(masv) + '&ngay_sinh=' + encodeURIComponent(ngaysinh)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        btn.innerHTML = '<i class="fas fa-check"></i> Đang chuyển hướng...';
                        setTimeout(() => { window.location.href = 'dashboard.php'; }, 500);
                    } else {
                        btn.disabled = false;
                        spinner.style.display = 'none';
                        AppAlert.error('Đăng nhập thất bại', data.message);
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    spinner.style.display = 'none';
                    AppAlert.error('Lỗi kết nối', 'Không thể kết nối đến máy chủ!');
                });
        });
    </script>
</body>
</html>
