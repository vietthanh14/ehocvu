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
            <div class="val" style="display: flex; align-items: center; gap: 10px;">
                <span id="txt-hoten" data-name="<?= htmlspecialchars($student['ho_ten']) ?>" style="font-weight:700;"><?= htmlspecialchars($student['ho_ten']) ?></span>
                <button type="button" aria-label="Cập nhật Họ tên" onclick="editName()" style="background: none; border: none; color: var(--primary); cursor: pointer; padding: 10px; border-radius: 8px; margin-left: -6px;" title="Cập nhật Họ tên">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
        </div>
        <div class="profile-row">
            <div class="lbl">Mã sinh viên</div>
            <div class="val"><span class="tag"><?= htmlspecialchars($student['ma_sv']) ?></span></div>
        </div>
        <div class="profile-row">
            <div class="lbl">Ngày sinh</div>
            <div class="val" style="display: flex; align-items: center; gap: 10px;">
                <span id="txt-ngaysinh" data-dob="<?= htmlspecialchars($student['ngay_sinh']) ?>"><?= htmlspecialchars($student['ngay_sinh']) ?></span>
                <button type="button" aria-label="Cập nhật Ngày sinh" onclick="editDob()" style="background: none; border: none; color: var(--primary); cursor: pointer; padding: 10px; border-radius: 8px; margin-left: -6px;" title="Cập nhật Ngày sinh">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
        </div>
        <div class="profile-row">
            <div class="lbl">SĐT liên hệ</div>
            <div class="val" style="display: flex; align-items: center; gap: 10px;">
                <span id="txt-sdt" data-phone="<?= htmlspecialchars($student['sdt']) ?>"><?= htmlspecialchars($student['sdt']) ?: '<em style="color:var(--text-light);">(Trống)</em>' ?></span>
                <button type="button" aria-label="Cập nhật Số điện thoại" onclick="editPhone()" style="background: none; border: none; color: var(--primary); cursor: pointer; padding: 10px; border-radius: 8px; margin-left: -6px;" title="Cập nhật Số điện thoại">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
        </div>
    </div>


<script>
function editPhone() {
    const currentPhone = document.getElementById('txt-sdt').getAttribute('data-phone') || '';
    AppAlert.prompt('Cập nhật Số điện thoại', 'Nhập số điện thoại liên lạc mới (Zalo/Call):', currentPhone, (value) => {
        if (!value) return 'Số điện thoại không được để trống!';
        if (!/^[0-9]{9,11}$/.test(value.trim())) return 'Số điện thoại không hợp lệ (Chỉ chứa số, độ dài 9-11 ký tự).';
    }).then((result) => {
        if (result.isConfirmed) {
            const newPhone = result.value.trim();
            AppAlert.loading('Đang cập nhật... Vui lòng chờ.');

            AppFetch.post('api/api_update_profile.php', new URLSearchParams({ action: 'update_phone', sdt: newPhone }))
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

function editName() {
    const currentName = document.getElementById('txt-hoten').getAttribute('data-name') || '';
    AppAlert.prompt('Cập nhật Họ và tên', 'Nhập họ và tên mới:', currentName, (value) => {
        if (!value || !value.trim()) return 'Họ tên không được để trống!';
        if (value.trim().length < 3) return 'Họ tên phải có ít nhất 3 ký tự.';
        if (value.trim().length > 100) return 'Họ tên không được vượt quá 100 ký tự.';
    }).then((result) => {
        if (result.isConfirmed) {
            const newName = result.value.trim();
            AppAlert.loading('Đang cập nhật... Vui lòng chờ.');

            AppFetch.post('api/api_update_profile.php', new URLSearchParams({ action: 'update_name', ho_ten: newName }))
            .then(r => r.json())
            .then(response => {
                if(response.success) {
                    const spanName = document.getElementById('txt-hoten');
                    spanName.textContent = response.new_ho_ten;
                    spanName.setAttribute('data-name', response.new_ho_ten);
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

function editDob() {
    const currentDob = document.getElementById('txt-ngaysinh').getAttribute('data-dob') || '';
    AppAlert.prompt('Cập nhật Ngày sinh', 'Nhập ngày sinh mới (VD: 15/08/2002):', currentDob, (value) => {
        if (!value || !value.trim()) return 'Ngày sinh không được để trống!';
        // Kiểm tra sơ bộ định dạng dd/mm/yyyy
        if (!/^\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}$/.test(value.trim())) return 'Định dạng không hợp lệ. VD: 15/08/2002';
    }).then((result) => {
        if (result.isConfirmed) {
            const newDob = result.value.trim();
            AppAlert.loading('Đang cập nhật... Vui lòng chờ.');

            AppFetch.post('api/api_update_profile.php', new URLSearchParams({ action: 'update_dob', ngay_sinh: newDob }))
            .then(r => r.json())
            .then(response => {
                if(response.success) {
                    const spanDob = document.getElementById('txt-ngaysinh');
                    spanDob.textContent = response.new_ngay_sinh;
                    spanDob.setAttribute('data-dob', response.new_ngay_sinh);
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
