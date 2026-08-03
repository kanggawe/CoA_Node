<?php

namespace CoaProxy\Tests;

use PHPUnit\Framework\TestCase;
use CoaProxy\Auth;

class AuthTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'api_token' => 'secret_bearer_token_12345',
            'allowed_ips' => ['127.0.0.1', '10.10.10.20', '192.168.1.0/24'],
            'rate_limit' => [
                'max_requests' => 5,
                'decay_seconds' => 60,
            ],
        ];
    }

    public function testIpAllowlistValidIp(): void
    {
        $auth = new Auth($this->config);
        $this->assertTrue($auth->checkIpAllowlist('127.0.0.1'));
        $this->assertTrue($auth->checkIpAllowlist('10.10.10.20'));
        $this->assertTrue($auth->checkIpAllowlist('192.168.1.50')); // CIDR range
    }

    public function testIpAllowlistForbiddenIp(): void
    {
        $auth = new Auth($this->config);
        $this->assertFalse($auth->checkIpAllowlist('8.8.8.8'));
        $this->assertFalse($auth->checkIpAllowlist('10.10.10.99'));
    }

    public function testAuthenticateValidToken(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret_bearer_token_12345';
        $auth = new Auth($this->config);
        $this->assertTrue($auth->authenticate());
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testAuthenticateInvalidToken(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer wrong_token_value';
        $auth = new Auth($this->config);
        $this->assertFalse($auth->authenticate());
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testAuthenticateMissingHeader(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $auth = new Auth($this->config);
        $this->assertFalse($auth->authenticate());
    }
}
