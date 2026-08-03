<?php

namespace CoaProxy;

class Validator
{
    private array $radiusConfig;

    public function __construct(array $radiusConfig)
    {
        $this->radiusConfig = $radiusConfig;
    }

    /**
     * Validate raw JSON body string length and syntax
     */
    public function validateJsonBody(?string $rawInput, int $maxSizeBytes = 65536): array
    {
        if ($rawInput === null || trim($rawInput) === '') {
            throw new \InvalidArgumentException('Request body is empty or null', 400);
        }

        if (strlen($rawInput) > $maxSizeBytes) {
            throw new \InvalidArgumentException('Request payload size exceeds 64 KB limit', 413);
        }

        $decoded = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON payload: ' . json_last_error_msg(), 400);
        }

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('JSON payload must be an object', 400);
        }

        return $decoded;
    }

    /**
     * Validate username parameter (1-128 chars, alphanumeric + _ - . @)
     */
    public function validateUsername(mixed $username): string
    {
        if (!is_string($username) || trim($username) === '') {
            throw new \InvalidArgumentException('Parameter "username" is required and must be a string');
        }

        $username = trim($username);
        if (strlen($username) > 128) {
            throw new \InvalidArgumentException('Parameter "username" must not exceed 128 characters');
        }

        if (!preg_match('/^[a-zA-Z0-9_\-\.@]+$/', $username)) {
            throw new \InvalidArgumentException('Parameter "username" contains invalid characters');
        }

        return $username;
    }

    /**
     * Validate optional session ID parameter (1-128 chars)
     */
    public function validateSessionId(mixed $sessionId): ?string
    {
        if ($sessionId === null || $sessionId === '') {
            return null;
        }

        if (!is_string($sessionId) && !is_numeric($sessionId)) {
            throw new \InvalidArgumentException('Parameter "acct_session_id" must be a string or integer');
        }

        $sessionId = (string) $sessionId;
        if (strlen($sessionId) > 128) {
            throw new \InvalidArgumentException('Parameter "acct_session_id" must not exceed 128 characters');
        }

        if (!preg_match('/^[a-zA-Z0-9_\-\.:@]+$/', $sessionId)) {
            throw new \InvalidArgumentException('Parameter "acct_session_id" contains invalid characters');
        }

        return $sessionId;
    }

    /**
     * Validate NAS IP address and verify against allowed NAS whitelist
     */
    public function validateNasIp(mixed $nasIp): string
    {
        if ($nasIp === null || trim((string)$nasIp) === '') {
            $nasIp = $this->radiusConfig['default_nas'];
        }

        $nasIp = trim((string)$nasIp);
        if (filter_var($nasIp, FILTER_VALIDATE_IP) === false) {
            throw new \InvalidArgumentException('Parameter "nas_ip" is not a valid IP address');
        }

        $allowedNas = $this->radiusConfig['allowed_nas'];
        if (!in_array($nasIp, $allowedNas, true)) {
            throw new \DomainException("NAS IP [{$nasIp}] is not authorized in RADIUS_ALLOWED_NAS allowlist");
        }

        return $nasIp;
    }

    /**
     * Validate Mikrotik Rate-Limit string (e.g. 10M/10M, 20M/20M)
     */
    public function validateRateLimit(mixed $rateLimit): string
    {
        if (!is_string($rateLimit) || trim($rateLimit) === '') {
            throw new \InvalidArgumentException('Parameter "rate_limit" is required');
        }

        $rateLimit = trim($rateLimit);
        if (strlen($rateLimit) > 128) {
            throw new \InvalidArgumentException('Parameter "rate_limit" is too long');
        }

        // Pattern accepts format: 10M/10M or 512k/1024k or multi-segment Mikrotik format
        $pattern = '/^[0-9]+[kKmMgG]?(\/[0-9]+[kKmMgG]?)?(\s+[0-9]+[kKmMgG]?(\/[0-9]+[kKmMgG]?)?)*$/';
        if (!preg_match($pattern, $rateLimit)) {
            throw new \InvalidArgumentException('Parameter "rate_limit" format is invalid. Example valid format: "20M/20M"');
        }

        return $rateLimit;
    }

    /**
     * Validate generic RADIUS attributes object against allowed whitelist
     */
    public function validateAttributes(mixed $attributes): array
    {
        if (!is_array($attributes) || empty($attributes)) {
            throw new \InvalidArgumentException('Parameter "attributes" must be a non-empty key-value object');
        }

        $allowedKeys = $this->radiusConfig['allowed_attributes'];
        $validated = [];

        foreach ($attributes as $key => $value) {
            if (!in_array($key, $allowedKeys, true)) {
                throw new \InvalidArgumentException("RADIUS attribute [{$key}] is not permitted in whitelist");
            }

            if (!is_string($value) && !is_numeric($value)) {
                throw new \InvalidArgumentException("RADIUS attribute [{$key}] value must be a string or number");
            }

            $valStr = trim((string)$value);
            if (strlen($valStr) > 256 || preg_match('/[\r\n]/', $valStr)) {
                throw new \InvalidArgumentException("RADIUS attribute [{$key}] value contains invalid characters or exceeds max length");
            }

            $validated[$key] = $valStr;
        }

        return $validated;
    }
}
