<?php

namespace Erikwang\Consul\Client;

use Erikwang\Consul\Config\ConfigCenter;
use Erikwang\Consul\Service\Discovery;
use Erikwang\Consul\Service\Registry;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class ConsulAsyncClient
{
    private ConsulClient $syncClient;

    public function __construct(
        array $config = [],
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?LoggerInterface $logger = null,
        ?CacheInterface $cache = null,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->syncClient = new ConsulClient(
            $config,
            $httpClient,
            $requestFactory,
            $streamFactory,
            $logger,
            $cache,
            $eventDispatcher
        );
    }

    public function wrap(callable $callable): Promise
    {
        return new Promise($callable);
    }

    public function serviceRegistry(): Registry
    {
        return $this->syncClient->serviceRegistry();
    }

    public function serviceDiscovery(): Discovery
    {
        return $this->syncClient->serviceDiscovery();
    }

    public function configCenter(): ConfigCenter
    {
        return $this->syncClient->configCenter();
    }

    public function __get(string $name)
    {
        return $this->syncClient->{$name};
    }
}
