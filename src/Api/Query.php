<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;

/**
 * 预备查询 API（/v1/query）。
 *
 * 预备查询把「服务发现 + 过滤 + 故障转移策略 + 可选 DNS/Template」打包成一个具名对象，
 * 客户端只带一个 ID 就能拿到已按策略收敛好的实例列表，是服务发现侧的常用封装。
 *
 * ## 方法语义（对照上游 agent/http_register.go 与 agent/prepared_query_endpoint.go）
 *
 * | 操作    | 方法与路径                        |
 * |---------|-----------------------------------|
 * | list    | GET    /v1/query                  |
 * | create  | POST   /v1/query                  |
 * | read    | GET    /v1/query/:id              |
 * | update  | PUT    /v1/query/:id              |
 * | delete  | DELETE /v1/query/:id              |
 * | execute | GET    /v1/query/:id/execute      |
 * | explain | GET    /v1/query/:id/explain      |
 *
 * 注意 execute/explain **都是 GET**，不是 POST：`/v1/query/` 前缀在上游注册时未限定方法，
 * 由 handler 自行判定，handler 对这两个后缀只接受 GET（v1.15 / v1.20 / main 均如此）。
 * execute 的 $id 除 UUID 外也可以传查询名。
 *
 * create 返回 ['ID' => '<uuid>']；update/delete 上游返回空 body。
 *
 * 注：路径与参数已对照上游源码，但**未对活集群验证**。
 */
class Query
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
     * 列出全部预备查询。
     *
     * @param array $options 支持 dc / ns / partition
     */
    public function list(array $options = []): array
    {
        return $this->transport->get('/v1/query', $this->optionsQuery($options));
    }

    /**
     * 新建预备查询，返回 ['ID' => '<uuid>']。
     *
     * body 形如 ['Name' => 'web', 'Service' => ['Service' => 'web', 'Failover' => ['NearestN' => 3]]]。
     */
    public function create(array $query, array $options = []): array
    {
        return $this->transport->post('/v1/query', $query, $this->optionsQuery($options));
    }

    /**
     * 读取单个预备查询。不存在时抛 NotFoundException。
     */
    public function read(string $id, array $options = []): array
    {
        return $this->transport->get('/v1/query/' . rawurlencode($id), $this->optionsQuery($options));
    }

    /**
     * 更新预备查询（整体覆盖）。body 内的 ID 会被路径中的 ID 覆盖。
     *
     * 上游成功时返回空 body，故这里返回空数组，失败会抛异常。
     */
    public function update(string $id, array $query, array $options = []): array
    {
        return $this->transport->put('/v1/query/' . rawurlencode($id), $query, $this->optionsQuery($options));
    }

    /**
     * 删除预备查询。上游成功时返回空 body。
     */
    public function delete(string $id, array $options = []): void
    {
        $this->transport->delete('/v1/query/' . rawurlencode($id), $this->optionsQuery($options));
    }

    /**
     * 执行预备查询，返回 ['Nodes' => [...], 'Service' => ..., 'DNS' => ..., 'Failover' => ...]。
     *
     * @param string $id      查询 UUID 或查询名
     * @param array  $options 除 dc / ns / partition 外，还支持：
     *                        limit（最多返回多少实例）、near（就近排序的节点）、
     *                        connect（true 时只返回支持 Connect 的实例）、stale
     */
    public function execute(string $id, array $options = []): array
    {
        return $this->transport->get(
            '/v1/query/' . rawurlencode($id) . '/execute',
            $this->optionsQuery($options, ['limit', 'connect', 'near', 'stale'])
        );
    }

    /**
     * 解释某次查询会如何被解析（返回结构同 execute，另含 Query 字段），用于排查
     * 「为什么返回了这些实例」。
     */
    public function explain(string $id, array $options = []): array
    {
        return $this->transport->get(
            '/v1/query/' . rawurlencode($id) . '/explain',
            $this->optionsQuery($options)
        );
    }

    private function optionsQuery(array $options, array $extra = []): array
    {
        return array_intersect_key(
            $options,
            array_flip(array_merge(['dc', 'ns', 'partition'], $extra))
        );
    }
}
