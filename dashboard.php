<?php
session_start();
if (!isset($_SESSION['student'])) {
    header('Location: index.php');
    exit;
}

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
$student = $_SESSION['student'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cổng Sinh Viên - Bảng điều khiển</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* === Dashboard-specific layout === */
        body { background: var(--body-bg); color: var(--text-dark); overflow-x: hidden; }

        /* Topbar */
        .topbar {
            position: fixed; top: 0; left: 0; right: 0; height: 56px;
            background: var(--topbar-bg);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; z-index: 1050;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .topbar-brand {
            display: flex; align-items: center; gap: 10px;
            color: white; font-weight: 700; font-size: 1rem; letter-spacing: -0.01em;
        }
        .topbar-brand .icon-box {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }
        .topbar-brand .icon-box i { color: white; font-size: 14px; }
        .topbar-user {
            display: flex; align-items: center; gap: 8px;
            color: var(--text-light); font-size: 0.85rem;
        }
        .topbar-user strong { color: white; font-weight: 600; }
        .topbar-user .avatar {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 0.8rem;
        }
        .btn-menu-toggle {
            display: none; background: none; border: none;
            color: white; font-size: 1.2rem; cursor: pointer; padding: 4px;
        }

        /* Sidebar */
        .sidebar {
            position: fixed; top: 56px; left: 0; width: 250px;
            height: calc(100vh - 56px);
            background: var(--sidebar-bg); overflow-y: auto; z-index: 1040;
            transition: var(--transition);
            border-right: 1px solid rgba(255,255,255,0.06);
        }
        .sidebar-section { padding: 16px 12px 8px; }
        .sidebar-label {
            font-size: 0.65rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.08em;
            color: rgba(148, 163, 184, 0.5); padding: 0 12px; margin-bottom: 6px;
        }
        .sidebar-nav { list-style: none; padding: 0; }
        .sidebar-nav li a {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 12px; margin: 2px 0; border-radius: 8px;
            color: #94a3b8; text-decoration: none;
            font-size: 0.875rem; font-weight: 500; transition: var(--transition);
        }
        .sidebar-nav li a i { width: 20px; text-align: center; font-size: 0.95rem; }
        .sidebar-nav li a:hover { background: var(--sidebar-hover); color: white; }
        .sidebar-nav li a.active {
            background: var(--sidebar-active); color: var(--primary-light); font-weight: 600;
        }
        .sidebar-nav li a.active i { color: var(--primary-light); }
        .sidebar-nav li a.text-danger { color: #f87171; }
        .sidebar-nav li a.text-danger:hover { background: rgba(239, 68, 68, 0.1); color: #fca5a5; }

        /* Main */
        .main {
            margin-left: 250px; margin-top: 56px; padding: 28px;
            min-height: calc(100vh - 56px); transition: var(--transition);
        }
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px;
        }
        .page-header h2 { font-size: 1.5rem; font-weight: 700; color: var(--text-dark); letter-spacing: -0.02em; }
        .page-header .breadcrumb-text { font-size: 0.8rem; color: var(--text-light); }

        /* Scrollbar */
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

        /* Responsive */
        @media (max-width: 768px) {
            .btn-menu-toggle { display: block; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main { margin-left: 0; padding: 20px 16px; }
            .topbar-user span { display: none; }
            .page-header h2 { font-size: 1.25rem; }
        }
    </style>
</head>
<body>

    <!-- Topbar -->
    <header class="topbar">
        <div style="display: flex; align-items: center; gap: 12px;">
            <button class="btn-menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
            <div class="topbar-brand">
                <div class="icon-box"><i class="fas fa-graduation-cap"></i></div>
                <span>Cổng Sinh Viên</span>
            </div>
        </div>
        <div class="topbar-user">
            <span>Xin chào, <strong><?= htmlspecialchars($student['ho_ten']) ?></strong></span>
            <div class="avatar"><?= mb_substr($student['ho_ten'], -1) ?></div>
        </div>
    </header>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-label">Menu chính</div>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php?page=home" class="<?= $page == 'home' ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i> Bảng điều khiển</a></li>
                <li><a href="dashboard.php?page=profile" class="<?= $page == 'profile' ? 'active' : '' ?>"><i class="fas fa-user"></i> Thông tin cá nhân</a></li>
                <li><a href="dashboard.php?page=dangky" class="<?= $page == 'dangky' ? 'active' : '' ?>"><i class="fas fa-file-circle-plus"></i> Đăng ký thủ tục</a></li>
            </ul>
        </div>
        <div class="sidebar-section" style="margin-top: auto;">
            <div class="sidebar-label">Tài khoản</div>
            <ul class="sidebar-nav">
                <li><a href="logout.php" class="text-danger"><i class="fas fa-arrow-right-from-bracket"></i> Đăng xuất</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main -->
    <main class="main fade-in">
        <?php if ($page == 'home'): ?>
            <div class="page-header">
                <h2>Bảng điều khiển</h2>
                <span class="breadcrumb-text"><i class="fas fa-home"></i> Trang chủ</span>
            </div>
            <div class="notice-card">
                <h6><i class="fas fa-bell"></i> Thông báo quan trọng</h6>
                <ul>
                    <li><strong>Quy định thủ tục:</strong> Việc đăng ký cần được thực hiện đúng theo quy định của Phòng đào tạo.</li>
                    <li><strong>Lưu ý:</strong> Số điện thoại đăng kí zalo không được để chế độ chặn để tiện cho việc liên lạc khi cần.</li>
                </ul>
            </div>
            <div class="section-title">
                <span class="icon-circle teal"><i class="fas fa-layer-group"></i></span>
                Hồ sơ thủ tục của bạn
            </div>
            <div class="card-modern">
                <?php include 'pages/table_lichsu.php'; ?>
            </div>

        <?php elseif ($page == 'profile'): ?>
            <div class="page-header">
                <h2>Thông tin cá nhân</h2>
                <span class="breadcrumb-text"><i class="fas fa-home"></i> Trang chủ / Thông tin</span>
            </div>
            <?php include 'pages/thongtin_canhan.php'; ?>

        <?php elseif ($page == 'dangky'): ?>
            <div class="page-header">
                <h2>Đăng ký thủ tục mới</h2>
                <span class="breadcrumb-text"><i class="fas fa-home"></i> Trang chủ / Đăng ký</span>
            </div>
            <div class="card-modern" style="padding: 28px;">
                <?php include 'pages/form_dangky.php'; ?>
            </div>

        <?php endif; ?>
    </main>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        menuToggle.addEventListener('click', () => sidebar.classList.toggle('show'));
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
    </script>
</body>
</html>
