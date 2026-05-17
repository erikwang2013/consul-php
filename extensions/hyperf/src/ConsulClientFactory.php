<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Hyperf;

use Erikwang2013\Consul\Client\ConsulClient;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class ConsulClientFactory
{
    public function __invoke(ContainerInterface $container): ConsulClient
    {
        $config = $container->get(ConfigInterface::class)->get('consul', []);

        $httpClient = $container->get(\Psr\Http\Client\ClientInterface::class);

        return new ConsulClient(
            $config,
            $httpClient,
            $container->get(\Psr\Http\Message\RequestFactoryInterface::class),
            $container->get(\Psr\Http\Message\StreamFactoryInterface::class),
            $container->get(LoggerInterface::class),
            ($config['cache']['enable'] ?? false) ? $container->get(CacheInterface::class) : null,
            $container->get(\Psr\EventDispatcher\EventDispatcherInterface::class)
        );
    }
}
