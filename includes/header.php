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

</head>
<body>

    <!-- Topbar -->
    <header class="topbar">
        <div style="display: flex; align-items: center; gap: 12px;">
            <button class="btn-menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
            <div class="topbar-brand">
                <img src="assets/logo.png" style="width: 36px; height: 36px; border-radius: 8px; object-fit: cover;" alt="Logo">
                <span>ĐH Hạ Long</span>
            </div>
        </div>
        <div class="topbar-user">
            <span>Xin chào, <strong><?= htmlspecialchars($student['ho_ten'] ?? '') ?></strong></span>
        </div>
    </header>
