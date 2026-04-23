/**
 * AppAlert - Thư viện dùng chung cho các thông báo toàn hệ thống
 * Sử dụng SweetAlert2 để đồng bộ giao diện
 */

const AppToast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    }
});

const AppAlert = {
    // Popup thông báo lớn giữa màn hình
    success: (title, text = '') => {
        return Swal.fire({ icon: 'success', title: title, text: text, confirmButtonColor: '#0f766e', confirmButtonText: 'Đóng' });
    },
    error: (title, text = '') => {
        return Swal.fire({ icon: 'error', title: title, text: text, confirmButtonColor: '#0f766e', confirmButtonText: 'Đóng' });
    },
    warning: (title, text = '') => {
        return Swal.fire({ icon: 'warning', title: title, text: text, confirmButtonColor: '#0f766e', confirmButtonText: 'Đóng' });
    },
    info: (title, text = '') => {
        return Swal.fire({ icon: 'info', title: title, text: text, confirmButtonColor: '#0f766e', confirmButtonText: 'Đóng' });
    },
    
    // Loading popup
    loading: (title = 'Đang xử lý...') => {
        return Swal.fire({
            title: title,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    },
    close: () => {
        Swal.close();
    },

    // Toast thông báo nhỏ góc màn hình
    toastSuccess: (title) => {
        AppToast.fire({ icon: 'success', title: title });
    },
    toastError: (title) => {
        AppToast.fire({ icon: 'error', title: title });
    },
    toastWarning: (title) => {
        AppToast.fire({ icon: 'warning', title: title });
    }
};
