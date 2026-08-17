<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Integration;

use Erikwang2013\Consul\Client\ConsulClient;

final class ClientFactory
{
    public static function create(
        array $config,
        ?\Psr\Http\Client\ClientInterface $httpClient,
        ?\Psr\Http\Message\RequestFactoryInterface $requestFactory,
        ?\Psr\Http\Message\StreamFactoryInterface $streamFactory,
        ?\Psr\Log\LoggerInterface $logger,
        ?\Psr\SimpleCache\CacheInterface $cache,
        ?\Psr\EventDispatcher\EventDispatcherInterface $dispatcher
    ): ConsulClient {
        return new ConsulClient(
            $config,
            $httpClient,
            $requestFactory,
            $streamFactory,
            $logger,
            ($config['cache']['enable'] ?? false) && $cache !== null ? $cache : null,
            $dispatcher
        );
    }
}
