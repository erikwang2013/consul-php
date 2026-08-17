<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Integration\Thinkphp;

use Erikwang2013\Consul\Client\ConsulClient;
use Erikwang2013\Consul\Integration\ClientFactory;
use think\Service;

class ConsulService extends Service
{
    public function register(): void
    {
        $this->app->bind('consul', function () {
            $config = $this->app->config->get('consul', []);

            return ClientFactory::create(
                $config,
                $this->app->bound(\Psr\Http\Client\ClientInterface::class) ? $this->app->make(\Psr\Http\Client\ClientInterface::class) : null,
                $this->app->bound(\Psr\Http\Message\RequestFactoryInterface::class) ? $this->app->make(\Psr\Http\Message\RequestFactoryInterface::class) : null,
                $this->app->bound(\Psr\Http\Message\StreamFactoryInterface::class) ? $this->app->make(\Psr\Http\Message\StreamFactoryInterface::class) : null,
                $this->app->bound(\Psr\Log\LoggerInterface::class) ? $this->app->make(\Psr\Log\LoggerInterface::class) : null,
                $this->app->bound(\Psr\SimpleCache\CacheInterface::class) ? $this->app->make(\Psr\SimpleCache\CacheInterface::class) : null,
                $this->app->bound(\Psr\EventDispatcher\EventDispatcherInterface::class)
                    ? $this->app->make(\Psr\EventDispatcher\EventDispatcherInterface::class)
                    : null
            );
        });
    }
}
