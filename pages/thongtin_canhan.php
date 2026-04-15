<?php
if (!isset($_SESSION['student'])) exit;
$student = $_SESSION['student'];
?>
<div class="profile-grid">
    <div class="profile-section">
        <div class="profile-section-header">
            <div class="icon-sm teal"><i class="fas fa-id-card"></i></div>
            <h5>Thông tin chung</h5>
        </div>
        <div class="profile-row">
            <div class="lbl">Họ và tên</div>
            <div class="val" style="font-weight:700;"><?= htmlspecialchars($student['ho_ten']) ?></div>
        </div>
        <div class="profile-row">
            <div class="lbl">Mã sinh viên</div>
            <div class="val"><span class="tag"><?= htmlspecialchars($student['ma_sv']) ?></span></div>
        </div>
        <div class="profile-row">
            <div class="lbl">Ngày sinh</div>
            <div class="val"><?= htmlspecialchars($student['ngay_sinh']) ?></div>
        </div>
        <div class="profile-row">
            <div class="lbl">SĐT liên hệ</div>
            <div class="val"><?= htmlspecialchars($student['sdt']) ?: '<em style="color:var(--text-light);">(Trống)</em>' ?></div>
        </div>
    </div>

    <div class="profile-section">
        <div class="profile-section-header">
            <div class="icon-sm green"><i class="fas fa-graduation-cap"></i></div>
            <h5>Thông tin Học tập</h5>
        </div>
        <div class="profile-row">
            <div class="lbl">Khoa</div>
            <div class="val" style="font-weight:600;"><?= htmlspecialchars($student['ten_khoa']) ?></div>
        </div>
        <div class="profile-row">
            <div class="lbl">Hệ đào tạo</div>
            <div class="val"><?= htmlspecialchars($student['ten_he']) ?></div>
        </div>
        <div class="profile-row">
            <div class="lbl">Lớp</div>
            <div class="val"><?= htmlspecialchars($student['ten_lop']) ?></div>
        </div>
        <div class="profile-row">
            <div class="lbl">Chuyên ngành</div>
            <div class="val"><?= htmlspecialchars($student['chuyen_nganh']) ?></div>
        </div>
        <div class="profile-row">
            <div class="lbl">Niên khóa</div>
            <div class="val"><?= htmlspecialchars($student['nien_khoa']) ?></div>
        </div>
    </div>
</div>
