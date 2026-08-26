<?php

namespace Erikwang2013\Consul\Tests\Config;

use Erikwang2013\Consul\Api\Kv;
use Erikwang2013\Consul\Config\ConfigCenter;
use Erikwang2013\Consul\Config\Watcher;
use Erikwang2013\Consul\Tests\Support\ArrayCache;
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

    public function testSetInvalidatesCachedValue(): void
    {
        $cache = new ArrayCache();
        $config = new ConfigCenter($this->kv, $cache, 300);

        $this->kv->method('get')->willReturnOnConsecutiveCalls(
            ['Key' => 'app/key', 'Value' => base64_encode('old')],
            ['Key' => 'app/key', 'Value' => base64_encode('new')]
        );
        $this->kv->method('put')->with('app/key', 'new')->willReturn(true);

        $this->assertSame('old', $config->get('app/key')); // primed from kv, cached
        $this->assertTrue($config->set('app/key', 'new'));
        $this->assertNull($cache->get('consul:config:app/key')); // invalidated
        $this->assertSame('new', $config->get('app/key')); // refetched from kv, not stale cache
    }

    public function testDeleteInvalidatesCachedValue(): void
    {
        $cache = new ArrayCache();
        $config = new ConfigCenter($this->kv, $cache, 300);

        $this->kv->method('get')->willReturnOnConsecutiveCalls(
            ['Key' => 'app/key', 'Value' => base64_encode('old')],
            null
        );
        $this->kv->method('delete')->with('app/key')->willReturn(true);

        $this->assertSame('old', $config->get('app/key')); // primed from kv, cached
        $this->assertTrue($config->delete('app/key'));
        $this->assertNull($cache->get('consul:config:app/key')); // invalidated
        $this->assertSame('fallback', $config->get('app/key', 'fallback')); // cache miss -> kv -> default
    }

    public function testGetHitsCacheWithoutCallingKv(): void
    {
        $cache = new ArrayCache();
        $config = new ConfigCenter($this->kv, $cache, 300);

        $this->kv->expects($this->once())
            ->method('get')
            ->with('app/key')
            ->willReturn(['Key' => 'app/key', 'Value' => base64_encode('v1')]);

        $this->assertSame('v1', $config->get('app/key'));
        $this->assertSame('v1', $config->get('app/key')); // served from cache
    }

    public function testGetReturnsDefaultWhenValueIsNotBase64(): void
    {
        $this->kv->method('get')
            ->with('app/broken')
            ->willReturn(['Key' => 'app/broken', 'Value' => 'not-base64!!!']);

        $this->assertSame('fallback', $this->config->get('app/broken', 'fallback'));
    }

    public function testGetReturnsEmptyStringWhenKvEntryHasNoValue(): void
    {
        // An existing key without a Value decodes to '' (base64 of empty),
        // distinct from a missing key (-> default).
        $this->kv->method('get')
            ->with('app/empty')
            ->willReturn(['Key' => 'app/empty']);

        $this->assertSame('', $this->config->get('app/empty', 'fallback'));
    }

    public function testNamespaceUsesCache(): void
    {
        $cache = new ArrayCache();
        $config = new ConfigCenter($this->kv, $cache, 300);

        $this->kv->expects($this->once())
            ->method('all')
            ->with('app/')
            ->willReturn([['Key' => 'app/x', 'Value' => base64_encode('1')]]);

        $this->assertSame('1', $config->namespace('app/')['app/x']);
        $this->assertSame('1', $config->namespace('app/')['app/x']); // cached
    }

    public function testNamespaceKeepsRawValueWhenNotBase64(): void
    {
        $this->kv->method('all')
            ->with('app/')
            ->willReturn([['Key' => 'app/raw', 'Value' => 'plain-text']]);

        $result = $this->config->namespace('app/');

        $this->assertSame('plain-text', $result['app/raw']);
    }

    public function testSetFailureDoesNotInvalidateCache(): void
    {
        $cache = new ArrayCache();
        $config = new ConfigCenter($this->kv, $cache, 300);

        $this->kv->method('get')->willReturn(['Key' => 'app/key', 'Value' => base64_encode('old')]);
        $this->kv->method('put')->with('app/key', 'new')->willReturn(false);

        $this->assertSame('old', $config->get('app/key')); // cached
        $this->assertFalse($config->set('app/key', 'new'));
        $this->assertSame('old', $cache->get('consul:config:app/key')); // still cached
    }

    public function testDeleteFailureDoesNotInvalidateCache(): void
    {
        $cache = new ArrayCache();
        $config = new ConfigCenter($this->kv, $cache, 300);

        $this->kv->method('get')->willReturn(['Key' => 'app/key', 'Value' => base64_encode('old')]);
        $this->kv->method('delete')->with('app/key')->willReturn(false);

        $this->assertSame('old', $config->get('app/key')); // cached
        $this->assertFalse($config->delete('app/key'));
        $this->assertSame('old', $cache->get('consul:config:app/key')); // still cached
    }

    public function testDeleteInvalidatesAllNamespacePrefixLevels(): void
    {
        $cache = new ArrayCache();
        $config = new ConfigCenter($this->kv, $cache, 300);

        $this->kv->method('get')->willReturn(['Key' => 'app/foo/bar', 'Value' => base64_encode('v')]);
        $this->kv->method('delete')->with('app/foo/bar')->willReturn(true);
        $this->kv->method('all')->willReturn([]);

        $config->get('app/foo/bar');               // caches consul:config:app/foo/bar
        $config->namespace('app/');                // caches consul:config:ns:app
        $config->namespace('app/foo');             // caches consul:config:ns:app/foo
        $config->delete('app/foo/bar');

        $this->assertNull($cache->get('consul:config:app/foo/bar'));
        $this->assertNull($cache->get('consul:config:ns:app'));
        $this->assertNull($cache->get('consul:config:ns:app/foo'));
    }

    public function testWatchPassesDispatcherToWatcher(): void
    {
        $dispatcher = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);
        $config = new ConfigCenter($this->kv, null, null, $dispatcher);

        $this->assertInstanceOf(Watcher::class, $config->watch('app/'));
    }
}
