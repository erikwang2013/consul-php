<?php

declare(strict_types=1);

use Erikwang2013\Consul\Client\ConsulClient;
use Erikwang2013\Consul\Integration\Yii3\ConsulClientFactory;
use Psr\Container\ContainerInterface;

/* @var array $params   由 yiisoft/config 注入（params 组的合并结果） */

// 应用里的 PSR-18/17 HTTP 客户端、PSR-3 日志、PSR-16 缓存、PSR-14 事件分发器
// 都会被自动注入；没绑定的留空，用库内默认（discovery → 内置 cURL、NullLogger）
return [
    ConsulClient::class => static fn (ContainerInterface $container): ConsulClient => ConsulClientFactory::create(
        $params['erikwang2013/consul-php']['consul'],
        $container
    ),
];
