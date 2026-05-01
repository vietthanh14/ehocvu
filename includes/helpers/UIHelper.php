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

        if (mb_stripos($status, 'duyệt') !== false || mb_stripos($status, 'thành công') !== false || mb_stripos($status, 'tham gia') !== false) { 
            $bc = 'var(--status-success-text)'; $bg = 'var(--status-success-bg)'; // Xanh lá
        } elseif (mb_stripos($status, 'Từ chối') !== false || mb_stripos($status, 'hủy') !== false || mb_stripos($status, 'không tham gia') !== false || mb_stripos($status, 'không') === 0) { 
            $bc = 'var(--status-danger-text)'; $bg = 'var(--status-danger-bg)'; // Đỏ
        } elseif (mb_stripos($status, 'Chờ') !== false) { 
            $bc = 'var(--status-warning-text)'; $bg = 'var(--status-warning-bg)'; // Vàng cam
        }

        return sprintf(
            '<span class="status-badge" style="background:%s; color:%s;">%s</span>',
            $bg, $bc, htmlspecialchars($status)
        );
    }

    /**
     * Render thẻ thông báo (Notice Card)
     * @param string $type Loại thông báo: 'info', 'success', 'warning', 'danger'
     * @param string $title Tiêu đề thẻ (bao gồm icon nếu có)
     * @param array $messages Danh sách các chuỗi thông báo (<li>)
     * @return string HTML
     */
    public static function renderNoticeCard(string $type, string $title, array $messages): string {
        $styles = [
            'info' => ['bg' => 'var(--notice-info-bg)', 'border' => '1px solid var(--notice-info-border)', 'text' => 'var(--notice-info-text)', 'icon' => 'fa-info-circle'],
            'success' => ['bg' => 'var(--notice-success-bg)', 'border' => '1px solid var(--notice-success-border)', 'text' => 'var(--notice-success-text)', 'icon' => 'fa-check-circle'],
            'warning' => ['bg' => 'var(--notice-warning-bg)', 'border' => '1px solid var(--notice-warning-border)', 'text' => 'var(--notice-warning-text)', 'icon' => 'fa-exclamation-triangle'],
            'danger' => ['bg' => 'var(--notice-danger-bg)', 'border' => '1px solid var(--notice-danger-border)', 'text' => 'var(--notice-danger-text)', 'icon' => 'fa-times-circle']
        ];

        $theme = $styles[$type] ?? $styles['info'];
        
        $html = '<div class="notice-card" style="background: '.$theme['bg'].'; border: '.$theme['border'].';">';
        $html .= '<h6 style="color: ' . $theme['text'] . ';"><i class="fas ' . $theme['icon'] . '"></i> ' . $title . '</h6>';
        if (!empty($messages)) {
            $html .= '<ul>';
            foreach ($messages as $msg) {
                $html .= '<li style="color: ' . $theme['text'] . ';">' . $msg . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</div>';

        return $html;
    }
}
