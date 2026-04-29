<?php
if (!isset($_SESSION['student'])) exit;
$student = $_SESSION['student'];

require_once __DIR__ . '/../core/BaoLuuService.php';
$service = new BaoLuuService();
$elig = $service->getStudentSubmitEligibility($student['ma_sv']);
$requests = $service->getStudentRequests($student['ma_sv']);
require_once __DIR__ . '/../includes/helpers/UIHelper.php';

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


<div class="tabs-nav">
    <button type="button" class="tab-btn active" onclick="switchTab('baoluu-form', this)">
        <i class="fas fa-plus-circle"></i> Tạo đơn mới
    </button>
    <button type="button" class="tab-btn" onclick="switchTab('baoluu-history', this)">
        <i class="fas fa-history"></i> Lịch sử đơn
    </button>
</div>

<div id="baoluu-form" class="tab-pane active">

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
        <button type="submit" id="btn-submit" class="btn-primary-custom">
            <span class="spinner-custom" id="spinner-submit"></span>
            <i class="fas fa-paper-plane"></i> Gửi hồ sơ đăng ký
        </button>
    </div>
</form>

<!-- Progress bar -->
<div id="upload-progress" style="display:none; margin-top:16px;">
    <div style="height:4px; background:var(--border); border-radius:4px; overflow:hidden;">
        <div style="height:100%; width:0%; background:linear-gradient(90deg, var(--primary), var(--primary-light)); animation: progressAnim 2s ease-in-out infinite;" id="progressBarHuyHP"></div>
    </div>
    <p id="progress-text" style="font-size:0.8rem; color:var(--text-light); margin-top:8px; text-align:center;">Đang xử lý...</p>
</div>

<style>
</style>


</div> <!-- End baoluu-form tab -->

<div id="baoluu-history" class="tab-pane">


<!-- ===== DESKTOP: Table view ===== -->
<div class="history-table-view">
    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width:5%; text-align:center;">STT</th>
                    <th style="text-align:left;">Thời gian nộp</th>
                    <th style="text-align:left;">Loại thủ tục</th>
                    <th style="text-align:center;">BL đến</th>
                    <th style="text-align:center;">Đơn ĐK</th>
                    <th style="text-align:center;">Trạng thái</th>
                    <th style="text-align:left;">Ghi chú</th>
                    <th style="text-align:center;">Số QĐ</th>
                    <th style="text-align:center;">Ngày QĐ</th>
                    <th style="text-align:center;">File QĐ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="10" style="text-align:center; color: var(--text-light); padding: 40px 16px;">
                            <i class="fas fa-inbox" style="font-size: 1.5rem; display:block; margin-bottom: 8px; opacity:0.4;"></i>
                            Chưa có thủ tục nào được đăng ký
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $idx => $req): ?>
                        <tr>
                            <td style="text-align:center;"><?= $idx + 1 ?></td>
                            <td style="text-align:left; color: var(--text-light);"><i class="far fa-clock" style="margin-right:4px;"></i> <?= htmlspecialchars($req['thoi_gian']) ?></td>
                            <td style="text-align:left; font-weight:600; color: var(--primary);"><?= htmlspecialchars($req['loai_yeu_cau']) ?></td>
                            <td style="text-align:center; color: var(--text-light);"><?= !empty($req['thoi_gian_bao_luu_den']) ? htmlspecialchars($req['thoi_gian_bao_luu_den']) : '-' ?></td>
                            <td style="text-align:center;">
                                <?php if (!empty($req['link_don_dang_ky'])): ?>
                                    <a href="<?= htmlspecialchars($req['link_don_dang_ky']) ?>" target="_blank" class="icon-link-btn" title="Xem đơn"><i class="fas fa-file-alt"></i></a>
                                <?php else: ?><span style="color:var(--text-light);">-</span><?php endif; ?>
                            </td>
                            <td style="text-align:center;"><?= UIHelper::renderStatusBadge($req['trang_thai']) ?></td>
                            <td style="text-align:left; color:var(--text-mid); white-space:normal; min-width:140px; font-size:0.82rem;"><?= htmlspecialchars($req['ghi_chu']) ?></td>
                            <td style="text-align:center; font-weight:600;"><?= htmlspecialchars($req['so_quyet_dinh']) ?></td>
                            <td style="text-align:center;"><?= htmlspecialchars($req['ngay_quyet_dinh']) ?></td>
                            <td style="text-align:center;">
                                <?php if (!empty($req['link_file_quyet_dinh'])): ?>
                                    <a href="<?= htmlspecialchars($req['link_file_quyet_dinh']) ?>" target="_blank" class="icon-link-btn info" title="Tải file QĐ"><i class="fas fa-file-download"></i></a>
                                <?php else: ?><span style="color:var(--text-light);">-</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== MOBILE: Card view ===== -->
<div class="history-card-view">
    <?php if (empty($requests)): ?>
        <div class="history-empty">
            <i class="fas fa-inbox"></i>
            <p>Chưa có thủ tục nào được đăng ký</p>
        </div>
    <?php else: ?>
        <?php foreach ($requests as $idx => $req): ?>
            <div class="history-card">
                <div class="history-card-header">
                    <div>
                        <span class="history-card-type"><?= htmlspecialchars($req['loai_yeu_cau']) ?></span>
                        <span class="history-card-time"><i class="far fa-clock"></i> <?= htmlspecialchars($req['thoi_gian']) ?></span>
                    </div>
                    <?= UIHelper::renderStatusBadge($req['trang_thai']) ?>
                </div>

                <div class="history-card-body">
                    <?php if (!empty($req['thoi_gian_bao_luu_den'])): ?>
                    <div class="history-card-row">
                        <span class="hc-label">BL đến</span>
                        <span class="hc-value"><?= htmlspecialchars($req['thoi_gian_bao_luu_den']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($req['ghi_chu'])): ?>
                    <div class="history-card-row">
                        <span class="hc-label">Ghi chú</span>
                        <span class="hc-value"><?= htmlspecialchars($req['ghi_chu']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($req['so_quyet_dinh'])): ?>
                    <div class="history-card-row">
                        <span class="hc-label">Số QĐ</span>
                        <span class="hc-value" style="font-weight:600;"><?= htmlspecialchars($req['so_quyet_dinh']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($req['ngay_quyet_dinh'])): ?>
                    <div class="history-card-row">
                        <span class="hc-label">Ngày QĐ</span>
                        <span class="hc-value"><?= htmlspecialchars($req['ngay_quyet_dinh']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($req['link_don_dang_ky']) || !empty($req['link_file_quyet_dinh'])): ?>
                <div class="history-card-footer">
                    <?php if (!empty($req['link_don_dang_ky'])): ?>
                        <a href="<?= htmlspecialchars($req['link_don_dang_ky']) ?>" target="_blank" class="hc-link">
                            <i class="fas fa-file-alt"></i> Xem đơn ĐK
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($req['link_file_quyet_dinh'])): ?>
                        <a href="<?= htmlspecialchars($req['link_file_quyet_dinh']) ?>" target="_blank" class="hc-link info">
                            <i class="fas fa-file-download"></i> File QĐ
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>



</div> <!-- End baoluu-history tab -->

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
    progressText.textContent = 'Đang xử lý...';

    AppFetch.post('api/api_submit_baoluu.php', new FormData(this))
    .then(r => r.json())
    .then(data => {
        spinner.style.display = 'none';
        progress.style.display = 'none';
        btn.disabled = false;
        if (data.success) {
            AppAlert.success('Thành công!', 'Hồ sơ đã được gửi đi thành công.')
            .then(() => { location.reload(); });
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
