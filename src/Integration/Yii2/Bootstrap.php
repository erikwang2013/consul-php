<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Integration\Yii2;

use Erikwang2013\Consul\Client\ConsulClient;
use yii\base\BootstrapInterface;
use Yii;

/**
 * 一行接入：把类名加进应用配置的 bootstrap 数组，就有了 Yii::$app->consul。
 *
 * ```php
 * // config/web.php
 * 'bootstrap' => [\Erikwang2013\Consul\Integration\Yii2\Bootstrap::class],
 * ```
 *
 * Yii 没有 composer 自动发现，bootstrap 是 Yii 给扩展准备的官方引导机制。
 * 已经在 components 里配过 consul 组件时，这里只补容器里的 ConsulClient，不覆盖组件配置。
 */
class Bootstrap implements BootstrapInterface
{
    /**
     * @param \yii\base\Application $app
     */
    public function bootstrap($app): void
    {
        // 容器里注入的 ConsulClient 必须与组件同源：不注册的话，Yii 的 DI 会按构造函数默认值
        // 另建一个客户端（base_uri 退回 127.0.0.1:8500，静默忽略配置）
        if (!isset(Yii::$container->getDefinitions()[ConsulClient::class])) {
            Yii::$container->setSingleton(
                ConsulClient::class,
                static fn (): ConsulClient => $app->get('consul')->getClient()
            );
        }

        if (!$app->has('consul')) {
            $config = require __DIR__ . '/config/consul.php';
            $app->set('consul', $config['components']['consul']);
        }
    }
}
