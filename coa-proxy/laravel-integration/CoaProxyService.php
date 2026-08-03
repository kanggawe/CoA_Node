<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * FreeRADIUS CoA Proxy API Integration Service for Laravel 13
 * 
 * Add to Laravel config/services.php:
 * 'coa_proxy' => [
 *     'url' => env('COA_PROXY_URL', 'https://coa.example.com'),
 *     'token' => env('COA_PROXY_TOKEN', ''),
 *     'timeout' => env('COA_PROXY_TIMEOUT', 10),
 * ],
 * 
 * Add to Laravel .env:
 * COA_PROXY_URL=https://coa.example.com
 * COA_PROXY_TOKEN=YOUR_SECURE_BEARER_TOKEN
 */
class CoaProxyService
{
    protected string $baseUrl;
    protected string $token;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.coa_proxy.url', ''), '/');
        $this->token = (string) config('services.coa_proxy.token', '');
        $this->timeout = (int) config('services.coa_proxy.timeout', 10);
    }

    /**
     * Send RADIUS Disconnect-Request to terminate active PPPoE session
     */
    public function disconnect(string $username, ?string $sessionId = null, ?string $nasIp = null, ?string $idempotencyKey = null): array
    {
        $payload = array_filter([
            'username' => $username,
            'acct_session_id' => $sessionId,
            'nas_ip' => $nasIp,
        ]);

        return $this->sendRequest('/api/coa/disconnect', $payload, $idempotencyKey);
    }

    /**
     * Send RADIUS CoA-Request to change dynamic bandwidth package rate limit
     */
    public function changeProfile(string $username, string $rateLimit, ?string $sessionId = null, ?string $nasIp = null, ?string $idempotencyKey = null): array
    {
        $payload = array_filter([
            'username' => $username,
            'rate_limit' => $rateLimit,
            'acct_session_id' => $sessionId,
            'nas_ip' => $nasIp,
        ]);

        return $this->sendRequest('/api/coa/change-profile', $payload, $idempotencyKey);
    }

    /**
     * Send Generic RADIUS CoA-Request with specified attributes
     */
    public function coa(string $username, array $attributes, ?string $sessionId = null, ?string $nasIp = null, ?string $idempotencyKey = null): array
    {
        $payload = array_filter([
            'username' => $username,
            'attributes' => $attributes,
            'acct_session_id' => $sessionId,
            'nas_ip' => $nasIp,
        ]);

        return $this->sendRequest('/api/coa/coa', $payload, $idempotencyKey);
    }

    /**
     * Execute HTTP Request using Laravel Http Client with Bearer token & Idempotency Key
     */
    protected function sendRequest(string $endpoint, array $payload, ?string $idempotencyKey = null): array
    {
        $url = $this->baseUrl . $endpoint;
        $key = $idempotencyKey ?? (string) Str::uuid();

        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                ->connectTimeout(5)
                ->withHeaders([
                    'Idempotency-Key' => $key,
                    'X-Request-ID' => (string) Str::uuid(),
                ])
                ->post($url, $payload);

            if ($response->successful()) {
                Log::info('CoaProxy call succeeded', [
                    'endpoint' => $endpoint,
                    'username' => $payload['username'] ?? null,
                    'response' => $response->json(),
                ]);
                return [
                    'success' => true,
                    'status' => $response->status(),
                    'data' => $response->json(),
                ];
            }

            Log::error('CoaProxy call failed with status error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'status' => $response->status(),
                'error' => $response->json('message') ?? 'CoA Proxy request failed',
                'error_code' => $response->json('error_code') ?? 'COA_PROXY_ERROR',
                'data' => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::error('CoaProxy HTTP connection exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 500,
                'error' => 'Failed to connect to CoA Proxy server: ' . $e->getMessage(),
                'error_code' => 'HTTP_CLIENT_EXCEPTION',
            ];
        }
    }
}
