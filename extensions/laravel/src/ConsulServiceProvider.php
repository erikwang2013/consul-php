<?php

namespace Erikwang2013\Consul\Laravel;

use Erikwang2013\Consul\Client\ConsulClient;
use Illuminate\Support\ServiceProvider;

class ConsulServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/consul.php', 'consul');

        $this->app->singleton(ConsulClient::class, function ($app) {
            $config = $app['config']['consul'];

            return new ConsulClient(
                $config,
                $app->make(\Psr\Http\Client\ClientInterface::class),
                $app->make(\Psr\Http\Message\RequestFactoryInterface::class),
                $app->make(\Psr\Http\Message\StreamFactoryInterface::class),
                $app->make(\Psr\Log\LoggerInterface::class),
                $config['cache']['enable'] ? $app->make(\Psr\SimpleCache\CacheInterface::class) : null,
                $app->make(\Psr\EventDispatcher\EventDispatcherInterface::class)
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/consul.php' => config_path('consul.php'),
        ], 'consul-config');
    }
}
