    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-section">
            <div class="sidebar-label">Menu chính</div>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php?page=home" class="<?= (isset($page) && $page == 'home') ? 'active' : '' ?>"><i class="fas fa-user"></i> Thông tin cá nhân</a></li>
                <li><a href="dashboard.php?page=baoluu" class="<?= (isset($page) && $page == 'baoluu') ? 'active' : '' ?>"><i class="fas fa-file-circle-plus"></i> Đăng kí thủ tục bảo lưu học lại</a></li>
                <li><a href="dashboard.php?page=huyhocphan" class="<?= (isset($page) && $page == 'huyhocphan') ? 'active' : '' ?>"><i class="fas fa-file-circle-minus"></i> Đề nghị hủy học phần</a></li>
                <li><a href="dashboard.php?page=letotnghiep" class="<?= (isset($page) && $page == 'letotnghiep') ? 'active' : '' ?>"><i class="fas fa-graduation-cap"></i> Đăng ký dự Lễ Tốt nghiệp</a></li>
            </ul>
        </div>
        <div class="sidebar-section" style="margin-top: auto;">
            <div class="sidebar-label">Tài khoản</div>
            <ul class="sidebar-nav">
                <li><a href="logout.php" class="text-danger"><i class="fas fa-arrow-right-from-bracket"></i> Đăng xuất</a></li>
            </ul>
        </div>
    </nav>
