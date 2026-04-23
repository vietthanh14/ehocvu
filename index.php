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
    <style>
        /* === Login-specific styles === */
        body {
            background: var(--bg-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        .bg-gradient {
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(15, 118, 110, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(20, 184, 166, 0.1) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 80%, rgba(245, 158, 11, 0.05) 0%, transparent 50%);
            animation: bgShift 8s ease-in-out infinite alternate;
        }
        @keyframes bgShift { 0% { opacity: 0.7; } 100% { opacity: 1; } }

        .particle {
            position: fixed;
            width: 2px; height: 2px;
            background: rgba(20, 184, 166, 0.4);
            border-radius: 50%;
            animation: float linear infinite;
        }
        @keyframes float {
            0% { transform: translateY(100vh) scale(0); opacity: 0; }
            10% { opacity: 1; } 90% { opacity: 1; }
            100% { transform: translateY(-10vh) scale(1); opacity: 0; }
        }

        .login-wrapper {
            position: relative; z-index: 10;
            width: 100%; max-width: 440px; padding: 20px;
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .logo-area { text-align: center; margin-bottom: 32px; }
        .logo-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 16px;
            display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 16px;
            box-shadow: var(--shadow-glow);
        }
        .logo-icon i { font-size: 28px; color: white; }
        .logo-area h1 { font-size: 1.5rem; font-weight: 700; color: #f1f5f9; letter-spacing: -0.02em; }
        .logo-area p { color: var(--text-light); font-size: 0.9rem; margin-top: 4px; }

        .login-card {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px; padding: 36px;
            box-shadow: var(--shadow-glow);
        }
        .login-card .form-field label { color: var(--text-light); }
        .login-card .input-wrapper { position: relative; }
        .login-card .input-wrapper i {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            color: var(--text-light); font-size: 1rem; transition: color 0.3s;
        }
        .login-card .input-wrapper input {
            width: 100%; padding: 14px 16px 14px 46px;
            background: rgba(255, 255, 255, 0.05);
            border: 1.5px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px; color: #f1f5f9;
            font-size: 1rem; font-family: 'Inter', sans-serif; font-weight: 500;
            transition: var(--transition); outline: none;
        }
        .login-card .input-wrapper input::placeholder { color: rgba(148, 163, 184, 0.6); }
        .login-card .input-wrapper input:focus {
            border-color: var(--primary-light);
            background: rgba(20, 184, 166, 0.05);
            box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.1);
        }
        .login-card .input-wrapper input:focus ~ i { color: var(--primary-light); }

        .error-msg {
            display: none; padding: 12px 16px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 10px; color: #fca5a5;
            font-size: 0.875rem; margin-bottom: 20px;
            animation: shake 0.4s ease;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }

        .btn-login {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border: none; border-radius: 12px;
            color: white; font-size: 1rem; font-weight: 600;
            font-family: 'Inter', sans-serif; cursor: pointer;
            transition: var(--transition);
        }
        .btn-login:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(20, 184, 166, 0.3);
        }
        .btn-login:disabled { opacity: 0.7; cursor: not-allowed; }
        .btn-login .spinner { display: none; width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,0.3); border-top-color: white;
            border-radius: 50%; animation: spin-anim 0.6s linear infinite;
            margin-right: 8px; vertical-align: middle;
        }

        .login-footer { text-align: center; margin-top: 24px; color: var(--text-light); font-size: 0.8rem; }

        @media (max-width: 480px) {
            .login-card { padding: 28px 24px; }
            .logo-area h1 { font-size: 1.3rem; }
        }
    </style>
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
