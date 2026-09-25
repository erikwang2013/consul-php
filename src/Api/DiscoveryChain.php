<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;

/**
 * Discovery chain API（/v1/discovery-chain）。
 *
 * 把「一个服务名」解析成实际上游的整条链路：service-router（路由）→ service-splitter
 * （分流/权重）→ service-resolver（解析）→ 故障转移目标的编译结果。排障
 * 「流量为什么落到那个实例」「故障转移为什么没生效」时看的就是它。
 *
 * 链路本身由配置项驱动，改配置请走 ConfigEntry 模块。
 */
class DiscoveryChain
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    /**
     * 读取某条 discovery chain 的编译结果。
     *
     * $options 原样进 query string。上游此端点（agent/discovery_chain_endpoint.go）
     * 实际消费这些参数：
     *   dc                在哪个数据中心编译（上游同时认 datacenter）
     *   compile-dc        用哪个数据中心的配置来编译，但不因此在那边查询
     *   index / wait      阻塞查询（长轮询链路变化）
     *   stale / consistent  一致性模式——注意本端点只看参数是否**出现**，不看值，
     *                       所以 'stale' => true 就能生效
     *   filter            服务端过滤
     *
     * 参数名是 **compile-dc**，不是 compile。返回 ['Chain' => [...]]，链不存在时上游 404。
     * **未对活集群验证。**
     */
    public function read(string $name, array $options = []): array
    {
        return $this->transport->get('/v1/discovery-chain/' . rawurlencode($name), $options);
    }
}
