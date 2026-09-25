<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;

/**
 * Connect 服务网格的授权链：意图（intention）。
 *
 * 意图描述「源服务 -> 目标服务」是否允许通信，是 mesh 里东西向流量的授权单元，
 * 也是 discovery chain 的一部分。没有意图时 Consul 默认放行；一旦为目标服务定义了
 * 意图，未被任何意图匹配的流量一律拒绝，所以这是网格安全的关键开关。
 *
 * ## 端点选择（以 Consul 上游 agent/http_register.go 与 agent/intentions_endpoint.go 为准）
 *
 * - CRUD 用 `/v1/connect/intentions/exact?source=&destination=`（GET/PUT/DELETE），
 *   身份取自 **query 参数**，PUT 的 body 里即使带 Source/Destination/ID 也会被服务端忽略。
 * - `/v1/connect/intentions/:id`（按 UUID 操作）上游已标注 **deprecated**，
 *   仅为兼容既有 UUID 而保留在本类，命名带 `ById` 后缀。
 * - `create` 用的 `POST /v1/connect/intentions` 上游同样标注 deprecated，但它是唯一
 *   返回新建意图 UUID 的入口，故保留。
 *
 * 新代码建议改用 `service-intentions` 配置项（见 {@see ConfigEntry}），
 * 它能一次声明目标服务的全部规则，且支持 L7（HTTP）匹配。
 *
 * 注：本模块路径与参数已对照上游源码，但**未对活集群验证**。
 */
class Connect
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
     * 列出所有意图。
     *
     * @param array $options 支持 dc / ns / partition / filter
     */
    public function intentions(array $options = []): array
    {
        return $this->transport->get('/v1/connect/intentions', $this->optionsQuery($options));
    }

    /**
     * 新建意图，返回 ['ID' => '<uuid>']。
     *
     * body 为意图对象，如
     * ['SourceName' => 'web', 'DestinationName' => 'db', 'Action' => 'allow']。
     *
     * 上游将该端点标注为 deprecated（推荐 service-intentions 配置项），但它是唯一
     * 返回新建 UUID 的入口。注意该端点不接受 partition 字段。
     */
    public function intentionCreate(array $intention, array $options = []): array
    {
        return $this->transport->post('/v1/connect/intentions', $intention, $this->optionsQuery($options));
    }

    /**
     * 按源/目标读取意图（非 deprecated 路径）。
     *
     * 不存在时返回 404，传输层抛 NotFoundException。
     */
    public function intentionRead(string $source, string $destination, array $options = []): array
    {
        return $this->transport->get(
            '/v1/connect/intentions/exact',
            $this->exactQuery($source, $destination, $options)
        );
    }

    /**
     * 按源/目标新建或更新意图。身份来自 query，$intention 里无需再带
     * SourceName/DestinationName/ID（带了也会被忽略）。
     *
     * @return bool 服务端返回 JSON true
     */
    public function intentionUpdate(string $source, string $destination, array $intention, array $options = []): bool
    {
        $response = $this->transport->put(
            '/v1/connect/intentions/exact',
            $intention,
            $this->exactQuery($source, $destination, $options)
        );
        return ($response['body'] ?? null) === true;
    }

    /**
     * 按源/目标删除意图。目标是通配（`*`）时删除的是该通配规则本身。
     *
     * @return bool 服务端返回 JSON true
     */
    public function intentionDelete(string $source, string $destination, array $options = []): bool
    {
        $response = $this->transport->delete(
            '/v1/connect/intentions/exact',
            $this->exactQuery($source, $destination, $options)
        );
        return ($response['body'] ?? null) === true;
    }

    /**
     * 按 UUID 读取意图。deprecated 路径，仅为兼容既有 UUID。
     *
     * @deprecated 上游 IntentionSpecific 已废弃，改用 intentionRead()
     */
    public function intentionReadById(string $id, array $options = []): array
    {
        return $this->transport->get(
            '/v1/connect/intentions/' . rawurlencode($id),
            $this->optionsQuery($options)
        );
    }

    /**
     * 按 UUID 更新意图。body 内的 ID 会被路径中的 ID 覆盖。
     *
     * @deprecated 上游 IntentionSpecific 已废弃，改用 intentionUpdate()
     *
     * @return bool 服务端返回 JSON true
     */
    public function intentionUpdateById(string $id, array $intention, array $options = []): bool
    {
        $response = $this->transport->put(
            '/v1/connect/intentions/' . rawurlencode($id),
            $intention,
            $this->optionsQuery($options)
        );
        return ($response['body'] ?? null) === true;
    }

    /**
     * 按 UUID 删除意图。
     *
     * @deprecated 上游 IntentionSpecific 已废弃，改用 intentionDelete()
     *
     * @return bool 服务端返回 JSON true
     */
    public function intentionDeleteById(string $id, array $options = []): bool
    {
        $response = $this->transport->delete(
            '/v1/connect/intentions/' . rawurlencode($id),
            $this->optionsQuery($options)
        );
        return ($response['body'] ?? null) === true;
    }

    /**
     * 查询「按源匹配」或「按目标匹配」时会命中哪些意图，按优先级排序。
     *
     * @param string $by    'source' 或 'destination'（其他值 Consul 返回 400）
     * @param array  $names 要查询的服务名列表，会展开成重复的 name 参数；
     *                      返回结果的顺序与传入顺序一一对应
     */
    public function intentionMatch(string $by, array $names, array $options = []): array
    {
        // name 必须是重复键（name=a&name=b，Go 侧按 url.Values 读）：
        // 传输层已把数组值展开成重复键，这里直接传数组，不要再手工拼查询串。
        return $this->transport->get(
            '/v1/connect/intentions/match',
            array_merge(['by' => $by, 'name' => array_values($names)], $this->optionsQuery($options))
        );
    }

    /**
     * 判断某个源能否访问某个目标，返回 ['Allowed' => bool, 'Reason' => string]。
     *
     * @param array $options 支持 dc / ns / partition / source-type
     *                       （source-type 非 consul 时 $source 按外部标识处理）
     */
    public function intentionCheck(string $source, string $destination, array $options = []): array
    {
        return $this->transport->get(
            '/v1/connect/intentions/check',
            array_merge(['source' => $source, 'destination' => $destination], $this->optionsQuery($options))
        );
    }

    private function exactQuery(string $source, string $destination, array $options): array
    {
        return array_merge(
            ['source' => $source, 'destination' => $destination],
            $this->optionsQuery($options)
        );
    }

    private function optionsQuery(array $options): array
    {
        $query = array_intersect_key($options, array_flip(['dc', 'ns', 'partition', 'filter', 'source_type']));
        if (isset($query['source_type'])) {
            $query['source-type'] = $query['source_type'];
            unset($query['source_type']);
        }
        return $query;
    }
}
