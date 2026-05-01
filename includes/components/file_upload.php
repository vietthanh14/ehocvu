<?php
/**
 * Component Upload File dùng chung
 * Đầu vào (Variables):
 * - $inputName: Tên của thẻ input file (mặc định: 'file_don')
 * - $isRequired: Có bắt buộc không (boolean, mặc định: true)
 * - $hintText: Text hướng dẫn phụ (mặc định: 'PDF, JPG, PNG, DOC — Tối đa 10MB')
 */

$inputName = $inputName ?? 'file_don';
$isRequired = $isRequired ?? true;
$hintText = $hintText ?? 'PDF, JPG, PNG, DOC, DOCX — Tối đa 10MB';
$idPrefix = $inputName . '_';
?>

<div class="file-upload-area" id="<?= $idPrefix ?>dropZone">
    <input type="file" name="<?= $inputName ?>" id="<?= $idPrefix ?>input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" <?= $isRequired ? 'required' : '' ?>>
    <div class="file-upload-content" id="<?= $idPrefix ?>content">
        <i class="fas fa-cloud-upload-alt"></i>
        <p>Kéo thả file vào đây hoặc <strong>nhấn để chọn</strong></p>
        <span><?= htmlspecialchars($hintText) ?></span>
    </div>
    <div class="file-upload-preview" id="<?= $idPrefix ?>preview" style="display:none;">
        <i class="fas fa-file-check"></i>
        <span id="<?= $idPrefix ?>nameDisplay"></span>
        <button type="button" id="<?= $idPrefix ?>clear" title="Xóa file"><i class="fas fa-times"></i></button>
    </div>
</div>

<style>
.file-upload-area {
    position: relative;
    border: 2px dashed var(--border);
    border-radius: 12px;
    transition: var(--transition);
    cursor: pointer;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.03);
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
(function() {
    const fileInput = document.getElementById('<?= $idPrefix ?>input');
    const dropZone = document.getElementById('<?= $idPrefix ?>dropZone');
    const uploadContent = document.getElementById('<?= $idPrefix ?>content');
    const filePreview = document.getElementById('<?= $idPrefix ?>preview');
    const fileNameDisplay = document.getElementById('<?= $idPrefix ?>nameDisplay');
    const fileClear = document.getElementById('<?= $idPrefix ?>clear');

    const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    if (!fileInput) return; // Prevent errors if loaded multiple times without distinct IDs (though style is duplicated, it's fine for simple use)

    fileInput.addEventListener('change', handleFileSelect);

    function handleFileSelect() {
        const file = fileInput.files[0];
        if (!file) return;

        if (file.size > MAX_FILE_SIZE) {
            if (typeof AppAlert !== 'undefined') {
                AppAlert.warning('File quá lớn', 'Vui lòng chọn file nhỏ hơn 10MB.');
            } else {
                Swal.fire({ icon: 'warning', title: 'File quá lớn', text: 'Vui lòng chọn file nhỏ hơn 10MB.', confirmButtonColor: '#0f766e' });
            }
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
})();
</script>
