<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Support\Boolean;
use Erikwang2013\Consul\Transport\TransportInterface;

class Health
{
    private const QUERY_OPTIONS = ['dc', 'ns', 'filter', 'index', 'wait', 'passing', 'near', 'node_meta'];

    /** 开关型一致性参数：真值才发出，形态与 Snapshot::save() 保持一致 */
    private const CONSISTENCY_FLAGS = ['stale', 'consistent'];

    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    public function node(string $node, array $options = []): array
    {
        return $this->getWithOptions('/v1/health/node/' . rawurlencode($node), $options);
    }

    public function checks(string $service, array $options = []): array
    {
        return $this->getWithOptions('/v1/health/checks/' . rawurlencode($service), $options);
    }

    public function service(string $service, array $options = []): array
    {
        return $this->getWithOptions('/v1/health/service/' . rawurlencode($service), $options);
    }

    public function connect(string $service, array $options = []): array
    {
        return $this->getWithOptions('/v1/health/connect/' . rawurlencode($service), $options);
    }

    public function state(string $state, array $options = []): array
    {
        return $this->getWithOptions('/v1/health/state/' . rawurlencode($state), $options);
    }

    public function ingress(string $service, array $options = []): array
    {
        return $this->getWithOptions('/v1/health/ingress/' . rawurlencode($service), $options);
    }

    /**
     * 六个 health 端点共用的查询组装：白名单过滤 + 一致性参数 + node-meta 展开成重复键。
     *
     * node-meta 在 Consul 侧是「重复的纯键」——`agent/http.go` 的 parseMetaFilter 反复读取
     * `?node-meta=...` 直到取不到（`?node-meta=a&node-meta=b` 表示两个元数据都要匹配）；
     * 而 http_build_query 对数组只会生成 `node-meta[0]=...` 这种带下标的键，服务端读不到，
     * 多条件过滤因此无法表达。传输层只接受数组、不做重复键，所以这里手工把这一段拼进 path
     * （path 由传输层原样交给 HTTP 客户端，`?` 之后的查询串不会再被二次编码）。
     *
     * 未验证：多值 node-meta 在真实 agent 上的过滤语义（本机无 Consul 实例，仅据上游源码判定）。
     */
    private function getWithOptions(string $path, array $options): array
    {
        $query = array_intersect_key($options, array_flip(self::QUERY_OPTIONS))
            + $this->consistencyQuery($options);

        // Consul 侧是重复的纯键（`?node-meta=a&node-meta=b`）；数组值交给传输层展开，不再手工拼 path。
        // 空数组视为未传（否则会发出一条空参数）
        if (isset($query['node_meta'])) {
            $meta = array_values(array_filter((array) $query['node_meta'], static fn ($v): bool => $v !== '' && $v !== null));
            unset($query['node_meta']);
            if ($meta !== []) {
                $query['node-meta'] = $meta;
            }
        }

        return $this->transport->get($path, $query);
    }

    /**
     * 一致性模式（上游 agent/http.go 的 parseConsistency，所有读端点都消费）：
     * stale 跟随者读、consistent 走 leader、max_stale 限制陈旧度上限。
     *
     * stale / consistent 是开关型参数，只有真值才发出，并按本库既有写法（见 Snapshot::save）
     * 归一成字符串 "true"：布尔 true 直接交给 http_build_query 会编成 stale=1，与 Consul
     * 文档里的 ?stale 形态不一致。max_stale 是时长（如 "10s"），原样透传交给服务端校验。
     * stale 与 consistent 同时为真时本库不拦，由 Consul 以 400 拒绝。
     */
    private function consistencyQuery(array $options): array
    {
        $query = [];

        foreach (self::CONSISTENCY_FLAGS as $flag) {
            if (Boolean::isTrue($options[$flag] ?? false)) {
                $query[$flag] = 'true';
            }
        }

        if (isset($options['max_stale']) && $options['max_stale'] !== '') {
            $query['max_stale'] = $options['max_stale'];
        }

        return $query;
    }
}
