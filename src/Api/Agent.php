<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;

class Agent
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function members(array $options = []): array
    {
        $query = [];
        if (isset($options['wan'])) $query['wan'] = '1';
        return $this->transport->get('/v1/agent/members', $query);
    }

    public function self(): array
    {
        return $this->transport->get('/v1/agent/self');
    }

    public function maintenance(bool $enable, string $reason = ''): void
    {
        $this->transport->put('/v1/agent/maintenance', [], $this->maintenanceQuery($enable, $reason));
    }

    private function maintenanceQuery(bool $enable, string $reason): array
    {
        $query = ['enable' => $enable ? 'true' : 'false'];
        if ($reason !== '') {
            $query['reason'] = $reason;
        }
        return $query;
    }

    public function join(string $address, bool $wan = false): void
    {
        $query = $wan ? ['wan' => '1'] : [];
        $this->transport->put('/v1/agent/join/' . rawurlencode($address), [], $query);
    }

    /**
     * 强制某节点离开。$options 原样作 query 透传，Consul 认这两个：
     *   prune  清理该节点上已消亡的服务与检查
     *   wan    按 WAN 成员表操作（跨 DC）
     */
    public function forceLeave(string $node, array $options = []): void
    {
        $this->transport->put('/v1/agent/force-leave/' . rawurlencode($node), [], $options);
    }

    /**
     * 重载本节点配置与 TLS 证书（PUT /v1/agent/reload）。
     */
    public function reload(): array
    {
        return $this->transport->put('/v1/agent/reload');
    }

    /**
     * 让本节点优雅离开集群（PUT /v1/agent/leave）。
     * 与 forceLeave() 不同：这是由被通知的 agent 自己发起、会先把服务标记为离开。
     */
    public function leave(): array
    {
        return $this->transport->put('/v1/agent/leave');
    }

    /**
     * 本 agent 的版本信息（GET /v1/agent/version）。
     * 返回 version.BuildInfo：SHA / BuildDate / HumanVersion / FIPS 四个键。
     */
    public function version(): array
    {
        return $this->transport->get('/v1/agent/version');
    }

    /**
     * 本 agent 所在主机的资源信息（GET /v1/agent/host）：OS、主机名、
     * CPU 核数、内存与磁盘使用。需要 operator:read ACL。
     * 返回体是 debug.CollectHostInfo() 的结构，字段名**未对活集群验证**。
     */
    public function host(): array
    {
        return $this->transport->get('/v1/agent/host');
    }

    /**
     * 读取本 agent 的指标快照（GET /v1/agent/metrics）。
     *
     * 不传 $format 返回 JSON 数组（键为指标名 / 标签），具体结构**未对活集群验证**。
     * 传 'prometheus' 时上游走 promhttp，返回的是 text/plain 文本而非 JSON，
     * 交给 get() 会被 JSON 解码直接抛异常，因此这里改走 getRaw()，包成
     * ['format' => $format, 'body' => $text] 返回。
     *
     * 两个坑：Prometheus 输出要求 agent 侧配了 telemetry.prometheus_retention_time，
     * 否则服务端直接 415；另外请求头带 Accept: application/openmetrics-text 时，
     * 即使不传 format 也会走 Prometheus 分支。
     */
    public function metrics(?string $format = null): array
    {
        if ($format === null || $format === '') {
            return $this->transport->get('/v1/agent/metrics');
        }

        return [
            'format' => $format,
            'body' => $this->transport->getRaw('/v1/agent/metrics', ['format' => $format]),
        ];
    }

    /**
     * 更新本 agent 自己使用的 ACL token（PUT /v1/agent/token/:kind）。
     *
     * $kind 取 acl_token / acl_agent_token / acl_agent_master_token /
     * acl_replication_token / dns_token / config_file_service_registration
     * （也接受 default、agent、agent_master、agent_recovery、replication、dns 别名），
     * 未知值服务端 404；ACL 未启用时 401。body 固定为 {"Token": "..."}。
     *
     * 这是遗留接口，只影响本 agent 进程自己持有的 token（acl_token 是默认 token，
     * 改它不会覆盖已有的 agent token）；新代码应优先用 /v1/acl/token 系列。
     */
    public function updateToken(string $kind, string $token): array
    {
        return $this->transport->put('/v1/agent/token/' . rawurlencode($kind), ['Token' => $token]);
    }

    /**
     * Connect 授权判定（POST /v1/agent/connect/authorize）：拿目标服务与客户端证书身份
     * 问本 agent 的 intentions 放不放行，排障 mesh 连通性时用。
     *
     * $payload 形如：
     *   ['Target' => 'db',
     *    'ClientCertURI' => 'spiffe://abc.consul/ns/default/dc/dc1/svc/web',
     *    'ClientCertSerial' => 'aa:bb:cc:...']
     * Target 与 ClientCertURI 必填，缺失或 URI 非法服务端 400；ClientCertSerial 用于对吊销列表。
     * 返回 ['Authorized' => bool, 'Reason' => string]；注意本端点把 L7 intention 一律判 DENY。
     */
    public function connectAuthorize(array $payload): array
    {
        return $this->transport->post('/v1/agent/connect/authorize', $payload);
    }

    /**
     * 本 agent 视角的 Connect CA 根证书链（GET /v1/agent/connect/ca/roots）。
     * 返回 ActiveRootID / TrustDomain / Roots[]，每个 Root 含 ID、RootCert、Active、
     * IntermediateCerts 等；字段名**未对活集群验证**。
     */
    public function connectCaRoots(): array
    {
        return $this->transport->get('/v1/agent/connect/ca/roots');
    }

    /**
     * 取某个服务的 leaf 证书（GET /v1/agent/connect/ca/leaf/:service_id）。
     *
     * 注意路径上要填的是**服务名**而不是实例 ID（上游注释：Need name not ID），
     * 由 agent 决定用哪个实例。返回 IssuedCert（SerialNumber / CertPEM /
     * PrivateKeyPEM / Service / ServiceURI / ValidAfter / ValidBefore），
     * 字段名**未对活集群验证**。需要 service:read，且服务需开启 Connect。
     */
    public function connectCaLeaf(string $serviceId): array
    {
        return $this->transport->get('/v1/agent/connect/ca/leaf/' . rawurlencode($serviceId));
    }

    /**
     * 查询本节点上按服务名匹配的服务实例健康状况。
     *
     * 注意：Consul 用 HTTP 状态码表达健康结果，传输层会把非 2xx 抛成异常，
     * 因此本方法在服务不健康时不会有返回值：
     *   200 全部 passing（此时才返回数组）
     *   429 存在 warning 检查 → ConsulRequestException
     *   503 存在 critical 检查 → ServerException
     *   404 服务不存在 → NotFoundException
     *
     * $options 原样作为 query 传给 Consul，可用 passing、filter、node-meta、ns、partition 等。
     */
    public function healthServiceByName(string $name, array $options = []): array
    {
        return $this->transport->get('/v1/agent/health/service/name/' . rawurlencode($name), $options);
    }

    /**
     * 查询本节点上单个服务实例的健康状况（按 ServiceID）。
     * 状态码语义同 healthServiceByName()：非 200 会被传输层抛成异常。
     */
    public function healthServiceById(string $serviceId, array $options = []): array
    {
        return $this->transport->get('/v1/agent/health/service/id/' . rawurlencode($serviceId), $options);
    }

    public function checks(array $options = []): array
    {
        return $this->transport->get('/v1/agent/checks', $options);
    }

    public function services(array $options = []): array
    {
        return $this->transport->get('/v1/agent/services', $options);
    }

    /**
     * 按 ServiceID 读取本 agent 上单个服务的注册定义（GET /v1/agent/service/:service_id）。
     * 与 services() 的区别：这里是列表 vs 单个实例。
     *
     * $options 原样作 query 透传。上游该 handler 只消费 index / wait / hash
     * （支持阻塞查询，响应体额外带 ContentHash）。注意它**不解析 filter**，
     * 与 checks() / services() 不同，传 filter 会被静默忽略。
     * 服务不存在时 404 由传输层抛异常。
     */
    public function service(string $serviceId, array $options = []): array
    {
        return $this->transport->get('/v1/agent/service/' . rawurlencode($serviceId), $options);
    }

    public function registerService(array $service): array
    {
        return $this->transport->put('/v1/agent/service/register', $service);
    }

    public function deregisterService(string $serviceId): void
    {
        $this->transport->put('/v1/agent/service/deregister/' . rawurlencode($serviceId));
    }

    public function enableMaintenance(string $serviceId, string $reason = ''): void
    {
        $this->transport->put('/v1/agent/service/maintenance/' . rawurlencode($serviceId), [], $this->maintenanceQuery(true, $reason));
    }

    public function disableMaintenance(string $serviceId): void
    {
        $this->transport->put('/v1/agent/service/maintenance/' . rawurlencode($serviceId), [], $this->maintenanceQuery(false, ''));
    }

    public function checkPass(string $checkId, string $note = ''): void
    {
        $this->transport->putRaw('/v1/agent/check/pass/' . rawurlencode($checkId), '', $this->noteQuery($note));
    }

    public function checkFail(string $checkId, string $note = ''): void
    {
        $this->transport->putRaw('/v1/agent/check/fail/' . rawurlencode($checkId), '', $this->noteQuery($note));
    }

    public function checkWarn(string $checkId, string $note = ''): void
    {
        $this->transport->putRaw('/v1/agent/check/warn/' . rawurlencode($checkId), '', $this->noteQuery($note));
    }

    /**
     * Consul's check TTL endpoints read the note from the query string,
     * not the request body.
     */
    private function noteQuery(string $note): array
    {
        return $note !== '' ? ['note' => $note] : [];
    }

    public function checkRegister(array $check): void
    {
        $this->transport->put('/v1/agent/check/register', $check);
    }

    public function checkDeregister(string $checkId): void
    {
        $this->transport->put('/v1/agent/check/deregister/' . rawurlencode($checkId));
    }

    /**
     * 更新已注册 check 的状态与输出（PUT /v1/agent/check/update/:check_id）。
     *
     * 与 checkRegister() 不同：这不会重新注册检查，只改已有 check 的状态，
     * 所以检查定义（Interval / HTTP / TTL 等）不受影响；check 不存在时 404。
     *
     * $options 即请求体（该 handler 不读 query），上游只认两个键：
     *   Status  passing / warning / critical，其余值服务端 400
     *   Output  展示给运维的输出（与 check 自身的 note 字段不同，无长度上限保护由服务端裁剪）
     * check ID 只走路径，不需要放进 body。
     */
    public function checkUpdate(string $checkId, array $options = []): array
    {
        return $this->transport->put('/v1/agent/check/update/' . rawurlencode($checkId), $options);
    }

    /** @deprecated Use checkPass() instead. */
    public function ttlCheckPass(string $checkId, string $note = ''): void
    {
        $this->checkPass($checkId, $note);
    }

    /** @deprecated Use checkFail() instead. */
    public function ttlCheckFail(string $checkId, string $note = ''): void
    {
        $this->checkFail($checkId, $note);
    }

    /** @deprecated Use checkWarn() instead. */
    public function ttlCheckWarn(string $checkId, string $note = ''): void
    {
        $this->checkWarn($checkId, $note);
    }
}
