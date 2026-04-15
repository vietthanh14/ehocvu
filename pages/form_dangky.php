<?php
if (!isset($_SESSION['student'])) exit;
$student = $_SESSION['student'];

// Lấy danh sách đơn đang chờ để cảnh báo trước trên giao diện
require_once __DIR__ . '/../GoogleSheetService.php';
$service = new GoogleSheetService();
$existingRequests = $service->getStudentRequests($student['ma_sv']);

$pendingTypes = [];
$coQDBaoLuu = false; // Kiểm tra có QĐ bảo lưu nào đã duyệt không

foreach ($existingRequests as $req) {
    $ttLower = mb_strtolower(trim($req['trang_thai']));
    $reqType = trim($req['loai_yeu_cau']);
    
    $isPending = (mb_strpos($ttLower, 'chờ') !== false);

    if ($isPending) {
        $pendingTypes[] = $reqType;
    }

    // Đã duyệt phải không chứa chữ "chờ" (tránh "đang chờ duyệt" match chữ "duyệt")
    if ($reqType === 'Bảo lưu kết quả học tập' && !$isPending && (mb_strpos($ttLower, 'duyệt') !== false || mb_strpos($ttLower, 'thành công') !== false || mb_strpos($ttLower, 'xong') !== false)) {
        $coQDBaoLuu = true;
    }
}
?>

<?php
$warnings = [];

// Thêm cảnh báo cho đơn đang chờ duyệt
foreach ($pendingTypes as $t) {
    $warnings[] = "<strong>" . htmlspecialchars($t) . "</strong> — đang chờ duyệt. Bạn không thể nộp thêm đơn cùng loại.";
}

// Thêm cảnh báo điều kiện về Bảo lưu
$isTiepTucHocPending = in_array('Tiếp tục học sau bảo lưu', $pendingTypes);

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

<form id="requestForm" method="POST" action="submit_request.php" enctype="multipart/form-data">
    <!-- Thông tin SV lấy từ session phía server, không cần gửi qua form -->

    <div class="form-field">
        <label for="loai_yeu_cau">Thủ tục đăng ký <span style="color: var(--danger);">*</span></label>
        <select name="loai_yeu_cau" id="loai_yeu_cau" required>
            <option value="" disabled selected>-- Chọn loại thủ tục --</option>
            <option value="Bảo lưu kết quả học tập" <?= in_array('Bảo lưu kết quả học tập', $pendingTypes) || $coQDBaoLuu || $isTiepTucHocPending ? 'disabled' : '' ?>>
                Bảo lưu kết quả học tập
            </option>
            <option value="Tiếp tục học sau bảo lưu" <?= in_array('Tiếp tục học sau bảo lưu', $pendingTypes) || !$coQDBaoLuu ? 'disabled' : '' ?>>
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
        <div class="file-upload-area" id="dropZone">
            <input type="file" name="file_don" id="file_don" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
            <div class="file-upload-content" id="fileUploadContent">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Kéo thả file vào đây hoặc <strong>nhấn để chọn</strong></p>
                <span>PDF, JPG, PNG, DOC — Tối đa 10MB</span>
            </div>
            <div class="file-upload-preview" id="filePreview" style="display:none;">
                <i class="fas fa-file-check"></i>
                <span id="fileNameDisplay"></span>
                <button type="button" id="fileClear" title="Xóa file"><i class="fas fa-times"></i></button>
            </div>
        </div>
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
.file-upload-area {
    position: relative;
    border: 2px dashed var(--border);
    border-radius: 12px;
    transition: var(--transition);
    cursor: pointer;
    overflow: hidden;
}
.file-upload-area:hover, .file-upload-area.drag-over {
    border-color: var(--primary-light);
    background: rgba(20, 184, 166, 0.03);
}
.file-upload-area input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}
.file-upload-content {
    padding: 32px 20px;
    text-align: center;
    color: var(--text-light);
}
.file-upload-content i {
    font-size: 2rem;
    margin-bottom: 8px;
    display: block;
    color: var(--primary-light);
}
.file-upload-content p {
    font-size: 0.9rem;
    margin-bottom: 4px;
    color: var(--text-mid);
}
.file-upload-content span {
    font-size: 0.78rem;
}
.file-upload-preview {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(20, 184, 166, 0.05);
}
.file-upload-preview i.fa-file-check {
    color: var(--success);
    font-size: 1.2rem;
}
.file-upload-preview span {
    flex: 1;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--text-dark);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.file-upload-preview button {
    background: none;
    border: none;
    color: var(--danger);
    cursor: pointer;
    font-size: 0.9rem;
    padding: 4px 8px;
    border-radius: 6px;
    z-index: 3;
    position: relative;
    transition: var(--transition);
}
.file-upload-preview button:hover {
    background: rgba(239, 68, 68, 0.1);
}
</style>

<script>
// === File upload UI ===
const fileInput = document.getElementById('file_don');
const dropZone = document.getElementById('dropZone');
const uploadContent = document.getElementById('fileUploadContent');
const filePreview = document.getElementById('filePreview');
const fileNameDisplay = document.getElementById('fileNameDisplay');
const fileClear = document.getElementById('fileClear');

const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

fileInput.addEventListener('change', handleFileSelect);

function handleFileSelect() {
    const file = fileInput.files[0];
    if (!file) return;

    if (file.size > MAX_FILE_SIZE) {
        Swal.fire({ icon: 'warning', title: 'File quá lớn', text: 'Vui lòng chọn file nhỏ hơn 10MB.', confirmButtonColor: '#0f766e' });
        fileInput.value = '';
        return;
    }

    uploadContent.style.display = 'none';
    filePreview.style.display = 'flex';
    const sizeMB = (file.size / 1024 / 1024).toFixed(1);
    fileNameDisplay.textContent = file.name + ' (' + sizeMB + ' MB)';
}

fileClear.addEventListener('click', (e) => {
    e.stopPropagation();
    fileInput.value = '';
    uploadContent.style.display = 'block';
    filePreview.style.display = 'none';
});

// Drag & drop
['dragover', 'dragenter'].forEach(ev => {
    dropZone.addEventListener(ev, (e) => { e.preventDefault(); dropZone.classList.add('drag-over'); });
});
['dragleave', 'drop'].forEach(ev => {
    dropZone.addEventListener(ev, () => { dropZone.classList.remove('drag-over'); });
});
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        handleFileSelect();
    }
});

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

    fetch('submit_request.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json())
    .then(data => {
        spinner.style.display = 'none';
        progress.style.display = 'none';
        btn.disabled = false;
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Thành công!', text: 'Hồ sơ đã được gửi đi thành công.', confirmButtonText: 'Đóng', confirmButtonColor: '#0f766e' })
            .then(() => { window.location.href = 'dashboard.php?page=home'; });
        } else {
            Swal.fire({ icon: 'error', title: 'Không thể nộp đơn', text: data.message || 'Đã có lỗi xảy ra.', confirmButtonColor: '#0f766e' });
        }
    })
    .catch(() => {
        spinner.style.display = 'none';
        progress.style.display = 'none';
        btn.disabled = false;
        Swal.fire({ icon: 'error', title: 'Lỗi kết nối', text: 'Không thể kết nối đến máy chủ.', confirmButtonColor: '#0f766e' });
    });
});
</script>
