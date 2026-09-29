<?php

declare(strict_types=1);

use Erikwang2013\Consul\Client\ConsulClient;
use Erikwang2013\Consul\Integration\Yii2\ConsulComponent;

// 合并进 Yii 应用配置：$config = ArrayHelper::merge($config, require __DIR__ . '/consul.php');
return [
    'components' => [
        'consul' => [
            'class'   => ConsulComponent::class,
            'baseUri' => getenv('CONSUL_BASE_URI') ?: 'http://127.0.0.1:8500',
            'token'   => getenv('CONSUL_TOKEN') ?: '',
            'cache'   => [
                'enable' => true,
                'ttl'    => 300,
            ],
        ],
    ],
    // 容器注入的 ConsulClient 必须与上面这份配置同源：不注册的话，Yii 的 DI 会按构造函数
    // 默认值另建一个客户端（base_uri 退回 127.0.0.1:8500，静默忽略配置）。
    'container' => [
        'singletons' => [
            ConsulClient::class => static fn () => Yii::$app->get('consul')->getClient(),
        ],
    ],
];
