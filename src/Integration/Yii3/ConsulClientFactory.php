<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Integration\Yii3;

use Erikwang2013\Consul\Client\ConsulClient;
use Erikwang2013\Consul\Integration\ClientFactory;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Yii3 的客户端工厂：从 PSR-11 容器里按类型取 PSR 服务，取不到就留空走库内默认，
 * 配置全部来自 params（键为 'erikwang2013/consul-php'）。
 *
 * ```php
 * // 本包的 config/di.php 已经这么接好了，应用侧一般不用自己写：
 * ConsulClient::class => static fn (ContainerInterface $c) => ConsulClientFactory::create(
 *     $params['erikwang2013/consul-php']['consul'],
 *     $c
 * ),
 * ```
 */
final class ConsulClientFactory
{
    /**
     * @param array<string, mixed> $config consul 配置：base_uri / token / cache / timeout / retry
     */
    public static function create(array $config, ContainerInterface $container): ConsulClient
    {
        return ClientFactory::create(
            $config,
            self::service($container, \Psr\Http\Client\ClientInterface::class),
            self::service($container, \Psr\Http\Message\RequestFactoryInterface::class),
            self::service($container, \Psr\Http\Message\StreamFactoryInterface::class),
            self::service($container, \Psr\Log\LoggerInterface::class),
            self::service($container, \Psr\SimpleCache\CacheInterface::class),
            self::service($container, \Psr\EventDispatcher\EventDispatcherInterface::class)
        );
    }

    /**
     * 容器里没有绑定时返回 null（ClientFactory 会走默认实现）；绑定错了类型直接报错，
     * 别拖到传输层才炸出一句莫名的 TypeError。
     *
     * @template T of object
     * @param class-string<T> $id
     * @return T|null
     */
    private static function service(ContainerInterface $container, string $id): ?object
    {
        if (!$container->has($id)) {
            return null;
        }

        $service = $container->get($id);

        if (!$service instanceof $id) {
            throw new RuntimeException(sprintf(
                '容器里 %s 的实现不符合该接口：%s',
                $id,
                get_debug_type($service)
            ));
        }

        return $service;
    }
}
