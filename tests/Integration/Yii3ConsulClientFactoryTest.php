<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Tests\Integration;

use Erikwang2013\Consul\Client\ConsulClient;
use Erikwang2013\Consul\Integration\Yii3\ConsulClientFactory;
use Erikwang2013\Consul\Tests\Support\ArrayCache;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use ReflectionProperty;
use RuntimeException;
use stdClass;

/**
 * Yii3 的装配逻辑：容器里绑定的 PSR 服务按类型注入，没绑的留空走库内默认。
 *
 * config/di.php 依赖 Yii3 本身（跑不起来），但它是配置；真正的逻辑在这个零框架依赖的
 * 工厂里，和 ClientFactory 一样照常测。
 */
class Yii3ConsulClientFactoryTest extends TestCase
{
    private const CONFIG = [
        'base_uri' => 'http://consul.test:8500',
        'token'    => 'acl-token',
        'cache'    => ['enable' => true, 'ttl' => 60],
    ];

    /** @param array<string, mixed> $services */
    private function build(array $services, ?array $config = null): ConsulClient
    {
        return ConsulClientFactory::create($config ?? self::CONFIG, new Yii3ContainerStub($services));
    }

    private function propertyOf(object $object, string $name): mixed
    {
        $property = new ReflectionProperty($object, $name);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    public function testMissingServicesFallBackToLibraryDefaults(): void
    {
        $client = $this->build([]);
        $transport = $this->propertyOf($client, 'transport');

        $this->assertSame('http://consul.test:8500', $this->propertyOf($transport, 'baseUri'));
        $this->assertSame('acl-token', $this->propertyOf($transport, 'token'));
        $this->assertInstanceOf(NullLogger::class, $this->propertyOf($transport, 'logger'));
        $this->assertNull($this->propertyOf($client, 'cache'));
        $this->assertNull($this->propertyOf($client, 'eventDispatcher'));
    }

    public function testServicesBoundInContainerAreInjected(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $cache = new ArrayCache();
        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }
        };

        $client = $this->build([
            ClientInterface::class => $httpClient,
            LoggerInterface::class => $logger,
            CacheInterface::class => $cache,
            EventDispatcherInterface::class => $dispatcher,
        ]);
        $transport = $this->propertyOf($client, 'transport');

        $this->assertSame($httpClient, $this->propertyOf($transport, 'httpClient'));
        $this->assertSame($logger, $this->propertyOf($transport, 'logger'));
        $this->assertSame($cache, $this->propertyOf($client, 'cache'));
        $this->assertSame($dispatcher, $this->propertyOf($client, 'eventDispatcher'));
    }

    public function testCacheIsIgnoredWhenDisabledInParams(): void
    {
        $client = $this->build(
            [CacheInterface::class => new ArrayCache()],
            ['base_uri' => 'http://consul:8500', 'cache' => ['enable' => false]]
        );

        $this->assertNull($this->propertyOf($client, 'cache'));
    }

    /** 绑定错了类型时要报错，别拖到传输层变成一句莫名的 TypeError */
    public function testWrongTypedBindingFailsLoudly(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(CacheInterface::class);

        $this->build([CacheInterface::class => new stdClass()]);
    }

    /** params.php 与 di.php 靠 params['erikwang2013/consul-php'] 对接，两边必须对得上 */
    public function testConfigFilesHandParametersToTheFactory(): void
    {
        $configDir = dirname(__DIR__, 2) . '/src/Integration/Yii3/config';

        putenv('CONSUL_BASE_URI=http://from-env:8500');
        $params = require $configDir . '/params.php';
        putenv('CONSUL_BASE_URI');

        $this->assertIsString($params['erikwang2013/consul-php']['consul']['base_uri'] ?? null);

        // yiisoft/config 是把 $params 变量塞进作用域后再 include 的，这里照做一遍
        $definitions = (static function () use ($params, $configDir): array {
            return require $configDir . '/di.php';
        })();

        $this->assertArrayHasKey(ConsulClient::class, $definitions);

        $client = $definitions[ConsulClient::class](new Yii3ContainerStub([]));

        $this->assertInstanceOf(ConsulClient::class, $client);
        $this->assertSame(
            'http://from-env:8500',
            $this->propertyOf($this->propertyOf($client, 'transport'), 'baseUri')
        );
    }
}

/** 只实现工厂用到的那部分 PSR-11 语义的测试替身 */
final class Yii3ContainerStub implements ContainerInterface
{
    /** @param array<string, mixed> $services */
    public function __construct(private array $services)
    {
    }

    public function get(string $id): mixed
    {
        if (!array_key_exists($id, $this->services)) {
            throw new Yii3NotFoundException("容器里没有 {$id}");
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}

final class Yii3NotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
