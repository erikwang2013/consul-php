<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Integration\Hyperf;

use Erikwang2013\Consul\Client\ConsulClient;
use Erikwang2013\Consul\Integration\ClientFactory;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class ConsulClientFactory
{
    public function __invoke(ContainerInterface $container): ConsulClient
    {
        $config = $container->get(ConfigInterface::class)->get('consul', []);

        return ClientFactory::create(
            $config,
            $container->has(\Psr\Http\Client\ClientInterface::class) ? $container->get(\Psr\Http\Client\ClientInterface::class) : null,
            $container->has(\Psr\Http\Message\RequestFactoryInterface::class) ? $container->get(\Psr\Http\Message\RequestFactoryInterface::class) : null,
            $container->has(\Psr\Http\Message\StreamFactoryInterface::class) ? $container->get(\Psr\Http\Message\StreamFactoryInterface::class) : null,
            $container->has(LoggerInterface::class) ? $container->get(LoggerInterface::class) : null,
            $container->has(CacheInterface::class) ? $container->get(CacheInterface::class) : null,
            $container->has(\Psr\EventDispatcher\EventDispatcherInterface::class)
                ? $container->get(\Psr\EventDispatcher\EventDispatcherInterface::class)
                : null
        );
    }
}
