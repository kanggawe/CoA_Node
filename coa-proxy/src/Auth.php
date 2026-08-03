<?php

namespace CoaProxy;

class Auth
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Check if client IP address is allowed
     */
    public function checkIpAllowlist(?string $clientIp = null): bool
    {
        $ip = $clientIp ?? self::getClientIp();
        $allowedIps = $this->config['allowed_ips'];

        if (empty($allowedIps)) {
            return false;
        }

        foreach ($allowedIps as $allowed) {
            if ($allowed === '*' || $allowed === $ip) {
                return true;
            }

            // Support CIDR matching e.g., 10.10.10.0/24
            if (str_contains($allowed, '/') && self::ipInCidr($ip, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Authenticate request using Bearer Token with hash_equals()
     */
    public function authenticate(): bool
    {
        $authHeader = self::getAuthorizationHeader();
        if ($authHeader === null || !str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }

        $token = trim(substr($authHeader, 7));
        $expectedToken = (string) $this->config['api_token'];

        if ($expectedToken === '' || $token === '') {
            return false;
        }

        return hash_equals($expectedToken, $token);
    }

    /**
     * Rate Limiter: 60 req/min/IP
     */
    public function checkRateLimit(?string $clientIp = null): bool
    {
        $ip = $clientIp ?? self::getClientIp();
        $maxRequests = $this->config['rate_limit']['max_requests'] ?? 60;
        $decaySeconds = $this->config['rate_limit']['decay_seconds'] ?? 60;

        // Try APCu first if available
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $key = "coa_rate_limit_" . md5($ip);
            $current = apcu_fetch($key);
            if ($current === false) {
                apcu_store($key, 1, $decaySeconds);
                return true;
            }
            if ($current >= $maxRequests) {
                return false;
            }
            apcu_inc($key);
            return true;
        }

        // File cache fallback
        $cacheDir = dirname(__DIR__) . '/storage/cache/rate_limit';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $file = $cacheDir . '/' . md5($ip) . '.json';
        $now = time();

        if (file_exists($file)) {
            $content = @file_get_contents($file);
            $data = $content ? json_decode($content, true) : null;
            if (is_array($data) && isset($data['reset_at'], $data['count'])) {
                if ($now < $data['reset_at']) {
                    if ($data['count'] >= $maxRequests) {
                        return false;
                    }
                    $data['count']++;
                    @file_put_contents($file, json_encode($data), LOCK_EX);
                    return true;
                }
            }
        }

        // Reset rate limit window
        $newData = [
            'reset_at' => $now + $decaySeconds,
            'count' => 1
        ];
        @file_put_contents($file, json_encode($newData), LOCK_EX);
        return true;
    }

    /**
     * Helper to retrieve client IP address
     */
    public static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return trim($_SERVER['HTTP_CLIENT_IP']);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Helper to retrieve Authorization header
     */
    private static function getAuthorizationHeader(): ?string
    {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['HTTP_AUTHORIZATION']);
        }
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $header => $value) {
                if (strtolower($header) === 'authorization') {
                    return trim($value);
                }
            }
        }
        return null;
    }

    /**
     * CIDR Range checker
     */
    private static function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskBin = -1 << (32 - (int)$mask);
            return ($ipLong & $maskBin) === ($subnetLong & $maskBin);
        }
        return false;
    }
}
