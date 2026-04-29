<div id="<?= htmlspecialchars($progressId ?? 'upload-progress') ?>" style="display:none; margin-top:16px;">
    <div style="height:4px; background:var(--border); border-radius:4px; overflow:hidden;">
        <div style="height:100%; width:0%; background:linear-gradient(90deg, var(--primary), var(--primary-light)); animation: progressAnim 2s ease-in-out infinite;"></div>
    </div>
    <p id="<?= htmlspecialchars($progressTextId ?? 'progress-text') ?>" style="font-size:0.8rem; color:var(--text-light); margin-top:8px; text-align:center;">Đang xử lý...</p>
</div>
