<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;

/**
 * 集群 peering API（/v1/peering、/v1/peerings）。
 *
 * peering 让两个 Consul 集群直接互认（无需联邦或网格网关串联即可跨集群做服务发现），
 * 是 Consul 1.14 起 CE 版即可用的能力。典型流程：
 *
 * 1. 在 A 集群 `generateToken(['PeerName' => 'b'])` 拿到 `PeeringToken`；
 * 2. 把 token 交给 B 集群 `establish(['PeerName' => 'a', 'PeeringToken' => $token])`；
 * 3. 之后用 `list()` / `read()` 查看对端状态，`delete()` 断开。
 *
 * 注意：CE 版 peering 的对端必须能直连（或经 server 的 external address）；
 * 经 mesh gateway 中转、admin partition 内的 peering 属 Enterprise 特性。
 *
 * 路径对照上游 agent/http_register.go：`/v1/peerings`（list）与
 * `/v1/peering/`（按 name 的 GET/DELETE），因此 read/delete 用 `/v1/peering/:name`。
 *
 * 注：路径与 body 字段已对照上游源码，但**未对活集群验证**。
 */
class Peering
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * 生成本集群的 peering token，返回 ['PeeringToken' => '<token>']。
     *
     * @param array $payload 必填 PeerName；可选 Partition、Datacenter、
     *                       ServerExternalAddresses（本端 server 对外地址列表，
     *                       对端无法直连时用于告知可达地址）、Meta
     */
    public function generateToken(array $payload, array $options = []): array
    {
        return $this->transport->post('/v1/peering/token', $payload, $this->optionsQuery($options));
    }

    /**
     * 用对端给的 token 建立 peering，返回 peering 对象。
     *
     * @param array $payload 必填 PeerName 与 PeeringToken；可选 Partition、Meta
     */
    public function establish(array $payload, array $options = []): array
    {
        return $this->transport->post('/v1/peering/establish', $payload, $this->optionsQuery($options));
    }

    /**
     * 列出本集群的全部 peering（含各状态）。
     *
     * @param array $options 支持 partition / dc
     */
    public function list(array $options = []): array
    {
        return $this->transport->get('/v1/peerings', $this->optionsQuery($options));
    }

    /**
     * 按 name 读取单个 peering 的状态（含对端 server 地址与最近心跳）。
     * 不存在时抛 NotFoundException。
     */
    public function read(string $name, array $options = []): array
    {
        return $this->transport->get('/v1/peering/' . rawurlencode($name), $this->optionsQuery($options));
    }

    /**
     * 按 name 删除 peering（本端断开；对端需自行清理）。上游成功时返回空 body。
     */
    public function delete(string $name, array $options = []): void
    {
        $this->transport->delete('/v1/peering/' . rawurlencode($name), $this->optionsQuery($options));
    }

    private function optionsQuery(array $options): array
    {
        return array_intersect_key($options, array_flip(['dc', 'partition']));
    }
}
