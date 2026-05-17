<?php

namespace Erikwang2013\Consul\Thinkphp;

use Erikwang2013\Consul\Client\ConsulClient;
use think\Service;

class ConsulService extends Service
{
    public function register(): void
    {
        $this->app->bind('consul', function () {
            $config = $this->app->config->get('consul', []);

            return new ConsulClient(
                $config,
                $this->app->make(\Psr\Http\Client\ClientInterface::class),
                $this->app->make(\Psr\Http\Message\RequestFactoryInterface::class),
                $this->app->make(\Psr\Http\Message\StreamFactoryInterface::class),
                $this->app->make(\Psr\Log\LoggerInterface::class),
                isset($config['cache']['enable']) && $config['cache']['enable'] && $this->app->bound(\Psr\SimpleCache\CacheInterface::class)
                    ? $this->app->make(\Psr\SimpleCache\CacheInterface::class)
                    : null,
                $this->app->bound(\Psr\EventDispatcher\EventDispatcherInterface::class)
                    ? $this->app->make(\Psr\EventDispatcher\EventDispatcherInterface::class)
                    : null
            );
        });
    }
}
