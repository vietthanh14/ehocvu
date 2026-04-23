<?php
class Response {
    public static function json(bool $success, string $message, array $data = []): void {
        header('Content-Type: application/json; charset=utf-8');
        $response = ['success' => $success, 'message' => $message];
        if (!empty($data)) {
            $response = array_merge($response, $data);
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(string $message = '', array $data = []): void {
        self::json(true, $message, $data);
    }

    public static function error(string $message): void {
        self::json(false, $message);
    }
}
