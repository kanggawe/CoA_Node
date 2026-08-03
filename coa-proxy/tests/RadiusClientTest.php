<?php

namespace CoaProxy\Tests;

use PHPUnit\Framework\TestCase;
use CoaProxy\RadiusClient;
use CoaProxy\Logger;

class RadiusClientTest extends TestCase
{
    public function testRadclientMissing(): void
    {
        $config = [
            'radclient_path' => '/nonexistent/path/to/radclient',
            'coa_port' => 3799,
            'secret' => 'testingsecret',
            'timeout' => 2,
        ];

        $logger = $this->createMock(Logger::class);
        $client = new RadiusClient($config, $logger);

        $res = $client->disconnect('user001', '123456', '10.10.10.1');
        
        $this->assertFalse($res['success']);
        $this->assertEquals('RADCLIENT_NOT_FOUND', $res['error_code']);
    }
}
