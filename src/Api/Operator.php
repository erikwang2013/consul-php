<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;
use InvalidArgumentException;

class Operator
{
    public const KEYRING_LIST = 'list';
    public const KEYRING_INSTALL = 'install';
    public const KEYRING_USE = 'use';
    public const KEYRING_REMOVE = 'remove';

    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function raftConfig(): array
    {
        return $this->transport->get('/v1/operator/raft/configuration');
    }

    /**
     * 摘除一个 Raft peer。Consul 要求 id 与 address 二选一必填，推荐用 server ID（IP 会变）：
     *   raftPeer('', ['id' => 'a1b2...'])   // 推荐
     *   raftPeer('10.0.0.3:8300')           // 按地址（兼容旧调用）
     */
    public function raftPeer(string $address = '', array $options = []): void
    {
        if ($address !== '') {
            $options['address'] = $address;
        }
        if (empty($options['id']) && empty($options['address'])) {
            throw new InvalidArgumentException('raftPeer() requires a non-empty "id" or "address"');
        }

        $this->transport->delete('/v1/operator/raft/peer', $options);
    }

    /**
     * 把 Raft leadership 移交给指定节点（地址形如 10.0.0.2:8300）。
     */
    public function raftTransferLeader(string $address): array
    {
        return $this->transport->post('/v1/operator/raft/transfer-leader', ['Address' => $address]);
    }

    public function autopilotConfig(): array
    {
        return $this->transport->get('/v1/operator/autopilot/configuration');
    }

    public function updateAutopilotConfig(array $config): void
    {
        $this->transport->put('/v1/operator/autopilot/configuration', $config);
    }

    public function autopilotHealth(): array
    {
        return $this->transport->get('/v1/operator/autopilot/health');
    }

    /**
     * Autopilot 的详细状态（当前 leader、各节点健康与投票情况）。
     */
    public function autopilotState(): array
    {
        return $this->transport->get('/v1/operator/autopilot/state');
    }

    /**
     * 密钥环管理。$options 支持 Consul 原生的 query 参数名：
     *   relay-factor  int   经过几个 server 转发（默认 3）
     *   local-only    bool  只统计本机（list），或只在本机执行（install/use/remove）
     */
    public function keyring(string $method, array $options = []): array
    {
        $query = array_intersect_key($options, array_flip(['relay-factor', 'local-only']));

        if ($method === self::KEYRING_LIST) {
            return $this->transport->get('/v1/operator/keyring', $query);
        }

        if (!in_array($method, [self::KEYRING_INSTALL, self::KEYRING_USE, self::KEYRING_REMOVE], true)) {
            throw new InvalidArgumentException("Unknown keyring method: {$method}");
        }

        if (!isset($options['key']) || $options['key'] === '') {
            throw new InvalidArgumentException("Keyring method \"{$method}\" requires a non-empty \"key\" option");
        }
        $body = ['Key' => $options['key']];

        return match ($method) {
            self::KEYRING_INSTALL => $this->transport->post('/v1/operator/keyring', $body, $query),
            self::KEYRING_USE => $this->transport->put('/v1/operator/keyring', $body, $query),
            // keyring 端点统一从 JSON body 读参数，DELETE 不带 body 会被上游 DecodeJSON 以 400 拒绝
            self::KEYRING_REMOVE => $this->transport->deleteWithBody('/v1/operator/keyring', $body, $query),
        };
    }

    /**
     * 列出全部 feature gate 及其开关状态（Consul 1.21 新增）。
     *
     * 返回 [{Name, Enabled, ...}]。**未对活集群验证**（本机无 1.21 实例）。
     */
    public function features(): array
    {
        return $this->transport->get('/v1/operator/features');
    }

    /**
     * 读取单个 feature gate。名字不存在时上游返回 404；策略尚未初始化时返回 503。
     */
    public function feature(string $name): array
    {
        return $this->transport->get('/v1/operator/feature/' . rawurlencode($name));
    }

    /**
     * 开关单个 feature gate。$payload 形如 ['Enabled' => true]。
     *
     * $options 支持 'cas'：把策略版本号当作乐观锁，版本不符时服务端拒绝本次修改，
     * 避免并发下覆盖别人的改动。返回 ['Applied' => bool, 'Feature' => [...]]。
     * **未对活集群验证**（1.21 新增端点）。
     */
    public function updateFeature(string $name, array $payload, array $options = []): array
    {
        $query = array_intersect_key($options, array_flip(['cas']));

        return $this->transport->put('/v1/operator/feature/' . rawurlencode($name), $payload, $query);
    }
}
