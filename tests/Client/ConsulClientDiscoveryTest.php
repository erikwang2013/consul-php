<?php

namespace Erikwang2013\Consul\Tests\Client;

use Erikwang2013\Consul\Client\ConsulClient;
use Erikwang2013\Consul\Http\RequestFactory;
use Erikwang2013\Consul\Http\StreamFactory;
use Erikwang2013\Consul\Tests\Support\Subprocess;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * 「原生 PHP 零额外依赖」靠的就是 discovery 失败时回退到内置 PSR-17 实现。
 * 本仓库装了 Guzzle，只有子进程里冒充 discovery 类才能造出"发现失败"。
 */
class ConsulClientDiscoveryTest extends TestCase
{
    public function testFallsBackToBuiltInFactoriesWhenDiscoveryThrows(): void
    {
        $script = <<<'PHP'
            <?php
            // 冒充 discovery：类存在但一律抛错，模拟机器上没有任何 PSR-17 实现
            namespace Http\Discovery;

            class Psr18ClientDiscovery
            {
                public static function find(): object
                {
                    throw new \RuntimeException('stub: 没有 PSR-18 客户端');
                }
            }

            class Psr17FactoryDiscovery
            {
                public static function findRequestFactory(): object
                {
                    throw new \RuntimeException('stub: 没有 PSR-17 请求工厂');
                }

                public static function findStreamFactory(): object
                {
                    throw new \RuntimeException('stub: 没有 PSR-17 流工厂');
                }
            }

            namespace ConsulDiscoveryCheck;

            require $argv[1];

            use Erikwang2013\Consul\Client\ConsulClient;

            function read(object $object, string $property)
            {
                $reflection = new \ReflectionProperty($object, $property);
                $reflection->setAccessible(true);

                return $reflection->getValue($object);
            }

            $client = new ConsulClient(['base_uri' => 'consul.local:8500']);
            $transport = read($client, 'transport');

            echo get_class(read($transport, 'requestFactory')), "\n";
            echo get_class(read($transport, 'streamFactory')), "\n";
            echo read($transport, 'baseUri'), "\n";
            PHP;

        $result = Subprocess::run($script);

        $this->assertSame(0, $result['status'], '子进程失败：' . $result['output']);
        $lines = array_values(array_filter(explode("\n", trim($result['output']))));

        $this->assertSame(RequestFactory::class, $lines[0] ?? '');
        $this->assertSame(StreamFactory::class, $lines[1] ?? '');
        // base_uri 没写 scheme 时自动补 http://，否则拼出来的 URI 客户端解析不了
        $this->assertSame('http://consul.local:8500', $lines[2] ?? '');
    }

    public function testDiscoverThrowsWhenTheDiscoveryClassDoesNotExist(): void
    {
        $client = (new \ReflectionClass(ConsulClient::class))->newInstanceWithoutConstructor();

        $discover = new ReflectionMethod($client, 'discover');
        $discover->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No PSR-18 HTTP client found.');

        $discover->invoke($client, 'Http\Discovery\TotallyMissingDiscovery', 'find', 'No PSR-18 HTTP client found.');
    }
}
