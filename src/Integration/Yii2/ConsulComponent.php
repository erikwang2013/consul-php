<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Integration\Yii2;

use Erikwang2013\Consul\Client\ConsulClient;
use Erikwang2013\Consul\Integration\ClientFactory;
use Psr\SimpleCache\CacheInterface;
use yii\base\Component;

/**
 * Yii2 组件：在应用配置的 components 里注册，客户端按需构造。
 *
 * ```php
 * // config/web.php
 * 'components' => [
 *     'consul' => [
 *         'class'   => \Erikwang2013\Consul\Integration\Yii2\ConsulComponent::class,
 *         'baseUri' => 'http://127.0.0.1:8500',
 *         'token'   => 'acl-token',
 *     ],
 * ],
 *
 * // 使用
 * Yii::$app->consul->client->serviceRegistry()->register('my-app', '10.0.0.1', 8080);
 * ```
 *
 * @property ConsulClient $client
 */
class ConsulComponent extends Component
{
    public string $baseUri = 'http://127.0.0.1:8500';

    public string $token = '';

    /** @var array<string, mixed> 缓存开关与 TTL，仅在注入 psrCache 时生效 */
    public array $cache = ['enable' => true, 'ttl' => 300];

    /** @var array<string, mixed> ConsulClient 的其余配置（timeout / retry 等） */
    public array $options = [];

    /**
     * PSR-16 缓存实现，可选。
     *
     * Yii 自带的 cache 组件不是 PSR-16（未命中返回 false 而非默认值），不要直接传——
     * 那会让缓存未命中被当成命中。需要缓存时注入一个 PSR-16 实现。
     */
    public ?CacheInterface $psrCache = null;

    private ?ConsulClient $client = null;

    public function getClient(): ConsulClient
    {
        if ($this->client === null) {
            $this->client = ClientFactory::create(
                $this->options + [
                    'base_uri' => $this->baseUri,
                    'token'    => $this->token,
                    'cache'    => $this->cache,
                ],
                null,
                null,
                null,
                null,
                $this->psrCache,
                null
            );
        }

        return $this->client;
    }

    /** 自行构造好客户端时（多集群、协程 HTTP 客户端等）直接替换，绕过上面的配置项。 */
    public function setClient(ConsulClient $client): void
    {
        $this->client = $client;
    }
}
