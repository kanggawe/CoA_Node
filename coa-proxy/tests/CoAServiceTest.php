<?php

namespace CoaProxy\Tests;

use PHPUnit\Framework\TestCase;
use CoaProxy\CoAService;
use CoaProxy\Validator;
use CoaProxy\RadiusClient;
use CoaProxy\Logger;

class CoAServiceTest extends TestCase
{
    public function testHandleDisconnectSuccess(): void
    {
        $radiusConfig = [
            'default_nas' => '10.10.10.1',
            'allowed_nas' => ['10.10.10.1'],
            'allowed_attributes' => ['User-Name', 'Acct-Session-Id'],
        ];

        $validator = new Validator($radiusConfig);
        $logger = $this->createMock(Logger::class);
        $radiusClient = $this->createMock(RadiusClient::class);

        $radiusClient->expects($this->once())
            ->method('disconnect')
            ->with('user001', '17654321', '10.10.10.1')
            ->willReturn([
                'success' => true,
                'exit_code' => 0,
                'stdout' => 'Received response code 2',
                'stderr' => '',
                'duration_ms' => 15,
                'timed_out' => false,
            ]);

        $config = ['log' => ['file' => sys_get_temp_dir() . '/coa_test.log']];

        $service = new CoAService($validator, $radiusClient, $logger, $config);

        $payload = [
            'username' => 'user001',
            'acct_session_id' => '17654321',
            'nas_ip' => '10.10.10.1'
        ];

        $res = $service->handleDisconnect($payload, null, '127.0.0.1');

        $this->assertEquals(200, $res['http_status']);
        $this->assertTrue($res['response']['success']);
        $this->assertEquals('user001', $res['response']['data']['username']);
    }
}
