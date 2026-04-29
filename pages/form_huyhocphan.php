<?php
/**
 * Form Đề nghị Hủy Học Phần
 * File này được include bởi dashboard.php
 * Biến $student đã có sẵn từ session
 *
 * @var array $student
 */

require_once __DIR__ . '/../core/GoogleSheetService.php';
require_once __DIR__ . '/../core/UIHelper.php';
$service = GoogleSheetService::getInstance();
$config = $service->getHuyHocPhanConfig();
$lichSuDon = $service->getHuyHocPhanHistory($student['ma_sv']);

// Kiểm tra trạng thái Đợt mở
$isDotMo = (mb_strtolower(trim($config['TrangThai'])) === 'mở');

// Kiểm tra thời gian
if ($isDotMo && !empty($config['TuNgay']) && !empty($config['DenNgay'])) {
    $today = strtotime(date('Y-m-d'));
    $tuNgay = strtotime(str_replace('/', '-', $config['TuNgay']));
    $denNgay = strtotime(str_replace('/', '-', $config['DenNgay']));
    if ($tuNgay && $denNgay && ($today < $tuNgay || $today > $denNgay)) {
        $isDotMo = false;
    }
}

// Kiểm tra SV đã nộp trong đợt này chưa
$daNopDon = false;
if ($isDotMo && !empty($config['TieuDeDot'])) {
    $daNopDon = $service->checkHuyHocPhanSubmitted($student['ma_sv'], $config['TieuDeDot']);
}
?>

<?php
// (Phần cảnh báo lịch sử đã được chuyển xuống bảng Lịch sử bên dưới)
?>

<?php if (!$isDotMo): ?>
<!-- === MÀN HÌNH KHÓA: Đợt đã đóng === -->
<div style="text-align: center; padding: 60px 20px;">
    <div style="font-size: 4rem; margin-bottom: 16px; opacity: 0.3;">🔒</div>
    <h3 style="color: var(--text-dark); margin-bottom: 12px;">Đợt đăng ký đã đóng</h3>
    <p style="color: var(--text-light); max-width: 500px; margin: 0 auto; line-height: 1.6;">
        <?= htmlspecialchars($config['ThongBaoDong']) ?>
    </p>
    <?php if (!empty($config['TuNgay']) && !empty($config['DenNgay'])): ?>
    <p style="color: var(--text-light); margin-top: 12px; font-size: 0.85rem;">
        <i class="fas fa-clock"></i> Thời gian mở gần nhất: <strong><?= htmlspecialchars($config['TuNgay']) ?></strong> — <strong><?= htmlspecialchars($config['DenNgay']) ?></strong>
    </p>
    <?php endif; ?>
</div>

<?php elseif ($daNopDon): ?>
<!-- === ĐÃ NỘP ĐƠN: Thông báo xác nhận === -->
<div style="text-align: center; padding: 40px 20px;">
    <div style="font-size: 3.5rem; margin-bottom: 16px;">✅</div>
    <h3 style="color: var(--text-dark); margin-bottom: 10px;">Bạn đã nộp đơn trong đợt này</h3>
    <p style="color: var(--text-light); max-width: 480px; margin: 0 auto; line-height: 1.6;">
        Đơn đề nghị hủy học phần của bạn trong đợt "<strong><?= htmlspecialchars($config['TieuDeDot']) ?></strong>" đã được ghi nhận.
        Vui lòng theo dõi trạng thái xử lý ở bảng lịch sử bên dưới.
    </p>
</div>

<?php else: ?>
<!-- === FORM ĐĂNG KÝ HỦY HỌC PHẦN === -->

<div class="notice-card" style="margin-bottom: 20px;">
    <h6><i class="fas fa-calendar-check"></i> <?= htmlspecialchars($config['TieuDeDot']) ?></h6>
    <ul>
        <li>Thời gian nộp đơn: <strong><?= htmlspecialchars($config['TuNgay']) ?></strong> — <strong><?= htmlspecialchars($config['DenNgay']) ?></strong></li>
        <li>Hãy liệt kê đầy đủ tất cả các học phần cần hủy.</li>
    </ul>
</div>

<form id="formHuyHocPhan" method="POST" action="api/api_submit_huyhocphan.php" enctype="multipart/form-data">

    <!-- Ô tìm kiếm môn học (Autocomplete) -->
    <div class="form-field">
        <label>Tìm kiếm môn học <span style="font-weight:400; text-transform: none; color: var(--text-light);">(Gõ mã hoặc tên môn)</span></label>
        <div style="position: relative;">
            <input type="text" id="courseSearchInput" placeholder="VD: INT123 hoặc Toán cao cấp..."
                   style="width:100%; padding:12px 16px 12px 40px; border:1.5px solid var(--border); border-radius:10px; font-family:'Inter',sans-serif; font-size:0.9rem; color:var(--text-dark); background:#fff; outline:none; transition:var(--transition);">
            <i class="fas fa-search" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--text-light); font-size:0.85rem;"></i>
        </div>
        <!-- Dropdown kết quả tìm kiếm -->
        <div id="courseDropdown" style="display:none; position:relative; z-index:100;">
            <div style="position:absolute; width:100%; max-height:220px; overflow-y:auto; background:#fff; border:1.5px solid var(--primary-light); border-radius:0 0 10px 10px; box-shadow:var(--shadow-md); margin-top:-2px;">
                <ul id="courseList" style="list-style:none; padding:0; margin:0;"></ul>
            </div>
        </div>
    </div>

    <!-- Danh sách môn đã chọn -->
    <div class="form-field">
        <label>Danh sách học phần cần hủy <span style="color: var(--danger);">*</span></label>
        <textarea name="danh_sach_mon" id="danhSachMonTextarea" rows="5" required
                  placeholder="Các môn bạn chọn sẽ tự động hiện ở đây. Bạn cũng có thể gõ thêm thủ công."></textarea>
        <span class="form-hint">Danh sách các môn sẽ được tự động thêm khi bạn chọn từ ô tìm kiếm phía trên.</span>
    </div>

    <!-- Lý do hủy -->
    <div class="form-field">
        <label>Lý do hủy <span style="color: var(--danger);">*</span></label>
        <textarea name="ly_do" rows="3" required placeholder="Trùng lịch học / Không đủ điều kiện tiên quyết / Lý do cá nhân..."></textarea>
    </div>

    <!-- Minh chứng (Tùy chọn) -->
    <div class="form-field">
        <label>File minh chứng <span style="font-weight:400; text-transform:none; color: var(--text-light);">(Không bắt buộc)</span></label>
        <?php 
        $inputName = 'file_minh_chung';
        $isRequired = false;
        $hintText = 'Chấp nhận PDF, JPG, PNG, DOC, DOCX — Tối đa 10MB';
        include __DIR__ . '/../includes/components/file_upload.php'; 
        ?>
    </div>

    <!-- Nút gửi -->
    <div class="form-actions">
        <button type="submit" class="btn-primary-custom" id="btnSubmitHuyHP">
            <div class="spinner-custom" id="spinnerHuyHP"></div>
            <i class="fas fa-paper-plane"></i> Gửi đề nghị hủy học phần
        </button>
    </div>

</form>

<!-- Progress bar -->
<div id="progressHuyHP" style="display:none; margin-top:16px;">
    <div style="height:4px; background:var(--border); border-radius:4px; overflow:hidden;">
        <div style="height:100%; width:0%; background:linear-gradient(90deg, var(--primary), var(--primary-light)); animation: progressAnim 2s ease-in-out infinite;" id="progressBarHuyHP"></div>
    </div>
    <p id="progressTextHuyHP" style="font-size:0.8rem; color:var(--text-light); margin-top:8px; text-align:center;">Đang xử lý...</p>
</div>

<style>
@keyframes progressAnim {
    0% { width: 10%; }
    50% { width: 70%; }
    100% { width: 95%; }
}
#courseList li {
    padding: 10px 16px;
    cursor: pointer;
    font-size: 0.88rem;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s;
}
#courseList li:hover {
    background: rgba(20, 184, 166, 0.08);
}
#courseList li:last-child {
    border-bottom: none;
}
#courseList li .course-id {
    font-weight: 700;
    color: var(--primary);
    margin-right: 6px;
}
#courseList li .course-name {
    color: var(--text-mid);
}
</style>

<script>
// === Autocomplete Logic ===
let allCourses = [];

// Tải danh sách môn học khi trang load
fetch('api/api_get_courses.php')
.then(r => r.json())
.then(data => {
    if (data.success && data.courses) {
        allCourses = data.courses;
    }
})
.catch(err => console.error('Lỗi tải danh mục môn học:', err));

const searchInput  = document.getElementById('courseSearchInput');
const dropdown     = document.getElementById('courseDropdown');
const courseListEl  = document.getElementById('courseList');
const textarea     = document.getElementById('danhSachMonTextarea');

searchInput.addEventListener('input', function() {
    const query = this.value.trim().toLowerCase();
    if (query.length < 1) {
        dropdown.style.display = 'none';
        return;
    }

    const filtered = allCourses.filter(c =>
        c.id.toLowerCase().includes(query) || c.name.toLowerCase().includes(query)
    ).slice(0, 15); // Giới hạn 15 kết quả

    if (filtered.length === 0) {
        courseListEl.innerHTML = '<li style="color:var(--text-light); cursor:default;">Không tìm thấy môn học</li>';
    } else {
        courseListEl.innerHTML = filtered.map(c =>
            `<li data-id="${c.id}" data-name="${c.name}">
                <span class="course-id">[${c.id}]</span>
                <span class="course-name">${c.name}</span>
            </li>`
        ).join('');
    }
    dropdown.style.display = 'block';
});

// Khi click chọn môn
courseListEl.addEventListener('click', function(e) {
    const li = e.target.closest('li');
    if (!li || !li.dataset.id) return;

    const line = `[${li.dataset.id}] - ${li.dataset.name}`;

    // Kiểm tra trùng
    if (!textarea.value.includes(li.dataset.id)) {
        textarea.value = textarea.value.trim()
            ? textarea.value.trim() + '\n' + line
            : line;
    }

    searchInput.value = '';
    dropdown.style.display = 'none';
    searchInput.focus();
});

// Đóng dropdown khi click ra ngoài
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});

// === Form Submit (AJAX) ===
document.getElementById('formHuyHocPhan').addEventListener('submit', function(e) {
    e.preventDefault();

    const btn = document.getElementById('btnSubmitHuyHP');
    const spinner = document.getElementById('spinnerHuyHP');
    const progress = document.getElementById('progressHuyHP');
    const progressText = document.getElementById('progressTextHuyHP');

    btn.disabled = true;
    spinner.style.display = 'inline-block';
    progress.style.display = 'block';
    progressText.textContent = 'Đang gửi đề nghị...';

    AppFetch.post('api/api_submit_huyhocphan.php', new FormData(this))
    .then(r => r.json())
    .then(data => {
        spinner.style.display = 'none';
        progress.style.display = 'none';

        if (data.success) {
            AppAlert.success('Thành công!', data.message).then(() => location.reload());
        } else {
            AppAlert.error('Lỗi', data.message);
            btn.disabled = false;
        }
    })
    .catch(() => {
        spinner.style.display = 'none';
        progress.style.display = 'none';
        AppAlert.error('Lỗi kết nối', 'Không thể kết nối tới máy chủ.');
        btn.disabled = false;
    });
});
</script>
<?php endif; ?>

<?php if (!empty($lichSuDon)): ?>
<!-- === LỊCH SỬ ĐƠN ĐÃ NỘP === -->
<?php
$lichSuRendered = [];
foreach ($lichSuDon as $idx => $don) {
    $don['_rbg'] = $idx % 2 === 0 ? '#fff' : '#f8fafc';
    $lichSuRendered[] = $don;
}
?>
<div style="margin-top: 30px;">
    <h5 style="color: var(--text-dark); margin-bottom: 14px; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-history" style="color: var(--primary);"></i> Lịch sử đơn hủy học phần
    </h5>

    <!-- Desktop: Table -->
    <div class="hhp-history-table">
        <div style="overflow-x:auto; border-radius:12px; border:1px solid var(--border); box-shadow:var(--shadow-sm);">
            <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                <thead>
                    <tr style="background:linear-gradient(135deg, var(--primary), var(--primary-dark)); color:#fff;">
                        <th style="padding:12px 14px; text-align:left; white-space:nowrap;">Thời gian</th>
                        <th style="padding:12px 14px; text-align:left; white-space:nowrap;">Đợt</th>
                        <th style="padding:12px 14px; text-align:left;">Môn hủy</th>
                        <th style="padding:12px 14px; text-align:left;">Lý do</th>
                        <th style="padding:12px 14px; text-align:center;">Minh chứng</th>
                        <th style="padding:12px 14px; text-align:center; white-space:nowrap;">Trạng thái</th>
                        <th style="padding:12px 14px; text-align:left;">Phản hồi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lichSuRendered as $d): ?>
                    <tr style="background:<?= $d['_rbg'] ?>; border-bottom:1px solid #f1f5f9;">
                        <td style="padding:11px 14px; white-space:nowrap; color:var(--text-mid);"><?= htmlspecialchars($d['timestamp']) ?></td>
                        <td style="padding:11px 14px; white-space:nowrap; font-weight:600; color:var(--text-dark);"><?= htmlspecialchars($d['tieu_de_dot']) ?></td>
                        <td style="padding:11px 14px; color:var(--text-mid); white-space:pre-line; max-width:280px;"><?= htmlspecialchars($d['danh_sach_mon']) ?></td>
                        <td style="padding:11px 14px; color:var(--text-mid); max-width:200px;"><?= htmlspecialchars($d['ly_do']) ?></td>
                        <td style="padding:11px 14px; text-align:center;">
                            <?php if (!empty($d['link_minh_chung'])): ?>
                                <a href="<?= htmlspecialchars($d['link_minh_chung']) ?>" target="_blank" style="color:var(--primary); text-decoration:none;" title="Xem minh chứng"><i class="fas fa-paperclip"></i></a>
                            <?php else: ?>
                                <span style="color:#cbd5e1;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:11px 14px; text-align:center;">
                            <?= UIHelper::renderStatusBadge($d['trang_thai']) ?>
                        </td>
                        <td style="padding:11px 14px; color:var(--text-mid); font-style:<?= empty($d['ghi_chu_admin']) ? 'italic' : 'normal' ?>;">
                            <?= !empty($d['ghi_chu_admin']) ? htmlspecialchars($d['ghi_chu_admin']) : '<span style="opacity:0.4;">—</span>' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mobile: Cards -->
    <div class="hhp-history-cards">
        <?php foreach ($lichSuRendered as $d): ?>
        <div class="hhp-card">
            <div class="hhp-card-header">
                <span class="hhp-card-dot"><?= htmlspecialchars($d['tieu_de_dot']) ?></span>
                <?= UIHelper::renderStatusBadge($d['trang_thai']) ?>
            </div>
            <div class="hhp-card-row">
                <span class="hhp-card-label"><i class="fas fa-clock"></i> Thời gian</span>
                <span class="hhp-card-value"><?= htmlspecialchars($d['timestamp']) ?></span>
            </div>
            <div class="hhp-card-row">
                <span class="hhp-card-label"><i class="fas fa-book"></i> Môn hủy</span>
                <span class="hhp-card-value" style="white-space:pre-line;"><?= htmlspecialchars($d['danh_sach_mon']) ?></span>
            </div>
            <div class="hhp-card-row">
                <span class="hhp-card-label"><i class="fas fa-info-circle"></i> Lý do</span>
                <span class="hhp-card-value"><?= htmlspecialchars($d['ly_do']) ?></span>
            </div>
            <?php if (!empty($d['link_minh_chung'])): ?>
            <div class="hhp-card-row">
                <span class="hhp-card-label"><i class="fas fa-paperclip"></i> Minh chứng</span>
                <span class="hhp-card-value"><a href="<?= htmlspecialchars($d['link_minh_chung']) ?>" target="_blank" style="color:var(--primary); text-decoration:none;"><i class="fas fa-external-link-alt"></i> Xem file</a></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($d['ghi_chu_admin'])): ?>
            <div class="hhp-card-row">
                <span class="hhp-card-label"><i class="fas fa-comment-dots"></i> Phản hồi</span>
                <span class="hhp-card-value"><?= htmlspecialchars($d['ghi_chu_admin']) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.hhp-history-table { display: block; }
.hhp-history-cards { display: none; }
@media (max-width: 768px) {
    .hhp-history-table { display: none !important; }
    .hhp-history-cards { display: flex; flex-direction: column; gap: 12px; }
}
.hhp-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 16px;
    box-shadow: var(--shadow-sm);
}
.hhp-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f1f5f9;
}
.hhp-card-dot {
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--text-dark);
}
.hhp-card-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.73rem;
    font-weight: 600;
    border: 1px solid;
    white-space: nowrap;
}
.hhp-card-row {
    display: flex;
    gap: 8px;
    padding: 5px 0;
    font-size: 0.83rem;
    line-height: 1.5;
}
.hhp-card-label {
    min-width: 85px;
    color: var(--text-light);
    font-weight: 500;
    flex-shrink: 0;
}
.hhp-card-label i {
    width: 16px;
    text-align: center;
    margin-right: 4px;
    color: var(--primary);
    font-size: 0.75rem;
}
.hhp-card-value {
    color: var(--text-mid);
    word-break: break-word;
}
</style>
<?php endif; ?>

