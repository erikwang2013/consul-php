<?php

namespace Erikwang2013\Consul\Tests\Integration;

use Erikwang2013\Consul\Client\ConsulClient;
use Erikwang2013\Consul\Integration\ClientFactory;
use Erikwang2013\Consul\Tests\Support\ArrayCache;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use ReflectionProperty;

/**
 * 四个框架适配器都汇聚到 ClientFactory::create()，唯一逻辑是缓存开关：
 * 只有 config['cache']['enable'] 为真且真的传了 $cache 时才注入。
 */
class ClientFactoryTest extends TestCase
{
    /** @param array<string, mixed> $config */
    private function create(array $config, ?CacheInterface $cache): ConsulClient
    {
        return ClientFactory::create($config, null, null, null, null, $cache, null);
    }

    private function cacheOf(ConsulClient $client): ?CacheInterface
    {
        $property = new ReflectionProperty($client, 'cache');
        $property->setAccessible(true);

        return $property->getValue($client);
    }

    public function testCacheIsInjectedWhenEnabledAndProvided(): void
    {
        $cache = new ArrayCache();
        $client = $this->create(
            ['base_uri' => 'http://consul:8500', 'cache' => ['enable' => true]],
            $cache
        );

        $this->assertSame($cache, $this->cacheOf($client));
    }

    public function testCacheIsIgnoredWhenEnabledButNoneProvided(): void
    {
        $client = $this->create(
            ['base_uri' => 'http://consul:8500', 'cache' => ['enable' => true]],
            null
        );

        $this->assertNull($this->cacheOf($client));
    }

    public function testCacheIsIgnoredWhenEnableKeyIsMissing(): void
    {
        $client = $this->create(['base_uri' => 'http://consul:8500'], new ArrayCache());

        $this->assertNull($this->cacheOf($client));
    }

    public function testCacheIsIgnoredWhenExplicitlyDisabled(): void
    {
        $client = $this->create(
            ['base_uri' => 'http://consul:8500', 'cache' => ['enable' => false]],
            new ArrayCache()
        );

        $this->assertNull($this->cacheOf($client));
    }
}
