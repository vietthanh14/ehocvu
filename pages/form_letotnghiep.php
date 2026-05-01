<?php
if (!isset($_SESSION['student'])) exit;
$student = $_SESSION['student'];

require_once __DIR__ . '/../core/LeTotNghiepService.php';
require_once __DIR__ . '/../includes/helpers/UIHelper.php';
$service = new LeTotNghiepService();
$config = $service->getConfig();
$isOpen = (mb_strtolower($config['TrangThai']) === 'mở');
$isEligible = $isOpen ? $service->isEligible($student['ma_sv']) : false;
$existing = ($isOpen && $isEligible) ? $service->findRegistration($student['ma_sv'], $config['TieuDeDot']) : null;
$history = $service->getHistory($student['ma_sv']);

$tabPrefix = 'ltn';
include __DIR__ . '/../includes/components/tabs_nav.php';
?>

<div id="ltn-form" class="tab-pane active">

<?php if (!$isOpen): ?>
    <?= UIHelper::renderNoticeCard('danger', 'ĐỢT ĐĂNG KÝ ĐÃ ĐÓNG', [htmlspecialchars($config['ThongBaoDong'])]) ?>
<?php elseif (!$isEligible): ?>
    <?= UIHelper::renderNoticeCard('danger', 'KHÔNG ĐỦ ĐIỀU KIỆN', [
        'Bạn không có tên trong danh sách được công nhận tốt nghiệp đợt này.',
        'Nếu có sai sót, vui lòng liên hệ Phòng Đào tạo.'
    ]) ?>
<?php else: ?>
    <?php
    $heroMessages = [];
    if (!empty($config['TuNgay']) || !empty($config['DenNgay'])) {
        $heroMessages[] = '<strong>Thời gian đăng ký:</strong> ' . htmlspecialchars($config['TuNgay']) . ' — ' . htmlspecialchars($config['DenNgay']);
    }
    $heroMessages[] = 'Sinh viên vui lòng xác nhận trạng thái tham dự và đăng ký số lượng khách mời (nếu có).';
    $heroMessages[] = 'Có thể đăng ký các dịch vụ bổ sung như ép plastic văn bằng, bảng điểm, v.v.';
    echo UIHelper::renderNoticeCard('info', htmlspecialchars($config['TieuDeDot']), $heroMessages);
    ?>



    <form id="ltnForm" method="POST" action="api/api_submit_letotnghiep.php">

        <div class="form-field">
            <label>Trạng thái tham dự <span style="color: var(--danger);">*</span></label>
            <div style="display: flex; gap: 20px; align-items: center; padding: 8px 0;">
                <label style="font-size: 0.9rem; font-weight: 500; color: var(--text-dark); text-transform: none; display: flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="radio" name="xac_nhan" value="Tham gia" <?= ($existing && $existing['xac_nhan'] === 'Tham gia') ? 'checked' : (!$existing ? 'checked' : '') ?> required>
                    Tham gia lễ tốt nghiệp
                </label>
                <label style="font-size: 0.9rem; font-weight: 500; color: var(--text-dark); text-transform: none; display: flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="radio" name="xac_nhan" value="Không tham gia" <?= ($existing && $existing['xac_nhan'] === 'Không tham gia') ? 'checked' : '' ?>>
                    Không tham gia
                </label>
            </div>
        </div>

        <div class="form-field" id="row-khach-moi">
            <label for="so_khach_moi">Số lượng khách mời đi cùng</label>
            <select name="so_khach_moi" id="so_khach_moi">
                <option value="0" <?= ($existing && $existing['so_khach_moi'] == '0') ? 'selected' : '' ?>>0 (Không có khách mời đi cùng)</option>
                <option value="1" <?= ($existing && $existing['so_khach_moi'] == '1') ? 'selected' : '' ?>>1 Người</option>
                <option value="2" <?= ($existing && $existing['so_khach_moi'] == '2') ? 'selected' : '' ?>>2 Người (Tối đa)</option>
            </select>
            <span class="form-hint">Khách mời là phụ huynh, người thân hoặc bạn bè.</span>
        </div>

        <div class="form-field">
            <label>Đăng ký dịch vụ thêm (Không bắt buộc)</label>
            <div style="background: var(--input-bg); border: 1.5px solid var(--border); border-radius: 10px; padding: 16px;">
                <?php
                $maxQties = [
                    'ep_vanbang'   => 1,
                    'ep_bangdiem'  => 1,
                    'bansao_bd'    => 5,
                    'ep_bansao_bd' => 5,
                ];
                foreach (LeTotNghiepService::DICH_VU as $key => $label):
                    $curVal = $existing[$key] ?? 0;
                    $maxQty = $maxQties[$key];
                ?>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed var(--border);">
                    <span style="font-size: 0.9rem; color: var(--text-dark); font-weight: 500;"><?= htmlspecialchars($label) ?></span>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 0.85rem; color: var(--text-mid);">Số lượng:</span>
                        <select name="<?= $key ?>" id="qty_<?= $key ?>" style="padding: 4px 8px; width: 60px; border-radius: 6px; border: 1px solid var(--border); outline: none;">
                            <?php for ($i = 0; $i <= $maxQty; $i++): ?>
                                <option value="<?= $i ?>" <?= $curVal == $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-field">
            <label for="ghi_chu">Ghi chú thêm</label>
            <textarea name="ghi_chu" id="ghi_chu" rows="3" placeholder="Ghi chú nếu bạn cần hỗ trợ đặc biệt (VD: Cần xe lăn, chỗ ngồi cho người lớn tuổi...)"><?= $existing ? htmlspecialchars($existing['ghi_chu']) : '' ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" id="btn-submit" class="btn-primary-custom">
                <span class="spinner-custom" id="spinner-submit"></span>
                <i class="fas fa-paper-plane"></i> <?= $existing ? 'Cập nhật đăng ký' : 'Xác nhận đăng ký' ?>
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
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="6" style="padding: 0;">
                            <?php 
                            $isRow = true; 
                            $emptyMessage = 'Chưa có lịch sử đăng ký tốt nghiệp'; 
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
                                <?= UIHelper::renderStatusBadge($h['xac_nhan']) ?>
                            </td>
                            <td style="text-align:center; color: var(--text-dark);"><?= htmlspecialchars($h['so_khach_moi'] ?? 0) ?></td>
                            <td style="text-align:center; font-size: 0.85rem; color: var(--text-mid);">
                                <?php
                                $services = [];
                                foreach (LeTotNghiepService::DICH_VU as $k => $l) {
                                    if (($h[$k] ?? 0) > 0) $services[] = $l . ': ' . $h[$k];
                                }
                                echo $services ? htmlspecialchars(implode(', ', $services)) : '-';
                                ?>
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
    <?php if (empty($history)): ?>
        <?php 
        $emptyMessage = 'Chưa có lịch sử đăng ký tốt nghiệp'; 
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
                        <?= UIHelper::renderStatusBadge($h['xac_nhan']) ?>
                </div>
                <div class="history-card-body">
                    <?php if ($h['xac_nhan'] === 'Tham gia' && ($h['so_khach_moi'] ?? 0) > 0): ?>
                    <div class="history-card-row">
                        <span class="hc-label">Khách</span>
                        <span class="hc-value"><?= htmlspecialchars($h['so_khach_moi']) ?> người</span>
                    </div>
                    <?php endif; ?>

                    <?php
                    $services = [];
                    foreach (LeTotNghiepService::DICH_VU as $k => $l) {
                        if (($h[$k] ?? 0) > 0) $services[] = $l . ': ' . $h[$k];
                    }
                    if ($services):
                    ?>
                    <div class="history-card-row">
                        <span class="hc-label">Dịch vụ</span>
                        <span class="hc-value"><?= htmlspecialchars(implode(', ', $services)) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</div> <!-- End ltn-history tab -->

<script>
// === Form logic ===
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

(function() {
    const checked = document.querySelector('input[name="xac_nhan"]:checked');
    if (checked && checked.value === 'Không tham gia') {
        document.getElementById('row-khach-moi').style.display = 'none';
    }
})();

// === Form submit ===
AppForm.handleSubmit('ltnForm', 'api/api_submit_letotnghiep.php');
</script>
