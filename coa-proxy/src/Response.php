<?php

namespace CoaProxy;

class Response
{
    /**
     * Send JSON success response
     */
    public static function json(array $data, int $statusCode = 200, array $headers = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        // Security Headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Content-Security-Policy: default-src \'none\'');

        foreach ($headers as $key => $value) {
            header("{$key}: {$value}");
        }

        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send standard API success response
     */
    public static function success(string $message, array $data = [], int $statusCode = 200, array $headers = []): void
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if (!empty($data)) {
            $payload['data'] = $data;
        }

        self::json($payload, $statusCode, $headers);
    }

    /**
     * Send standard API error response
     */
    public static function error(string $message, string $errorCode, int $statusCode = 400, array $details = [], array $headers = []): void
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
        ];

        if (!empty($details)) {
            $payload['details'] = $details;
        }

        self::json($payload, $statusCode, $headers);
    }
}
