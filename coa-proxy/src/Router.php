<?php

namespace CoaProxy;

class Router
{
    private Auth $auth;
    private CoAService $coaService;
    private Validator $validator;
    private Logger $logger;
    private array $config;
    private array $radiusConfig;

    public function __construct(
        Auth $auth,
        CoAService $coaService,
        Validator $validator,
        Logger $logger,
        array $config,
        array $radiusConfig
    ) {
        $this->auth = $auth;
        $this->coaService = $coaService;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->config = $config;
        $this->radiusConfig = $radiusConfig;
    }

    /**
     * Dispatch incoming HTTP request to handler
     */
    public function dispatch(string $method, string $uri): void
    {
        // Strip query string and normalize URI path
        $path = parse_url($uri, PHP_URL_PATH);
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        // IP Allowlist Check
        if (!$this->auth->checkIpAllowlist()) {
            $this->logger->warning('ip_forbidden', [
                'client_ip' => Auth::getClientIp(),
                'uri' => $path
            ]);
            Response::error('IP address not allowed', 'IP_NOT_ALLOWED', 403);
        }

        // Rate Limit Check
        if (!$this->auth->checkRateLimit()) {
            $this->logger->warning('rate_limit_exceeded', [
                'client_ip' => Auth::getClientIp(),
                'uri' => $path
            ]);
            Response::error('Too many requests. Rate limit exceeded.', 'RATE_LIMIT_EXCEEDED', 429);
        }

        // Route Matching
        try {
            if ($path === '/api/health') {
                $this->handleHealth($method);
            } elseif ($path === '/api/version') {
                $this->handleVersion($method);
            } elseif (str_starts_with($path, '/api/coa/')) {
                // All CoA endpoints require Bearer Token authentication
                if (!$this->auth->authenticate()) {
                    $this->logger->warning('auth_failed', [
                        'client_ip' => Auth::getClientIp(),
                        'uri' => $path
                    ]);
                    Response::error('Authentication required or token invalid', 'AUTH_INVALID', 401);
                }

                if ($method !== 'POST') {
                    Response::error('HTTP method not allowed. POST required.', 'METHOD_NOT_ALLOWED', 405);
                }

                $rawInput = file_get_contents('php://input');
                $payload = $this->validator->validateJsonBody($rawInput, $this->config['max_request_body_size']);
                $idempotencyKey = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null;
                $clientIp = Auth::getClientIp();

                if ($path === '/api/coa/disconnect') {
                    $res = $this->coaService->handleDisconnect($payload, $idempotencyKey, $clientIp);
                    Response::json($res['response'], $res['http_status']);
                } elseif ($path === '/api/coa/change-profile') {
                    $res = $this->coaService->handleChangeProfile($payload, $idempotencyKey, $clientIp);
                    Response::json($res['response'], $res['http_status']);
                } elseif ($path === '/api/coa/coa') {
                    $res = $this->coaService->handleCoa($payload, $idempotencyKey, $clientIp);
                    Response::json($res['response'], $res['http_status']);
                } else {
                    Response::error('API endpoint not found', 'ENDPOINT_NOT_FOUND', 404);
                }
            } else {
                Response::error('API endpoint not found', 'ENDPOINT_NOT_FOUND', 404);
            }
        } catch (\InvalidArgumentException $e) {
            $code = $e->getCode();
            $httpStatus = ($code === 413) ? 413 : 400;
            $errorCode = ($code === 413) ? 'REQUEST_TOO_LARGE' : 'VALIDATION_ERROR';
            Response::error($e->getMessage(), $errorCode, $httpStatus);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 'NAS_NOT_ALLOWED', 403);
        } catch (\Throwable $e) {
            $this->logger->error('unhandled_exception', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            $msg = $this->config['debug'] ? $e->getMessage() : 'An internal server error occurred';
            Response::error($msg, 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * Health check endpoint handler
     */
    private function handleHealth(string $method): void
    {
        if ($method !== 'GET') {
            Response::error('HTTP method not allowed. GET required.', 'METHOD_NOT_ALLOWED', 405);
        }

        $radclientPath = $this->radiusConfig['radclient_path'];
        $radclientAvailable = file_exists($radclientPath) && is_executable($radclientPath);
        if (!$radclientAvailable) {
            $which = @shell_exec('which radclient 2>/dev/null');
            if ($which && trim($which) !== '') {
                $radclientAvailable = true;
            }
        }

        $logFile = $this->config['log']['file'];
        $logDir = dirname($logFile);
        $logWritable = is_writable($logFile) || (!file_exists($logFile) && is_writable($logDir));

        $configValid = !empty($this->config['api_token']) && !empty($this->radiusConfig['secret']);

        $allOk = $radclientAvailable && $logWritable && $configValid;

        Response::json([
            'success' => true,
            'service' => 'FreeRADIUS CoA Proxy',
            'status' => $allOk ? 'online' : 'degraded',
            'version' => '1.0.0',
            'checks' => [
                'radclient' => $radclientAvailable,
                'configuration' => $configValid,
                'log' => $logWritable,
            ]
        ], $allOk ? 200 : 503);
    }

    /**
     * Version endpoint handler
     */
    private function handleVersion(string $method): void
    {
        if ($method !== 'GET') {
            Response::error('HTTP method not allowed. GET required.', 'METHOD_NOT_ALLOWED', 405);
        }

        Response::json([
            'success' => true,
            'service' => 'FreeRADIUS CoA Proxy',
            'version' => '1.0.0',
            'php_version' => PHP_VERSION,
        ]);
    }
}
