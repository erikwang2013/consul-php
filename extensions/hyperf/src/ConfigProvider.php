<?php

declare(strict_types=1);

namespace Erikwang\Consul\Hyperf;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                \Erikwang\Consul\Client\ConsulClient::class => ConsulClientFactory::class,
            ],
            'publish' => [
                [
                    'id'          => 'consul',
                    'description' => 'Consul config',
                    'source'      => __DIR__ . '/../publish/consul.php',
                    'destination' => BASE_PATH . '/config/autoload/consul.php',
                ],
            ],
        ];
    }
}
