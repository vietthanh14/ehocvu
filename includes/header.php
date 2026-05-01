<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../core/Security.php'; ?>
    <meta name="csrf-token" content="<?= Security::generateCsrfToken() ?>">
    <title>ĐH Hạ Long - Bảng điều khiển</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/main.js"></script>
    <link href="assets/dashboard.css" rel="stylesheet">
    <script>
        // Chống FOUC: Set theme ngay khi tải trang
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
</head>
<body>

    <!-- Topbar -->
    <header class="topbar">
        <div style="display: flex; align-items: center; gap: 12px;">
            <button class="btn-menu-toggle" id="menuToggle" aria-label="Mở menu"><i class="fas fa-bars"></i></button>
            <div class="topbar-brand">
                <img src="assets/logo.png" style="height: 38px; width: auto; object-fit: contain;" alt="Logo">
                <span class="brand-text" style="margin-left: 8px; text-transform: uppercase; font-size: 1.05rem; letter-spacing: 0.03em;">Đại học Hạ Long</span>
            </div>
        </div>
        <div class="topbar-user" style="display: flex; align-items: center;">
            <button id="themeToggleBtn" aria-label="Đổi giao diện" style="background: none; border: none; color: var(--text-mid); font-size: 1.2rem; cursor: pointer; margin-right: 16px; padding: 4px; transition: var(--transition);">
                <i class="fas fa-moon" id="themeToggleIcon"></i>
            </button>
            <span>Xin chào, <strong><?= htmlspecialchars($student['ho_ten'] ?? '') ?></strong></span>
        </div>
    </header>
