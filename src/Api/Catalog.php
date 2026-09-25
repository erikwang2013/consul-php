<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Support\Boolean;
use Erikwang2013\Consul\Transport\TransportInterface;
use InvalidArgumentException;

class Catalog
{
    private const QUERY_OPTIONS = ['dc', 'ns', 'filter', 'index', 'wait', 'node_meta'];

    /** 开关型一致性参数：真值才发出，形态与 Health::getWithOptions() 保持一致 */
    private const CONSISTENCY_FLAGS = ['stale', 'consistent'];

    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    /** node / service 层里不能靠下划线转驼峰推出的字段别名 */
    private const FIELD_ALIASES = ['id' => 'ID'];
    private const NODE_ALIASES = ['node' => 'Node', 'address' => 'Address', 'meta' => 'NodeMeta'];
    private const SERVICE_ALIASES = ['service' => 'Service'];

    /**
     * 注册节点 / 服务。
     *
     * $node 与 $service 里的 key 会按下划线转驼峰映射成 Consul 字段：node、address、port、
     * id、tags、meta、datacenter、tagged_addresses、enable_tag_override… 因此 Kind / Proxy /
     * Connect / Weights / TaggedAddresses / EnableTagOverride 这类 mesh 字段都能直接表达
     * （如 ['kind' => 'connect-proxy', 'proxy' => ['DestinationServiceName' => 'web']]）。
     * 嵌套结构（Proxy、Connect、Weights、check 的内容）按 Consul 原生字段名原样传递。
     *
     * $check 是节点级健康检查（写入 RegisterRequest.Check，不是 Service.Check；
     * 后者请写在 $service['check'] 里）。
     */
    public function register(array $node, array $service, array $check = []): array
    {
        if (!isset($node['node'], $node['address'], $service['service'])) {
            throw new InvalidArgumentException('register() requires node.node, node.address and service.service');
        }

        $payload = $this->mapFields($node, self::NODE_ALIASES);

        $service = $this->mapFields($service, self::SERVICE_ALIASES);
        if (!isset($service['Address'])) {
            $service['Address'] = $node['address'];
        }
        $payload['Service'] = $service;

        if (!empty($check)) {
            $payload['Check'] = $check;
        }

        return $this->transport->put('/v1/catalog/register', $payload);
    }

    public function deregister(array $node, string $serviceId = ''): void
    {
        if (!isset($node['node'])) {
            throw new InvalidArgumentException('deregister() requires node.node');
        }

        $payload = ['Node' => $node['node']];
        if (isset($node['datacenter'])) $payload['Datacenter'] = $node['datacenter'];
        if ($serviceId !== '') $payload['ServiceID'] = $serviceId;

        $this->transport->put('/v1/catalog/deregister', $payload);
    }

    /**
     * 列出已知的数据中心（多 DC 场景的第一跳，用于再按 dc 查询其他目录端点）。
     * 返回字符串数组，如 ['dc1', 'dc2']。
     */
    public function datacenters(): array
    {
        return $this->transport->get('/v1/catalog/datacenters');
    }

    public function nodes(array $options = []): array
    {
        return $this->getWithOptions('/v1/catalog/nodes', $options);
    }

    public function services(array $options = []): array
    {
        return $this->getWithOptions('/v1/catalog/services', $options);
    }

    public function service(string $service, array $options = []): array
    {
        return $this->getWithOptions('/v1/catalog/service/' . rawurlencode($service), $options);
    }

    public function connect(string $service, array $options = []): array
    {
        return $this->getWithOptions('/v1/catalog/connect/' . rawurlencode($service), $options);
    }

    /**
     * 某网关关联的服务列表：`GET /v1/catalog/gateway-services/:gateway`。
     *
     * `$gateway` 是网关的**服务名**（不是 `ingress` / `terminating` 这类类型名）。
     * 返回结构形如 `[['Gateway' => 'gw', 'GatewayKind' => 'ingress', 'Service' => 'web', ...], ...]`。
     * 未对活集群验证。
     */
    public function gatewayServices(string $gateway, array $options = []): array
    {
        return $this->getWithOptions('/v1/catalog/gateway-services/' . rawurlencode($gateway), $options);
    }

    public function node(string $node, array $options = []): array
    {
        return $this->getWithOptions('/v1/catalog/node/' . rawurlencode($node), $options);
    }

    public function nodeServices(string $node, array $options = []): array
    {
        return $this->getWithOptions('/v1/catalog/node-services/' . rawurlencode($node), $options);
    }

    /**
     * Catalog 读端点共用的查询组装：白名单过滤 + node-meta 展开成重复键。
     * 与 Health::getWithOptions() 同一套做法（传输层只接受数组，生成不了重复键）。
     *
     * node-meta 在 Consul 侧是「重复的纯键」——`agent/http.go` 的 parseMetaFilter 反复读取
     * `?node-meta=...`（`?node-meta=a&node-meta=b` 表示两个元数据都要匹配），而 http_build_query
     * 对数组只会生成 `node-meta[0]=...`，服务端读不到。因此这里手工把这一段拼进 path
     * （path 由传输层原样交给 HTTP 客户端，`?` 之后不再二次编码）。
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
     * stale 跟随者读（降延迟）、consistent 走 leader、max_stale 限制陈旧度上限。
     *
     * stale / consistent 是开关型参数，只有真值才发出，并归一成字符串 "true"——布尔 true
     * 交给 http_build_query 会编成 stale=1，而上游是 `b.Get("stale") == "true"`，认不出来。
     * max_stale 是时长（如 "10s"），原样透传交给服务端校验。
     * 两者同时为真时本库不拦，由 Consul 以 400 拒绝。
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

    /**
     * 简写 key 转 Consul 字段名：别名表优先，其余按下划线转驼峰（保留原顺序）。
     */
    private function mapFields(array $fields, array $aliases): array
    {
        $mapped = [];
        foreach ($fields as $key => $value) {
            $key = (string) $key;
            $mapped[$aliases[$key] ?? self::FIELD_ALIASES[$key] ?? self::pascalCase($key)] = $value;
        }
        return $mapped;
    }

    private static function pascalCase(string $key): string
    {
        if (strpos($key, '_') === false) {
            return ucfirst($key);
        }
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
    }
}
