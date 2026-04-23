<?php
if (!isset($_SESSION['student'])) exit;

require_once __DIR__ . '/../core/GoogleSheetService.php';
$service = GoogleSheetService::getInstance();
$requests = $service->getStudentRequests($_SESSION['student']['ma_sv']);

require_once __DIR__ . '/../core/UIHelper.php';
?>

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

<style>
/* Desktop: show table, hide cards */
.history-table-view { display: block; }
.history-card-view { display: none; }

/* Mobile: hide table, show cards */
@media (max-width: 768px) {
    .history-table-view { display: none; }
    .history-card-view { display: block; }
}

/* === Card styles === */
.history-empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-light);
}
.history-empty i {
    font-size: 2rem;
    opacity: 0.3;
    display: block;
    margin-bottom: 10px;
}
.history-empty p {
    font-size: 0.9rem;
}

.history-card {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 12px;
    margin-bottom: 12px;
    overflow: hidden;
    transition: var(--transition);
}
.history-card:hover {
    box-shadow: var(--shadow-md, 0 4px 12px rgba(0,0,0,0.06));
}

.history-card-header {
    padding: 14px 16px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    border-bottom: 1px solid #f1f5f9;
}
.history-card-type {
    display: block;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--primary, #0f766e);
    margin-bottom: 4px;
}
.history-card-time {
    font-size: 0.75rem;
    color: var(--text-light, #94a3b8);
}
.history-card-time i { margin-right: 3px; }

.history-card-body {
    padding: 10px 16px;
}
.history-card-row {
    display: flex;
    align-items: flex-start;
    padding: 6px 0;
    gap: 8px;
}
.hc-label {
    flex-shrink: 0;
    width: 70px;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-light, #94a3b8);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding-top: 1px;
}
.hc-value {
    font-size: 0.85rem;
    color: var(--text-dark, #1e293b);
    word-break: break-word;
}

.history-card-footer {
    padding: 10px 16px 14px;
    display: flex;
    gap: 8px;
    border-top: 1px solid #f1f5f9;
}
.hc-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    text-decoration: none;
    background: rgba(20, 184, 166, 0.08);
    color: var(--primary, #0f766e);
    transition: var(--transition);
}
.hc-link:hover {
    background: rgba(20, 184, 166, 0.15);
}
.hc-link.info {
    background: rgba(14, 165, 233, 0.08);
    color: #0284c7;
}
.hc-link.info:hover {
    background: rgba(14, 165, 233, 0.15);
}
</style>
