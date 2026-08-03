<?php

namespace CoaProxy;

class CoAService
{
    private Validator $validator;
    private RadiusClient $radiusClient;
    private Logger $logger;
    private array $config;

    public function __construct(Validator $validator, RadiusClient $radiusClient, Logger $logger, array $config)
    {
        $this->validator = $validator;
        $this->radiusClient = $radiusClient;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Handle Disconnect API request
     */
    public function handleDisconnect(array $payload, ?string $idempotencyKey = null, ?string $clientIp = null): array
    {
        $username = $this->validator->validateUsername($payload['username'] ?? null);
        $sessionId = $this->validator->validateSessionId($payload['acct_session_id'] ?? null);
        $nasIp = $this->validator->validateNasIp($payload['nas_ip'] ?? null);

        return $this->executeWithIdempotency('disconnect', $idempotencyKey, function() use ($username, $sessionId, $nasIp, $clientIp) {
            $result = $this->radiusClient->disconnect($username, $sessionId, $nasIp);

            $auditData = [
                'action' => 'disconnect',
                'username' => $username,
                'session_id' => $sessionId,
                'nas_ip' => $nasIp,
                'client_ip' => $clientIp ?? Auth::getClientIp(),
                'status' => $result['success'] ? 'success' : 'failed',
                'duration_ms' => $result['duration_ms'],
                'exit_code' => $result['exit_code'],
            ];
            $this->logger->audit($auditData);

            if (!$result['success']) {
                return [
                    'http_status' => $result['timed_out'] ? 504 : 502,
                    'response' => [
                        'success' => false,
                        'message' => $result['message'],
                        'error_code' => $result['error_code'],
                        'details' => [
                            'username' => $username,
                            'nas_ip' => $nasIp,
                            'stderr' => $result['stderr'],
                        ]
                    ]
                ];
            }

            return [
                'http_status' => 200,
                'response' => [
                    'success' => true,
                    'message' => 'CoA Disconnect-Request sent successfully',
                    'data' => [
                        'username' => $username,
                        'acct_session_id' => $sessionId,
                        'nas_ip' => $nasIp,
                        'action' => 'disconnect',
                        'duration_ms' => $result['duration_ms'],
                    ]
                ]
            ];
        });
    }

    /**
     * Handle Change Profile / Bandwidth Rate Limit API request
     */
    public function handleChangeProfile(array $payload, ?string $idempotencyKey = null, ?string $clientIp = null): array
    {
        $username = $this->validator->validateUsername($payload['username'] ?? null);
        $sessionId = $this->validator->validateSessionId($payload['acct_session_id'] ?? null);
        $nasIp = $this->validator->validateNasIp($payload['nas_ip'] ?? null);
        $rateLimit = $this->validator->validateRateLimit($payload['rate_limit'] ?? null);

        $attributes = [
            'Mikrotik-Rate-Limit' => $rateLimit,
        ];

        return $this->executeWithIdempotency('change-profile', $idempotencyKey, function() use ($username, $sessionId, $nasIp, $rateLimit, $attributes, $clientIp) {
            $result = $this->radiusClient->coa($username, $sessionId, $nasIp, $attributes);

            $auditData = [
                'action' => 'change-profile',
                'username' => $username,
                'session_id' => $sessionId,
                'nas_ip' => $nasIp,
                'rate_limit' => $rateLimit,
                'client_ip' => $clientIp ?? Auth::getClientIp(),
                'status' => $result['success'] ? 'success' : 'failed',
                'duration_ms' => $result['duration_ms'],
                'exit_code' => $result['exit_code'],
            ];
            $this->logger->audit($auditData);

            if (!$result['success']) {
                return [
                    'http_status' => $result['timed_out'] ? 504 : 502,
                    'response' => [
                        'success' => false,
                        'message' => $result['message'],
                        'error_code' => $result['error_code'],
                        'details' => [
                            'username' => $username,
                            'nas_ip' => $nasIp,
                            'stderr' => $result['stderr'],
                        ]
                    ]
                ];
            }

            return [
                'http_status' => 200,
                'response' => [
                    'success' => true,
                    'message' => 'CoA Change-Profile sent successfully',
                    'data' => [
                        'username' => $username,
                        'acct_session_id' => $sessionId,
                        'nas_ip' => $nasIp,
                        'rate_limit' => $rateLimit,
                        'action' => 'change-profile',
                        'duration_ms' => $result['duration_ms'],
                    ]
                ]
            ];
        });
    }

    /**
     * Handle Generic CoA API request
     */
    public function handleCoa(array $payload, ?string $idempotencyKey = null, ?string $clientIp = null): array
    {
        $username = $this->validator->validateUsername($payload['username'] ?? null);
        $sessionId = $this->validator->validateSessionId($payload['acct_session_id'] ?? null);
        $nasIp = $this->validator->validateNasIp($payload['nas_ip'] ?? null);
        $attributes = $this->validator->validateAttributes($payload['attributes'] ?? null);

        return $this->executeWithIdempotency('coa', $idempotencyKey, function() use ($username, $sessionId, $nasIp, $attributes, $clientIp) {
            $result = $this->radiusClient->coa($username, $sessionId, $nasIp, $attributes);

            $auditData = [
                'action' => 'coa',
                'username' => $username,
                'session_id' => $sessionId,
                'nas_ip' => $nasIp,
                'attributes' => array_keys($attributes),
                'client_ip' => $clientIp ?? Auth::getClientIp(),
                'status' => $result['success'] ? 'success' : 'failed',
                'duration_ms' => $result['duration_ms'],
                'exit_code' => $result['exit_code'],
            ];
            $this->logger->audit($auditData);

            if (!$result['success']) {
                return [
                    'http_status' => $result['timed_out'] ? 504 : 502,
                    'response' => [
                        'success' => false,
                        'message' => $result['message'],
                        'error_code' => $result['error_code'],
                        'details' => [
                            'username' => $username,
                            'nas_ip' => $nasIp,
                            'stderr' => $result['stderr'],
                        ]
                    ]
                ];
            }

            return [
                'http_status' => 200,
                'response' => [
                    'success' => true,
                    'message' => 'CoA Request sent successfully',
                    'data' => [
                        'username' => $username,
                        'acct_session_id' => $sessionId,
                        'nas_ip' => $nasIp,
                        'attributes' => $attributes,
                        'action' => 'coa',
                        'duration_ms' => $result['duration_ms'],
                    ]
                ]
            ];
        });
    }

    /**
     * Idempotency wrapper using file storage
     */
    private function executeWithIdempotency(string $action, ?string $idempotencyKey, callable $callback): array
    {
        if ($idempotencyKey === null || trim($idempotencyKey) === '') {
            return $callback();
        }

        $idempotencyKey = preg_replace('/[^a-zA-Z0-9\-]/', '', $idempotencyKey);
        $cacheDir = dirname(__DIR__) . '/storage/idempotency';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $file = $cacheDir . '/' . md5($action . '_' . $idempotencyKey) . '.json';
        $ttl = 60; // 60 seconds idempotency TTL

        if (file_exists($file)) {
            $mtime = filemtime($file);
            if (time() - $mtime < $ttl) {
                $cached = @file_get_contents($file);
                if ($cached) {
                    $decoded = json_decode($cached, true);
                    if (is_array($decoded) && isset($decoded['http_status'], $decoded['response'])) {
                        $decoded['response']['idempotent_replay'] = true;
                        return $decoded;
                    }
                }
            }
        }

        $result = $callback();

        // Cache response for 60 seconds
        @file_put_contents($file, json_encode($result), LOCK_EX);

        return $result;
    }
}
