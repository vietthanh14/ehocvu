<?php
if (!isset($_SESSION['student'])) exit;
$student = $_SESSION['student'];

require_once __DIR__ . '/../core/GoogleSheetService.php';
$service = GoogleSheetService::getInstance();
$elig = $service->getStudentSubmitEligibility($student['ma_sv']);

$pendingTypes = $elig['pendingTypes'];
$coQDBaoLuu = $elig['coQDBaoLuu'];
$isTiepTucHocPending = $elig['isTiepTucHocPending'];

$warnings = [];

foreach ($pendingTypes as $t) {
    $warnings[] = "<strong>" . htmlspecialchars($t) . "</strong> — đang chờ duyệt. Bạn không thể nộp thêm đơn cùng loại.";
}

if ($coQDBaoLuu || $isTiepTucHocPending) {
    if (!in_array('Bảo lưu kết quả học tập', $pendingTypes)) {
        if ($isTiepTucHocPending) {
            $warnings[] = "<strong>Bảo lưu kết quả học tập</strong> — bạn đang có đơn Tiếp tục học chờ duyệt nên không thể nộp đơn bảo lưu lúc này.";
        } else {
            $warnings[] = "<strong>Bảo lưu kết quả học tập</strong> — bạn đang trong thời gian bảo lưu nên không thể nộp thêm.";
        }
    }
} else {
    if (!in_array('Tiếp tục học sau bảo lưu', $pendingTypes)) {
        $warnings[] = "<strong>Tiếp tục học sau bảo lưu</strong> — chưa đủ điều kiện (bạn chưa có Quyết định Bảo lưu nào).";
    }
}
?>

<?php if (!empty($warnings)): ?>
<div class="notice-card" style="margin-bottom: 24px; background: linear-gradient(135deg, #fef2f2, #fee2e2); border-color: #fca5a5;">
    <h6 style="color: #991b1b;"><i class="fas fa-exclamation-circle"></i> LƯU Ý KHI NỘP HỒ SƠ</h6>
    <ul>
        <?php foreach ($warnings as $w): ?>
            <li style="color: #991b1b; padding-bottom: 6px;"><?= $w ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form id="requestForm" method="POST" action="api/api_submit_baoluu.php" enctype="multipart/form-data">
    <!-- Thông tin SV lấy từ session phía server, không cần gửi qua form -->

    <div class="form-field">
        <label for="loai_yeu_cau">Thủ tục đăng ký <span style="color: var(--danger);">*</span></label>
        <select name="loai_yeu_cau" id="loai_yeu_cau" required>
            <option value="" disabled selected>-- Chọn loại thủ tục --</option>
            <option value="Bảo lưu kết quả học tập" <?= !$elig['canSubmitBaoLuu'] ? 'disabled' : '' ?>>
                Bảo lưu kết quả học tập
            </option>
            <option value="Tiếp tục học sau bảo lưu" <?= !$elig['canSubmitTiepTuc'] ? 'disabled' : '' ?>>
                Tiếp tục học sau bảo lưu
            </option>
        </select>
    </div>

    <div class="form-field" id="row-tgbl" style="display: none;">
        <label for="thoi_gian_bao_luu_den" id="lbl-tgbl">Bảo lưu đến ngày</label>
        <input type="date" name="thoi_gian_bao_luu_den" id="thoi_gian_bao_luu_den">
        <span class="form-hint" id="hint-tgbl">Chọn ngày dự kiến kết thúc bảo lưu (không bắt buộc)</span>
    </div>

    <div class="form-field">
        <label for="file_don">File đơn đăng ký <span style="color: var(--danger);">*</span></label>
        <div style="margin-bottom: 8px; font-size: 0.85rem; color: var(--text-mid);">
            <i class="fas fa-info-circle" style="color: var(--primary);"></i> Chưa có mẫu đơn? <a href="https://drive.google.com/drive/folders/1zs6cYMC95_dpMt29hSVpWhr0As10Y8eA" target="_blank" style="color: var(--primary); font-weight: 600; text-decoration: underline;">Tải biểu mẫu tại đây</a>.
        </div>
        <?php 
        $inputName = 'file_don';
        $isRequired = true;
        $hintText = 'PDF, JPG, PNG, DOC — Tối đa 10MB';
        include __DIR__ . '/../includes/components/file_upload.php'; 
        ?>
    </div>

    <div class="form-field">
        <label for="ly_do">Lý do cụ thể <span style="color: var(--danger);">*</span></label>
        <textarea name="ly_do" id="ly_do" rows="4" placeholder="Ghi rõ lý do bạn muốn bảo lưu hoặc tiếp tục học..." required></textarea>
    </div>

    <div class="form-actions">
        <div id="upload-progress" style="display:none; margin-bottom: 12px;">
            <div style="display:flex; align-items:center; gap:8px; font-size:0.85rem; color: var(--text-mid);">
                <span class="spinner-custom" style="display:inline-block;"></span>
                <span id="progress-text">Đang tải file lên...</span>
            </div>
        </div>
        <button type="submit" id="btn-submit" class="btn-primary-custom">
            <span class="spinner-custom" id="spinner-submit"></span>
            <i class="fas fa-paper-plane"></i> Gửi hồ sơ đăng ký
        </button>
    </div>
</form>

<style>
</style>

<script>
// === Procedure toggle ===
document.getElementById('loai_yeu_cau').addEventListener('change', function() {
    const rowTgbl = document.getElementById('row-tgbl');
    const lblTgbl = document.getElementById('lbl-tgbl');
    const hintTgbl = document.getElementById('hint-tgbl');
    const inputTgbl = document.getElementById('thoi_gian_bao_luu_den');

    if (this.value === 'Bảo lưu kết quả học tập') {
        rowTgbl.style.display = 'block';
        lblTgbl.innerHTML = 'Bảo lưu đến ngày';
        hintTgbl.innerHTML = 'Chọn ngày dự kiến kết thúc bảo lưu (không bắt buộc)';
        inputTgbl.required = false;
    } else if (this.value === 'Tiếp tục học sau bảo lưu') {
        rowTgbl.style.display = 'block';
        lblTgbl.innerHTML = 'Ngày bắt đầu đi học lại <span style="color: var(--danger);">*</span>';
        hintTgbl.innerHTML = 'Chọn ngày chính thức bắt đầu quay lại học tiếp.';
        inputTgbl.required = true;
    } else {
        rowTgbl.style.display = 'none';
        inputTgbl.value = '';
        inputTgbl.required = false;
    }
});

// === Form submit ===
document.getElementById('requestForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit');
    const spinner = document.getElementById('spinner-submit');
    const progress = document.getElementById('upload-progress');
    const progressText = document.getElementById('progress-text');

    btn.disabled = true;
    spinner.style.display = 'inline-block';
    progress.style.display = 'block';
    progressText.textContent = 'Đang tải file lên Google Drive...';

    AppFetch.post('api/api_submit_baoluu.php', new FormData(this))
    .then(r => r.json())
    .then(data => {
        spinner.style.display = 'none';
        progress.style.display = 'none';
        btn.disabled = false;
        if (data.success) {
            AppAlert.success('Thành công!', 'Hồ sơ đã được gửi đi thành công.')
            .then(() => { window.location.href = 'dashboard.php?page=home'; });
        } else {
            AppAlert.error('Không thể nộp đơn', data.message || 'Đã có lỗi xảy ra.');
        }
    })
    .catch(() => {
        spinner.style.display = 'none';
        progress.style.display = 'none';
        btn.disabled = false;
        AppAlert.error('Lỗi kết nối', 'Không thể kết nối đến máy chủ.');
    });
});
</script>
