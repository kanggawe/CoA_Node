<?php

namespace CoaProxy\Tests;

use PHPUnit\Framework\TestCase;
use CoaProxy\Auth;
use CoaProxy\Validator;
use CoaProxy\RadiusClient;
use CoaProxy\CoAService;
use CoaProxy\Logger;
use CoaProxy\Router;

class ApiTest extends TestCase
{
    public function testHealthEndpointReturnsJson(): void
    {
        $config = [
            'api_token' => 'token123',
            'allowed_ips' => ['127.0.0.1'],
            'rate_limit' => ['max_requests' => 60, 'decay_seconds' => 60],
            'log' => ['file' => sys_get_temp_dir() . '/test.log'],
            'max_request_body_size' => 65536,
            'debug' => true,
        ];
        $radiusConfig = [
            'default_nas' => '10.10.10.1',
            'allowed_nas' => ['10.10.10.1'],
            'radclient_path' => '/usr/bin/radclient',
            'allowed_attributes' => ['User-Name'],
            'secret' => 'secret123',
        ];

        $logger = $this->createMock(Logger::class);
        $auth = new Auth($config);
        $validator = new Validator($radiusConfig);
        $radiusClient = $this->createMock(RadiusClient::class);
        $coaService = new CoAService($validator, $radiusClient, $logger, $config);

        $router = new Router($auth, $coaService, $validator, $logger, $config, $radiusConfig);

        ob_start();
        try {
            $router->dispatch('GET', '/api/health');
        } catch (\Throwable $e) {
            // Expected exit from Response::json
        }
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        $decoded = json_decode($output, true);
        $this->assertTrue($decoded['success']);
        $this->assertEquals('FreeRADIUS CoA Proxy', $decoded['service']);
    }
}
