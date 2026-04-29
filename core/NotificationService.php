<?php
require_once __DIR__ . '/GoogleSheetClient.php';

class NotificationService {
    private GoogleSheetClient $client;
    
    private const CACHE_TTL = [
        'notifications' => 120
    ];

    public function __construct() {
        $this->client = GoogleSheetClient::getInstance();
    }

    private function fetchNotificationSheet(): ?array {
        if (!defined('SHEET_NOTIFICATION')) return null;
        try {
            return $this->client->fetchSheetDataCached('notifications', SHEET_NOTIFICATION, self::CACHE_TTL['notifications'], true);
        } catch (Exception $e) {
            return null;
        }
    }

    public function getGlobalNotifications(): array {
        $notiValues = $this->fetchNotificationSheet();
        $messages = [];
        if (!empty($notiValues)) {
            foreach ($notiValues as $nRow) {
                $nMaSv = isset($nRow[0]) ? trim($nRow[0]) : '';
                $nMsg = isset($nRow[1]) ? trim($nRow[1]) : '';
                
                if ((strtoupper($nMaSv) === 'ALL' || $nMaSv === '*') && $nMsg !== '') {
                    $safeMsg = htmlspecialchars($nMsg, ENT_QUOTES, 'UTF-8');
                    $processedMsg = preg_replace_callback(
                        '#\bhttps?://[^\s()<>]+#i',
                        function($matches) {
                            $url = $matches[0];
                            return '<a href="'.$url.'" target="_blank" style="text-decoration: underline; color: #14b8a6; font-weight: 700;">[<i class="fas fa-external-link-alt" style="font-size: 0.8rem;"></i> Truy cập Link]</a>';
                        },
                        $safeMsg
                    );
                    $messages[] = nl2br($processedMsg);
                }
            }
        }
        return $messages;
    }
}
