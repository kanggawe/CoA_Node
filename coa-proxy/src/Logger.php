<?php

namespace CoaProxy;

class Logger
{
    private string $logFile;
    private string $requestId;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
        $this->requestId = self::resolveRequestId();
        $this->ensureDirectoryExists();
    }

    /**
     * Get or generate X-Request-ID
     */
    public static function resolveRequestId(): string
    {
        if (isset($_SERVER['HTTP_X_REQUEST_ID']) && !empty($_SERVER['HTTP_X_REQUEST_ID'])) {
            // Sanitize incoming Request ID
            return preg_replace('/[^a-zA-Z0-9\-]/', '', $_SERVER['HTTP_X_REQUEST_ID']);
        }
        return self::generateUuid();
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * Log structured message
     */
    public function log(string $level, string $event, array $context = []): void
    {
        $timestamp = date('c');
        $sanitizedContext = $this->sanitizeContext($context);
        
        $kvPairs = [];
        foreach ($sanitizedContext as $k => $v) {
            $kvPairs[] = "{$k}=" . (is_scalar($v) ? $v : json_encode($v));
        }
        $kvString = implode(' ', $kvPairs);

        $line = sprintf(
            "%s %s %s req_id=%s %s\n",
            $timestamp,
            strtoupper($level),
            $event,
            $this->requestId,
            $kvString
        );

        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log structured audit record (JSON audit line)
     */
    public function audit(array $auditData): void
    {
        $sanitized = $this->sanitizeContext($auditData);
        $sanitized['request_id'] = $this->requestId;
        if (!isset($sanitized['timestamp'])) {
            $sanitized['timestamp'] = date('c');
        }

        $auditLogFile = dirname($this->logFile) . '/audit.log';
        $line = json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($auditLogFile, $line, FILE_APPEND | LOCK_EX);

        // Also output formatted standard log line
        $this->log('info', 'coa_audit', $sanitized);
    }

    public function info(string $event, array $context = []): void
    {
        $this->log('info', $event, $context);
    }

    public function error(string $event, array $context = []): void
    {
        $this->log('error', $event, $context);
    }

    public function warning(string $event, array $context = []): void
    {
        $this->log('warning', $event, $context);
    }

    /**
     * Never log sensitive parameters like secret keys or bearer tokens
     */
    private function sanitizeContext(array $context): array
    {
        $sensitiveKeys = [
            'api_token',
            'coa_api_token',
            'radius_secret',
            'secret',
            'authorization',
            'http_authorization',
            'bearer'
        ];

        foreach ($context as $key => $val) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveKeys, true) || str_contains($lowerKey, 'token') || str_contains($lowerKey, 'secret')) {
                $context[$key] = '***REDACTED***';
            }
        }

        return $context;
    }

    private function ensureDirectoryExists(): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
