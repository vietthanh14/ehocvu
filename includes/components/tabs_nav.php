<div class="tabs-nav">
    <button type="button" class="tab-btn active" onclick="switchTab('<?= htmlspecialchars($tabPrefix ?? '') ?>-form', this)">
        <i class="fas fa-plus-circle"></i> Tạo đơn mới
    </button>
    <button type="button" class="tab-btn" onclick="switchTab('<?= htmlspecialchars($tabPrefix ?? '') ?>-history', this)">
        <i class="fas fa-history"></i> Lịch sử đơn
    </button>
</div>
