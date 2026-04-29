<?php

class UIHelper {
    /**
     * Render HTML badge cho trạng thái đơn
     * @param string $status Trạng thái (VD: Chờ duyệt, Đã duyệt, Từ chối)
     * @return string HTML span tag
     */
    public static function renderStatusBadge(string $status): string {
        $bc = '#94a3b8'; // border & text color (mặc định)
        $bg = '#f1f5f9'; // background (mặc định)

        if (mb_stripos($status, 'duyệt') !== false || mb_stripos($status, 'thành công') !== false) { 
            $bc = '#059669'; $bg = '#ecfdf5'; // Xanh lá
        } elseif (mb_stripos($status, 'Từ chối') !== false || mb_stripos($status, 'hủy') !== false) { 
            $bc = '#dc2626'; $bg = '#fef2f2'; // Đỏ
        } elseif (mb_stripos($status, 'Chờ') !== false) { 
            $bc = '#d97706'; $bg = '#fffbeb'; // Vàng cam
        }

        return sprintf(
            '<span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:0.78rem; font-weight:600; color:%s; background:%s; border:1px solid %s30;">%s</span>',
            $bc, $bg, $bc, htmlspecialchars($status)
        );
    }
}
