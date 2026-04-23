<?php
if (!isset($_SESSION['student'])) exit;
$student = $_SESSION['student'];
?>
<div class="profile-grid">
    <div class="profile-section">
        <div class="profile-section-header">
            <div class="icon-sm teal"><i class="fas fa-id-card"></i></div>
            <h5>Thông tin chung</h5>
        </div>
        <div class="profile-row">
            <div class="lbl">Họ và tên</div>
            <div class="val" style="font-weight:700;"><?= htmlspecialchars($student['ho_ten']) ?></div>
        </div>
        <div class="profile-row">
            <div class="lbl">Mã sinh viên</div>
            <div class="val"><span class="tag"><?= htmlspecialchars($student['ma_sv']) ?></span></div>
        </div>
        <div class="profile-row">
            <div class="lbl">Ngày sinh</div>
            <div class="val"><?= htmlspecialchars($student['ngay_sinh']) ?></div>
        </div>
        <div class="profile-row">
            <div class="lbl">SĐT liên hệ</div>
            <div class="val" style="display: flex; align-items: center; gap: 10px;">
                <span id="txt-sdt" data-phone="<?= htmlspecialchars($student['sdt']) ?>"><?= htmlspecialchars($student['sdt']) ?: '<em style="color:var(--text-light);">(Trống)</em>' ?></span>
                <button type="button" onclick="editPhone()" style="background: none; border: none; color: var(--primary); cursor: pointer; padding: 4px; border-radius: 4px;" title="Cập nhật Số điện thoại">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
        </div>
    </div>


<script>
function editPhone() {
    const currentPhone = document.getElementById('txt-sdt').getAttribute('data-phone') || '';
    Swal.fire({
        title: 'Cập nhật Số điện thoại',
        input: 'text',
        inputLabel: 'Nhập số điện thoại liên lạc mới (Zalo/Call):',
        inputValue: currentPhone,
        showCancelButton: true,
        confirmButtonText: 'Lưu thay đổi',
        cancelButtonText: 'Hủy',
        inputValidator: (value) => {
            if (!value) {
                return 'Số điện thoại không được để trống!'
            }
            if (!/^[0-9]{9,11}$/.test(value.trim())) {
                return 'Số điện thoại không hợp lệ (Chỉ chứa số, độ dài 9-11 tự).'
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const newPhone = result.value.trim();
            
            // Hiện loading
            AppAlert.loading('Đang cập nhật... Vui lòng chờ.');

            // Gửi API
            fetch('api/api_update_profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ sdt: newPhone })
            })
            .then(r => r.json())
            .then(response => {
                if(response.success) {
                    const spanSdt = document.getElementById('txt-sdt');
                    spanSdt.textContent = response.new_sdt;
                    spanSdt.setAttribute('data-phone', response.new_sdt);
                    AppAlert.success('Thành công!', response.message);
                } else {
                    AppAlert.error('Lỗi', response.message);
                }
            })
            .catch(e => {
                AppAlert.error('Lỗi', 'Không thể kết nối đến máy chủ.');
            });
        }
    });
}
</script>

    <div class="profile-section">
        <div class="profile-section-header">
            <div class="icon-sm green"><i class="fas fa-graduation-cap"></i></div>
            <h5>Thông tin Học tập</h5>
        </div>
        <div class="profile-row">
            <div class="lbl">Khoa</div>
            <div class="val" style="font-weight:600;"><?= htmlspecialchars($student['ten_khoa']) ?></div>
        </div>
        <div class="profile-row">
            <div class="lbl">Hệ đào tạo</div>
            <div class="val"><?= htmlspecialchars($student['ten_he']) ?></div>
        </div>
        <div class="profile-row">
            <div class="lbl">Lớp</div>
            <div class="val"><?= htmlspecialchars($student['ten_lop']) ?></div>
        </div>
        <div class="profile-row">
            <div class="lbl">Chuyên ngành</div>
            <div class="val"><?= htmlspecialchars($student['chuyen_nganh']) ?></div>
        </div>
        <div class="profile-row">
            <div class="lbl">Niên khóa</div>
            <div class="val"><?= htmlspecialchars($student['nien_khoa']) ?></div>
        </div>
    </div>
</div>
