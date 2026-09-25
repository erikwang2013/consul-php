<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Tests\Support;

use Erikwang2013\Consul\Service\Registry;

/**
 * 记录调用的 Registry 桩。用在子进程脚本里——那边没有 PHPUnit 的 mock，
 * 而且 NativeService 的注销结果要靠子进程的输出来看。
 */
class RegistryStub extends Registry
{
    /** 不走父类构造：桩不碰真实的 Consul Agent。 */
    public function __construct()
    {
    }

    public function register(string $name, string $address, int $port, array $options = []): void
    {
        self::log('register:' . (string) ($options['id'] ?? $name));
    }

    public function heartbeat(string $serviceId, string $note = ''): void
    {
        self::log('heartbeat:' . $serviceId);
    }

    public function deregister(string $serviceId): void
    {
        self::log('deregister:' . $serviceId);
    }

    private static function log(string $line): void
    {
        echo $line, "\n";
        flush();
    }
}
