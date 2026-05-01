<?php
if (!isset($_SESSION['student'])) exit;
$student = $_SESSION['student'];

require_once __DIR__ . '/../core/LeTotNghiepService.php';
$service = new LeTotNghiepService();
$config = $service->getConfig();
$isOpen = (mb_strtolower($config['TrangThai']) === 'mở');
$existing = $isOpen ? $service->findRegistration($student['ma_sv'], $config['TieuDeDot']) : null;
$history = $service->getHistory($student['ma_sv']);

$tabPrefix = 'ltn';
include __DIR__ . '/../includes/components/tabs_nav.php';
?>

<div id="ltn-form" class="tab-pane active">

<?php if (!$isOpen): ?>
    <!-- === ĐÓNG ĐĂNG KÝ === -->
    <div class="notice-card" style="background: linear-gradient(135deg, #fef2f2, #fee2e2); border-color: #fca5a5; text-align: center; padding: 40px 24px;">
        <div style="font-size: 3rem; margin-bottom: 16px;">🎓</div>
        <h6 style="color: #991b1b; font-size: 1.1rem;"><i class="fas fa-lock"></i> Đợt đăng ký đã đóng</h6>
        <p style="color: #b91c1c; margin-top: 8px;"><?= htmlspecialchars($config['ThongBaoDong']) ?></p>
    </div>

<?php else: ?>
    <!-- === THÔNG TIN ĐỢT === -->
    <div class="notice-card" style="margin-bottom: 24px;">
        <h6><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($config['TieuDeDot']) ?></h6>
        <ul>
            <?php if (!empty($config['TuNgay']) || !empty($config['DenNgay'])): ?>
                <li><strong>Thời gian đăng ký:</strong> <?= htmlspecialchars($config['TuNgay']) ?> — <?= htmlspecialchars($config['DenNgay']) ?></li>
            <?php endif; ?>
            <li><strong>Lưu ý:</strong> Sinh viên vui lòng xác nhận tham gia hoặc không tham gia và đăng ký dịch vụ ép plastic / bản sao (nếu cần).</li>
        </ul>
    </div>

    <?php if ($existing !== null): ?>
    <!-- === ĐÃ XÁC NHẬN — HIỂN THỊ TRẠNG THÁI + CHO ĐỔI Ý === -->
    <div class="notice-card" style="background: <?= $existing['xac_nhan'] === 'Tham gia' ? 'linear-gradient(135deg, #f0fdf4, #dcfce7)' : 'linear-gradient(135deg, #fefce8, #fef9c3)' ?>; border-color: <?= $existing['xac_nhan'] === 'Tham gia' ? '#86efac' : '#fde047' ?>; margin-bottom: 24px;">
        <h6 style="color: <?= $existing['xac_nhan'] === 'Tham gia' ? '#166534' : '#854d0e' ?>;">
            <i class="fas fa-<?= $existing['xac_nhan'] === 'Tham gia' ? 'check-circle' : 'times-circle' ?>"></i>
            Bạn đã xác nhận: <strong><?= htmlspecialchars($existing['xac_nhan']) ?></strong>
        </h6>
        <?php if ($existing['xac_nhan'] === 'Tham gia' && (int)$existing['so_khach_moi'] > 0): ?>
            <p style="margin-top: 6px; color: #166534;">Số khách mời đi cùng: <strong><?= htmlspecialchars($existing['so_khach_moi']) ?></strong> người</p>
        <?php endif; ?>

        <?php
        // Hiển thị dịch vụ đã đăng ký
        $dichVu = LeTotNghiepService::DICH_VU;
        $hasService = false;
        foreach ($dichVu as $key => $label) {
            if (($existing[$key] ?? 0) > 0) $hasService = true;
        }
        if ($hasService):
        ?>
        <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px;">
            <?php foreach ($dichVu as $key => $label):
                $sl = $existing[$key] ?? 0;
                if ($sl > 0):
            ?>
                <span class="status-badge success" style="font-size: 0.82rem;">
                    <?= htmlspecialchars($label) ?>: <strong><?= $sl ?></strong>
                </span>
            <?php endif; endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($existing['ghi_chu'])): ?>
            <p style="margin-top: 4px; color: #64748b;">Ghi chú: <?= htmlspecialchars($existing['ghi_chu']) ?></p>
        <?php endif; ?>
        <p style="margin-top: 10px; font-size: 0.85rem; color: #64748b;">
            <i class="far fa-clock"></i> Cập nhật lúc: <?= htmlspecialchars($existing['timestamp']) ?>
            — Bạn có thể đổi ý bên dưới.
        </p>
    </div>
    <?php endif; ?>

    <!-- === FORM XÁC NHẬN === -->
    <form id="ltnForm" method="POST" action="api/api_submit_letotnghiep.php">

        <div class="form-field">
            <label>Xác nhận tham gia <span style="color: var(--danger);">*</span></label>
            <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-top: 6px;">
                <label class="radio-card" style="flex: 1; min-width: 160px;">
                    <input type="radio" name="xac_nhan" value="Tham gia" <?= ($existing && $existing['xac_nhan'] === 'Tham gia') ? 'checked' : (!$existing ? 'checked' : '') ?> required>
                    <div class="radio-card-body">
                        <i class="fas fa-check-circle" style="color: #22c55e; font-size: 1.5rem;"></i>
                        <span style="font-weight: 600; font-size: 1rem;">Tham gia</span>
                        <span style="font-size: 0.8rem; color: var(--text-light);">Tôi sẽ dự Lễ Tốt nghiệp</span>
                    </div>
                </label>
                <label class="radio-card" style="flex: 1; min-width: 160px;">
                    <input type="radio" name="xac_nhan" value="Không tham gia" <?= ($existing && $existing['xac_nhan'] === 'Không tham gia') ? 'checked' : '' ?>>
                    <div class="radio-card-body">
                        <i class="fas fa-times-circle" style="color: #ef4444; font-size: 1.5rem;"></i>
                        <span style="font-weight: 600; font-size: 1rem;">Không tham gia</span>
                        <span style="font-size: 0.8rem; color: var(--text-light);">Tôi không dự lễ</span>
                    </div>
                </label>
            </div>
        </div>

        <div class="form-field" id="row-khach-moi">
            <label for="so_khach_moi">Số khách mời đi cùng</label>
            <select name="so_khach_moi" id="so_khach_moi">
                <option value="0" <?= ($existing && $existing['so_khach_moi'] == '0') ? 'selected' : '' ?>>0 — Không có khách mời</option>
                <option value="1" <?= ($existing && $existing['so_khach_moi'] == '1') ? 'selected' : '' ?>>1 người</option>
                <option value="2" <?= ($existing && $existing['so_khach_moi'] == '2') ? 'selected' : '' ?>>2 người</option>
            </select>
            <span class="form-hint">Tối đa 2 khách mời (gia đình, bạn bè)</span>
        </div>

        <!-- === ĐĂNG KÝ DỊCH VỤ === -->
        <div class="form-field">
                <label><i class="fas fa-id-card"></i> Đăng ký dịch vụ ép plastic / bản sao <span style="font-weight: 400; color: var(--text-light);">(không bắt buộc)</span></label>
                <div class="epp-services-grid" style="margin-top: 8px;">
                    <?php
                    $icons = [
                        'ep_vanbang'   => ['🎓', '#059669', '#ecfdf5', '#d1fae5', 1],
                        'ep_bangdiem'  => ['📋', '#2563eb', '#eff6ff', '#dbeafe', 1],
                        'bansao_bd'    => ['📑', '#d97706', '#fffbeb', '#fef3c7', 5],
                        'ep_bansao_bd' => ['✨', '#7c3aed', '#f5f3ff', '#ede9fe', 5],
                    ];
                    foreach (LeTotNghiepService::DICH_VU as $key => $label):
                        $icon = $icons[$key];
                        $curVal = $existing[$key] ?? 0;
                        $maxQty = $icon[4];
                    ?>
                    <div class="epp-service-card" style="--card-accent: <?= $icon[1] ?>; --card-bg: <?= $icon[2] ?>; --card-border: <?= $icon[3] ?>;">
                        <div class="epp-service-icon"><?= $icon[0] ?></div>
                        <div class="epp-service-info">
                            <span class="epp-service-name"><?= htmlspecialchars($label) ?></span>
                            <div class="epp-qty-wrap">
                                <label for="qty_<?= $key ?>">SL:</label>
                                <select name="<?= $key ?>" id="qty_<?= $key ?>">
                                    <?php for ($i = 0; $i <= $maxQty; $i++): ?>
                                        <option value="<?= $i ?>" <?= $curVal == $i ? 'selected' : '' ?>><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
            </div>
        </div>

        <div class="form-field">
            <label for="ghi_chu">Ghi chú (không bắt buộc)</label>
            <textarea name="ghi_chu" id="ghi_chu" rows="3" placeholder="VD: Cần hỗ trợ chỗ ngồi cho người khuyết tật..."><?= $existing ? htmlspecialchars($existing['ghi_chu']) : '' ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" id="btn-submit" class="btn-primary-custom">
                <span class="spinner-custom" id="spinner-submit"></span>
                <i class="fas fa-paper-plane"></i>
                <?= $existing ? 'Cập nhật xác nhận' : 'Gửi xác nhận' ?>
            </button>
        </div>
    </form>
<?php endif; ?>

</div> <!-- End ltn-form tab -->

<div id="ltn-history" class="tab-pane">

<!-- ===== DESKTOP: Table view ===== -->
<div class="history-table-view">
    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width:5%; text-align:center;">STT</th>
                    <th style="text-align:left;">Thời gian</th>
                    <th style="text-align:left;">Đợt</th>
                    <th style="text-align:center;">Xác nhận</th>
                    <th style="text-align:center;">Khách</th>
                    <th style="text-align:center;">Dịch vụ</th>
                    <th style="text-align:left;">Ghi chú</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="7" style="padding: 0;">
                            <?php
                            $isRow = true;
                            $emptyMessage = 'Chưa có lịch sử đăng ký dự lễ tốt nghiệp';
                            include __DIR__ . '/../includes/components/empty_state.php';
                            ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($history as $idx => $h): ?>
                        <tr>
                            <td style="text-align:center;"><?= $idx + 1 ?></td>
                            <td style="text-align:left; color: var(--text-light);"><i class="far fa-clock" style="margin-right:4px;"></i> <?= htmlspecialchars($h['timestamp']) ?></td>
                            <td style="text-align:left; font-weight:600; color: var(--primary);"><?= htmlspecialchars($h['tieu_de_dot']) ?></td>
                            <td style="text-align:center;">
                                <?php if ($h['xac_nhan'] === 'Tham gia'): ?>
                                    <span class="status-badge success"><i class="fas fa-check"></i> Tham gia</span>
                                <?php else: ?>
                                    <span class="status-badge warning"><i class="fas fa-times"></i> Không</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;"><?= htmlspecialchars($h['so_khach_moi']) ?></td>
                            <td style="text-align:center;">
                                <?php
                                $services = [];
                                foreach (LeTotNghiepService::DICH_VU as $k => $l) {
                                    if (($h[$k] ?? 0) > 0) $services[] = $l . ':' . $h[$k];
                                }
                                echo $services ? htmlspecialchars(implode(', ', $services)) : '<span style="color: var(--text-light);">—</span>';
                                ?>
                            </td>
                            <td style="text-align:left; color:var(--text-mid); font-size:0.82rem;"><?= htmlspecialchars($h['ghi_chu']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== MOBILE: Card view ===== -->
<div class="history-card-view">
    <?php if (empty($history)): ?>
        <?php
        $emptyMessage = 'Chưa có lịch sử đăng ký dự lễ tốt nghiệp';
        include __DIR__ . '/../includes/components/empty_state.php';
        ?>
    <?php else: ?>
        <?php foreach ($history as $idx => $h): ?>
            <div class="history-card">
                <div class="history-card-header">
                    <div>
                        <span class="history-card-type"><?= htmlspecialchars($h['tieu_de_dot']) ?></span>
                        <span class="history-card-time"><i class="far fa-clock"></i> <?= htmlspecialchars($h['timestamp']) ?></span>
                    </div>
                    <?php if ($h['xac_nhan'] === 'Tham gia'): ?>
                        <span class="status-badge success"><i class="fas fa-check"></i> Tham gia</span>
                    <?php else: ?>
                        <span class="status-badge warning"><i class="fas fa-times"></i> Không</span>
                    <?php endif; ?>
                </div>
                <div class="history-card-body">
                    <div class="history-card-row">
                        <span class="hc-label">Khách mời</span>
                        <span class="hc-value"><?= htmlspecialchars($h['so_khach_moi']) ?> người</span>
                    </div>
                    <?php foreach (LeTotNghiepService::DICH_VU as $k => $l):
                        if (($h[$k] ?? 0) > 0):
                    ?>
                    <div class="history-card-row">
                        <span class="hc-label"><?= htmlspecialchars($l) ?></span>
                        <span class="hc-value"><?= $h[$k] ?></span>
                    </div>
                    <?php endif; endforeach; ?>
                    <?php if (!empty($h['ghi_chu'])): ?>
                    <div class="history-card-row">
                        <span class="hc-label">Ghi chú</span>
                        <span class="hc-value"><?= htmlspecialchars($h['ghi_chu']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</div> <!-- End ltn-history tab -->

<script>
// === Toggle khách mời khi chọn "Không tham gia" (dịch vụ luôn hiển thị) ===
document.querySelectorAll('input[name="xac_nhan"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const rowKhach = document.getElementById('row-khach-moi');
        if (this.value === 'Không tham gia') {
            rowKhach.style.display = 'none';
            document.getElementById('so_khach_moi').value = '0';
        } else {
            rowKhach.style.display = 'block';
        }
    });
});

// Trigger on load
(function() {
    const checked = document.querySelector('input[name="xac_nhan"]:checked');
    if (checked && checked.value === 'Không tham gia') {
        document.getElementById('row-khach-moi').style.display = 'none';
    }
})();

// === Form submit ===
AppForm.handleSubmit('ltnForm', 'api/api_submit_letotnghiep.php');
</script>

<style>
/* Radio Card Styling */
.radio-card {
    cursor: pointer;
    display: block;
}
.radio-card input[type="radio"] {
    display: none;
}
.radio-card-body {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 20px 16px;
    border: 2px solid var(--border);
    border-radius: 12px;
    background: var(--bg-card);
    transition: all 0.25s ease;
    text-align: center;
}
.radio-card input[type="radio"]:checked + .radio-card-body {
    border-color: var(--primary);
    background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(99,102,241,0.1));
    box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
}
.radio-card:hover .radio-card-body {
    border-color: var(--primary-light);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

/* Service Cards Grid */
.epp-services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 14px;
}
.epp-service-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    border: 2px solid var(--card-border);
    border-radius: 12px;
    background: var(--card-bg);
    transition: all 0.25s ease;
}
.epp-service-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    border-color: var(--card-accent);
}
.epp-service-icon {
    font-size: 2rem;
    flex-shrink: 0;
}
.epp-service-info {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
}
.epp-service-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-dark);
}
.epp-qty-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
}
.epp-qty-wrap label {
    font-size: 0.82rem;
    color: var(--text-light);
    margin: 0;
    white-space: nowrap;
}
.epp-qty-wrap select {
    padding: 5px 10px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-size: 0.85rem;
    background: #fff;
    cursor: pointer;
    min-width: 55px;
}
.epp-qty-wrap select:focus {
    border-color: var(--card-accent);
    outline: none;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}
</style>
