/**
 * AppAlert & AppFetch - Thư viện dùng chung cho hệ thống
 * - AppAlert: Thông báo đồng nhất (SweetAlert2)
 * - AppFetch: Gửi request với CSRF token tự động
 */

// === CSRF Helper ===
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/**
 * AppFetch - Wrapper fetch() tự động gắn CSRF token
 */
const AppFetch = {
    post: (url, body) => {
        const token = getCsrfToken();
        // Nếu body là FormData, append csrf_token vào
        if (body instanceof FormData) {
            body.append('csrf_token', token);
            return fetch(url, { method: 'POST', body: body });
        }
        // Nếu body là URLSearchParams, append csrf_token
        if (body instanceof URLSearchParams) {
            body.append('csrf_token', token);
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            });
        }
        // Nếu body là object thuần, chuyển thành URLSearchParams
        const params = new URLSearchParams(body);
        params.append('csrf_token', token);
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        });
    }
};

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

    // Prompt popup (nhập liệu)
    prompt: (title, inputLabel, inputValue = '', inputValidator = null) => {
        return Swal.fire({
            title: title,
            input: 'text',
            inputLabel: inputLabel,
            inputValue: inputValue,
            showCancelButton: true,
            confirmButtonText: 'Lưu thay đổi',
            cancelButtonText: 'Hủy',
            confirmButtonColor: '#0f766e',
            inputValidator: inputValidator
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
