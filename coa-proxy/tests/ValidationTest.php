<?php

namespace CoaProxy\Tests;

use PHPUnit\Framework\TestCase;
use CoaProxy\Validator;

class ValidationTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $radiusConfig = [
            'default_nas' => '10.10.10.1',
            'allowed_nas' => ['10.10.10.1', '10.10.10.2'],
            'allowed_attributes' => [
                'User-Name',
                'Acct-Session-Id',
                'Mikrotik-Rate-Limit',
                'Mikrotik-Address-List',
                'Session-Timeout',
                'Idle-Timeout',
            ],
        ];
        $this->validator = new Validator($radiusConfig);
    }

    public function testValidUsername(): void
    {
        $this->assertEquals('user001', $this->validator->validateUsername('user001'));
        $this->assertEquals('user_test@isp.net', $this->validator->validateUsername('user_test@isp.net'));
    }

    public function testInvalidUsernameChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validateUsername('user; rm -rf /');
    }

    public function testValidNasIp(): void
    {
        $this->assertEquals('10.10.10.1', $this->validator->validateNasIp('10.10.10.1'));
    }

    public function testDisallowedNasIp(): void
    {
        $this->expectException(\DomainException::class);
        $this->validator->validateNasIp('192.168.100.99');
    }

    public function testValidRateLimit(): void
    {
        $this->assertEquals('20M/20M', $this->validator->validateRateLimit('20M/20M'));
        $this->assertEquals('10M/50M', $this->validator->validateRateLimit('10M/50M'));
    }

    public function testInvalidRateLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validateRateLimit('20M; DROP TABLE users;');
    }

    public function testValidAttributeWhitelist(): void
    {
        $input = [
            'Mikrotik-Rate-Limit' => '10M/10M',
            'Session-Timeout' => '3600'
        ];
        $validated = $this->validator->validateAttributes($input);
        $this->assertArrayHasKey('Mikrotik-Rate-Limit', $validated);
        $this->assertEquals('10M/10M', $validated['Mikrotik-Rate-Limit']);
    }

    public function testDisallowedAttribute(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $input = [
            'Unsafe-Attribute' => 'hack'
        ];
        $this->validator->validateAttributes($input);
    }
}
