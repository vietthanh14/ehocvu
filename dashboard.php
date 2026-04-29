<?php
require_once __DIR__ . '/config.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
session_start();
if (!isset($_SESSION['student'])) {
    header('Location: index.php');
    exit;
}

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
$student = $_SESSION['student'];
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main -->
    <main class="main fade-in">
        <?php if ($page == 'home'): ?>
            <div class="page-header">
                <h2>Thông tin cá nhân</h2>
                <span class="breadcrumb-text"><i class="fas fa-home"></i> Trang chủ</span>
            </div>

            <?php if (!empty($student['thong_bao_rieng'])): ?>
            <div class="notice-card" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-left: 4px solid #ef4444; margin-bottom: 24px;">
                <h6 style="color: #b91c1c; font-weight: 700; margin-bottom: 8px;">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 6px;"></i> Thông báo từ Phòng Đào tạo
                </h6>
                <div style="color: #991b1b; font-size: 0.95rem; line-height: 1.5;">
                    <?= $student['thong_bao_rieng'] ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="notice-card">
                <h6><i class="fas fa-bell"></i> Thông báo quan trọng</h6>
                <ul>
                    <li><strong>Quy định thủ tục:</strong> Kiểm tra thông tin cá nhân trước khi đăng ký.</li>
                    <li><strong>Lưu ý:</strong> Số điện thoại đăng kí zalo không được để chế độ chặn để tiện cho việc liên lạc khi cần.</li>
                    <li><strong>Biểu mẫu thủ tục:</strong> Sinh viên tải các biểu mẫu đơn đăng ký <a href="https://drive.google.com/drive/folders/1zs6cYMC95_dpMt29hSVpWhr0As10Y8eA" target="_blank" style="color: #0f766e; font-weight: 600; text-decoration: underline;">tại đây</a>.</li>
                </ul>
            </div>
            <?php include 'pages/thongtin_canhan.php'; ?>

        <?php elseif ($page == 'baoluu'): ?>
            <div class="page-header">
                <h2>Đăng kí thủ tục bảo lưu học lại</h2>
                <span class="breadcrumb-text"><i class="fas fa-home"></i> Trang chủ / Đăng ký</span>
            </div>
            <div class="section-title">
                <span class="icon-circle teal"><i class="fas fa-layer-group"></i></span>
                Lịch sử thủ tục của bạn
            </div>
            <div class="card-modern" style="margin-bottom: 30px;">
                <?php include 'pages/table_lichsu.php'; ?>
            </div>

            <div class="section-title">
                <span class="icon-circle teal"><i class="fas fa-plus-circle"></i></span>
                Tạo đơn đăng ký mới
            </div>
            <div class="card-modern" style="padding: 28px; margin-bottom: 24px;">
                <?php include 'pages/form_baoluu.php'; ?>
            </div>
        <?php elseif ($page == 'huyhocphan'): ?>
            <div class="page-header">
                <h2>Đề nghị hủy học phần</h2>
                <span class="breadcrumb-text"><i class="fas fa-home"></i> Trang chủ / Hủy học phần</span>
            </div>
            <div class="card-modern" style="padding: 28px; margin-bottom: 24px;">
                <?php include 'pages/form_huyhocphan.php'; ?>
            </div>

        <?php endif; ?>
    </main>

<?php include __DIR__ . '/includes/footer.php'; ?>
