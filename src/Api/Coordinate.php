<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;
use InvalidArgumentException;

class Coordinate
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function datacenters(): array
    {
        return $this->transport->get('/v1/coordinate/datacenters');
    }

    public function nodes(array $options = []): array
    {
        return $this->transport->get('/v1/coordinate/nodes', $options);
    }

    public function node(string $node, array $options = []): array
    {
        return $this->transport->get('/v1/coordinate/node/' . rawurlencode($node), $options);
    }

    /**
     * 上报一个节点的网络坐标（`consul rtt` 与各 agent 定期上报走的就是这个端点）。
     *
     * $coordinate 用 Consul 的原生字段名：
     *   ['Node' => 'web01', 'Segment' => '', 'Coord' => ['Vec' => [0.1, 0.2], 'Error' => 0.5, 'Height' => 1.0]]
     *
     * 字段名是 **Coord** 而不是 "Coordinates"（上游 structs.CoordinateUpdateRequest
     * 与 api.CoordinateEntry 都叫 Coord）。上游解码时对未知字段是静默忽略，
     * 传错名字不会报参数错，但 Coord 会变成 null，请求在 RPC 侧被拒——所以这里直接拦住。
     *
     * 成功时上游不返回内容（`return nil, nil`），本方法返回空数组。
     * **未对活集群验证。**
     */
    public function update(array $coordinate): array
    {
        if (!isset($coordinate['Node']) || $coordinate['Node'] === '') {
            throw new InvalidArgumentException('update() requires a non-empty "Node"');
        }
        if (isset($coordinate['Coordinates'])) {
            throw new InvalidArgumentException(
                'update() expects "Coord", not "Coordinates" (upstream field is CoordinateUpdateRequest.Coord)'
            );
        }

        return $this->transport->put('/v1/coordinate/update', $coordinate);
    }
}
