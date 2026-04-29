<div class="<?= htmlspecialchars($emptyClass ?? 'history-empty') ?>" <?= isset($isRow) && $isRow ? 'style="text-align:center; color: var(--text-light); padding: 40px 16px;"' : '' ?>>
    <i class="fas fa-inbox" <?= isset($isRow) && $isRow ? 'style="font-size: 1.5rem; display:block; margin-bottom: 8px; opacity:0.4;"' : '' ?>></i>
    <?= isset($isRow) && $isRow ? '' : '<p>' ?><?= htmlspecialchars($emptyMessage ?? 'Chưa có dữ liệu') ?><?= isset($isRow) && $isRow ? '' : '</p>' ?>
</div>
