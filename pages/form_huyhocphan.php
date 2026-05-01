<?php
/**
 * Form Đề nghị Hủy Học Phần
 * File này được include bởi dashboard.php
 * Biến $student đã có sẵn từ session
 *
 * @var array $student
 */

require_once __DIR__ . '/../core/HuyHocPhanService.php';
require_once __DIR__ . '/../includes/helpers/UIHelper.php';
$service = new HuyHocPhanService();
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
$tabPrefix = 'hhp'; 
include __DIR__ . '/../includes/components/tabs_nav.php'; 
?>

<div id="hhp-form" class="tab-pane active">
<?php
// (Phần cảnh báo lịch sử đã được chuyển xuống bảng Lịch sử bên dưới)
?>



<?php if (!$isDotMo): ?>
<!-- === MÀN HÌNH KHÓA: Đợt đã đóng === -->
<div class="notice-card" style="text-align: center; padding: 40px 20px; background: var(--input-bg); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 24px; display: flex; flex-direction: column; align-items: center;">
    <div style="width: 64px; height: 64px; background: rgba(100, 116, 139, 0.1); color: #64748b; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 20px;">
        <i class="fas fa-calendar-times"></i>
    </div>
    <h4 style="color: var(--text-dark); margin-bottom: 12px; font-weight: 600; font-size: 1.15rem;">Đợt đăng ký đã đóng</h4>
    <p style="color: var(--text-mid); max-width: 500px; margin: 0 auto; line-height: 1.6; font-size: 0.95rem;">
        <?= htmlspecialchars($config['ThongBaoDong']) ?>
    </p>
    <?php if (!empty($config['TuNgay']) && !empty($config['DenNgay'])): ?>
    <div style="display: inline-flex; align-items: center; margin-top: 16px; padding: 8px 16px; background: #f8fafc; border-radius: 8px; color: var(--text-mid); font-size: 0.85rem; border: 1px solid #e2e8f0;">
        <i class="far fa-clock" style="margin-right: 6px;"></i> Thời gian mở gần nhất: <strong style="margin-left: 4px;"><?= htmlspecialchars($config['TuNgay']) ?></strong> <span style="margin: 0 6px;">—</span> <strong><?= htmlspecialchars($config['DenNgay']) ?></strong>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($daNopDon): ?>
<!-- === ĐÃ NỘP ĐƠN: Thông báo xác nhận === -->
<div class="notice-card" style="text-align: center; padding: 40px 20px; background: var(--input-bg); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 24px; display: flex; flex-direction: column; align-items: center;">
    <div style="width: 64px; height: 64px; background: rgba(20, 184, 166, 0.1); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 20px;">
        <i class="fas fa-check-circle"></i>
    </div>
    <h4 style="color: var(--text-dark); margin-bottom: 12px; font-weight: 600; font-size: 1.15rem;">Bạn đã nộp đơn trong đợt này</h4>
    <p style="color: var(--text-mid); max-width: 500px; margin: 0 auto; line-height: 1.6; font-size: 0.95rem;">
        Đơn đề nghị hủy học phần của bạn trong đợt "<strong><?= htmlspecialchars($config['TieuDeDot']) ?></strong>" đã được ghi nhận.<br>
        Vui lòng theo dõi trạng thái xử lý ở tab <strong>Lịch sử đơn</strong>.
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
                   style="width:100%; padding:12px 16px 12px 40px; border:1.5px solid var(--border); border-radius:10px; font-family:'Inter',sans-serif; font-size:0.9rem; color:var(--text-dark); background:var(--input-bg); outline:none; transition:var(--transition);">
            <i class="fas fa-search" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--text-light); font-size:0.85rem;"></i>
        </div>
        <!-- Dropdown kết quả tìm kiếm -->
        <div id="courseDropdown" style="display:none; position:relative;">
            <div class="autocomplete-dropdown">
                <ul id="courseList" class="autocomplete-list"></ul>
            </div>
        </div>
    </div>

    <!-- Danh sách môn đã chọn -->
    <div class="form-field">
        <label>Danh sách học phần cần hủy <span style="color: var(--danger);">*</span></label>
        <div class="course-tags-container" id="courseTagsContainer">
            <span class="course-tag-empty" id="emptyTagsMsg">Chưa có môn học nào được chọn. Hãy tìm và chọn ở trên.</span>
        </div>
        <textarea name="danh_sach_mon" id="danhSachMonTextarea" style="display:none;" required></textarea>
        <span class="form-hint">Danh sách các môn sẽ được tự động thêm dưới dạng thẻ.</span>
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
<?php 
$progressId = 'progressHuyHP'; 
$progressTextId = 'progressTextHuyHP'; 
include __DIR__ . '/../includes/components/progress_bar.php'; 
?>



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
        courseListEl.innerHTML = '<li class="empty-msg">Không tìm thấy môn học</li>';
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

// === Tags Logic ===
const tagsContainer = document.getElementById('courseTagsContainer');
const emptyMsg = document.getElementById('emptyTagsMsg');
let selectedCourses = [];

function updateTagsUI() {
    if (selectedCourses.length === 0) {
        tagsContainer.innerHTML = '<span class="course-tag-empty" id="emptyTagsMsg">Chưa có môn học nào được chọn. Hãy tìm và chọn ở trên.</span>';
        textarea.value = '';
    } else {
        tagsContainer.innerHTML = selectedCourses.map((c, index) => 
            `<span class="course-tag">
                [${c.id}] ${c.name}
                <button type="button" onclick="removeCourse(${index})" title="Xóa môn này"><i class="fas fa-times"></i></button>
            </span>`
        ).join('');
        textarea.value = selectedCourses.map(c => `[${c.id}] - ${c.name}`).join('\n');
    }
}

window.removeCourse = function(index) {
    selectedCourses.splice(index, 1);
    updateTagsUI();
};

// Khi click chọn môn
courseListEl.addEventListener('click', function(e) {
    const li = e.target.closest('li');
    if (!li || !li.dataset.id) return;

    // Kiểm tra trùng
    const isExist = selectedCourses.some(c => c.id === li.dataset.id);
    if (!isExist) {
        selectedCourses.push({ id: li.dataset.id, name: li.dataset.name });
        updateTagsUI();
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
AppForm.handleSubmit('formHuyHocPhan', 'api/api_submit_huyhocphan.php');
</script>
<?php endif; ?>

</div>

<div id="hhp-history" class="tab-pane">
<!-- === LỊCH SỬ ĐƠN ĐÃ NỘP === -->
<?php
$lichSuRendered = [];
if (!empty($lichSuDon)) {
    foreach ($lichSuDon as $idx => $don) {
        $don['_rbg'] = $idx % 2 === 0 ? 'transparent' : 'var(--hover-bg)';
        $lichSuRendered[] = $don;
    }
}
?>
<div style="margin-bottom: 30px;">
    <h5 style="color: var(--text-dark); margin-bottom: 14px; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-history" style="color: var(--primary);"></i> Lịch sử đơn hủy học phần
    </h5>

    <!-- Desktop: Table -->
    <div class="history-table-view">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th style="text-align:left; white-space:nowrap;">Thời gian</th>
                        <th style="text-align:left; white-space:nowrap;">Đợt</th>
                        <th style="text-align:left;">Môn hủy</th>
                        <th style="text-align:left;">Lý do</th>
                        <th style="text-align:center;">Minh chứng</th>
                        <th style="text-align:center; white-space:nowrap;">Trạng thái</th>
                        <th style="text-align:left;">Phản hồi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($lichSuRendered)): ?>
                    <tr>
                        <td colspan="7" style="padding: 0;">
                            <?php 
                            $isRow = true; 
                            $emptyMessage = 'Chưa có đơn đề nghị hủy học phần nào được đăng ký'; 
                            include __DIR__ . '/../includes/components/empty_state.php'; 
                            ?>
                        </td>
                    </tr>
                <?php else: ?>
                <?php foreach ($lichSuRendered as $d): ?>
                    <tr style="background:<?= $d['_rbg'] ?>;">
                        <td><?= htmlspecialchars($d['timestamp']) ?></td>
                        <td><?= htmlspecialchars($d['tieu_de_dot']) ?></td>
                        <td><?= htmlspecialchars($d['danh_sach_mon']) ?></td>
                        <td><?= htmlspecialchars($d['ly_do']) ?></td>
                        <td>
                            <?php if (!empty($d['link_minh_chung'])): ?>
                                <a href="<?= htmlspecialchars($d['link_minh_chung']) ?>" target="_blank" style="color:var(--primary); text-decoration:none;" title="Xem minh chứng"><i class="fas fa-paperclip"></i></a>
                            <?php else: ?>
                                <span style="color:#cbd5e1;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= UIHelper::renderStatusBadge($d['trang_thai']) ?>
                        </td>
                        <td>
                            <?= !empty($d['ghi_chu_admin']) ? htmlspecialchars($d['ghi_chu_admin']) : '<span style="opacity:0.4;">—</span>' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mobile: Cards -->
    <div class="history-card-view">
        <?php if (empty($lichSuRendered)): ?>
        <?php 
        $emptyMessage = 'Chưa có đơn đề nghị hủy học phần nào được đăng ký'; 
        include __DIR__ . '/../includes/components/empty_state.php'; 
        ?>
        <?php else: ?>
        <?php foreach ($lichSuRendered as $d): ?>
        <div class="history-card">
            <div class="history-card-header">
                <div>
                    <span class="history-card-type"><?= htmlspecialchars($d['tieu_de_dot']) ?></span>
                    <span class="history-card-time"><i class="far fa-clock"></i> <?= htmlspecialchars($d['timestamp']) ?></span>
                </div>
                <?= UIHelper::renderStatusBadge($d['trang_thai']) ?>
            </div>
            <div class="history-card-body">
                <div class="history-card-row">
                    <span class="hc-label">Môn hủy</span>
                    <span class="hc-value" style="white-space:pre-line;"><?= htmlspecialchars($d['danh_sach_mon']) ?></span>
                </div>
                <div class="history-card-row">
                    <span class="hc-label">Lý do</span>
                    <span class="hc-value"><?= htmlspecialchars($d['ly_do']) ?></span>
                </div>
                <?php if (!empty($d['ghi_chu_admin'])): ?>
                <div class="history-card-row">
                    <span class="hc-label">Phản hồi</span>
                    <span class="hc-value" style="font-style:italic;"><?= htmlspecialchars($d['ghi_chu_admin']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($d['link_minh_chung'])): ?>
            <div class="history-card-footer">
                <a href="<?= htmlspecialchars($d['link_minh_chung']) ?>" target="_blank" class="hc-link info">
                    <i class="fas fa-external-link-alt"></i> Xem minh chứng
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
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
    background: var(--card-bg);
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
    border-bottom: 1px solid var(--border);
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

</div>
