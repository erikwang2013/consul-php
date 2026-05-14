<?php

namespace Erikwang\Consul\Tests\Config;

use Erikwang\Consul\Api\Kv;
use Erikwang\Consul\Config\ConfigCenter;
use Erikwang\Consul\Config\Watcher;
use PHPUnit\Framework\TestCase;

class ConfigCenterTest extends TestCase
{
    private $kv;
    private ConfigCenter $config;

    protected function setUp(): void
    {
        $this->kv = $this->createMock(Kv::class);
        $this->config = new ConfigCenter($this->kv);
    }

    public function testGetReturnsDecodedValue(): void
    {
        $this->kv->method('get')
            ->with('app/db_host')
            ->willReturn(['Key' => 'app/db_host', 'Value' => base64_encode('mysql.local')]);

        $result = $this->config->get('app/db_host');

        $this->assertSame('mysql.local', $result);
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $this->kv->method('get')
            ->with('missing/key')
            ->willReturn(null);

        $result = $this->config->get('missing/key', 'fallback');

        $this->assertSame('fallback', $result);
    }

    public function testNamespaceReturnsKeyValueMap(): void
    {
        $this->kv->method('all')
            ->with('app/')
            ->willReturn([
                ['Key' => 'app/host', 'Value' => base64_encode('localhost')],
                ['Key' => 'app/port', 'Value' => base64_encode('3306')],
            ]);

        $result = $this->config->namespace('app/');

        $this->assertSame('localhost', $result['app/host']);
        $this->assertSame('3306', $result['app/port']);
    }

    public function testSet(): void
    {
        $this->kv->method('put')
            ->with('app/key', 'value')
            ->willReturn(true);

        $result = $this->config->set('app/key', 'value');

        $this->assertTrue($result);
    }

    public function testWatchReturnsWatcher(): void
    {
        $watcher = $this->config->watch('app/');

        $this->assertInstanceOf(Watcher::class, $watcher);
    }
}
