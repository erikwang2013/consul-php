<?php

namespace Erikwang2013\Consul\Tests\Http;

use PHPUnit\Framework\TestCase;

/**
 * 原生 PHP 场景：没有任何 PSR-18 实现时，ConsulClient 必须回退到内置 cURL 客户端。
 *
 * 用子进程跑——本仓库装了 Guzzle，只有隔离进程里才能让自动发现"找不到"。
 */
class NativeFallbackTest extends TestCase
{
    public function testClientFallsBackToBuiltInCurlClient(): void
    {
        if (!\extension_loaded('curl')) {
            $this->markTestSkipped('需要 curl 扩展');
        }

        $script = <<<'PHP'
            <?php
            // 冒充 discovery 的客户端发现类：存在但抛错，模拟"机器上没有任何 PSR-18 实现"
            namespace Http\Discovery;
            class Psr18ClientDiscovery
            {
                public static function find(): object
                {
                    throw new \RuntimeException('stub: 没有可用的 PSR-18 客户端');
                }
            }

            namespace ConsulNativeCheck;

            require $argv[1];

            use Erikwang2013\Consul\Client\ConsulClient;

            $client = new ConsulClient(['base_uri' => 'http://127.0.0.1:8500']);

            $property = new \ReflectionProperty($client, 'transport');
            $property->setAccessible(true);
            $transport = $property->getValue($client);

            $transportProperty = new \ReflectionProperty($transport, 'httpClient');
            $transportProperty->setAccessible(true);
            $httpClient = $transportProperty->getValue($transport);

            echo get_class($httpClient), "\n";
            PHP;

        $file = tempnam(sys_get_temp_dir(), 'consul-native') . '.php';
        file_put_contents($file, $script);

        try {
            exec(sprintf('%s %s %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($file), escapeshellarg(dirname(__DIR__, 2) . '/vendor/autoload.php')), $output, $status);
        } finally {
            unlink($file);
        }

        $this->assertSame(0, $status, '子进程失败：' . implode("\n", $output));
        $this->assertSame('Erikwang2013\Consul\Http\CurlClient', trim(end($output) ?: ''));
    }
}
