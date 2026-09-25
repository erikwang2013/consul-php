<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;

/**
 * 配置项 API（/v1/config）。
 *
 * 这是 Consul 1.8+ 起管理 mesh 相关能力的统一入口：网关（ingress-gateway /
 * terminating-gateway）、服务默认值与分流（service-defaults / service-resolver /
 * service-router / service-splitter）、服务意图（service-intentions）、
 * 导出服务（exported-services）、网格配置（mesh）等，都是「配置项」。
 * 做 Connect 服务网格、网关或跨集群访问控制时，这个模块是必需的。
 *
 * 配置项对象至少含 `Kind` 与 `Name` 两个字段，其余字段随 Kind 而异，本模块原样透传。
 *
 * 注意：`service-intentions` 配置项已取代旧的 /v1/connect/intentions 写接口
 * （后者虽在但已标记 deprecated），新代码建议走本模块。
 */
class ConfigEntry
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
     * 创建或更新配置项（upsert）。
     *
     * @param array $entry 完整配置项，必须包含 Kind 与 Name，例如
     *                     ['Kind' => 'service-defaults', 'Name' => 'web', 'Protocol' => 'http']
     * @param array $options 仅支持 cas / dc / ns / partition；带 cas 时该值需为期望的 ModifyIndex
     */
    public function set(array $entry, array $options = []): array
    {
        return $this->transport->put('/v1/config', $entry, $this->optionsQuery($options));
    }

    /**
     * 读取单个配置项。不存在时 Consul 返回 404，传输层抛 NotFoundException。
     */
    public function get(string $kind, string $name, array $options = []): array
    {
        return $this->transport->get(
            '/v1/config/' . rawurlencode($kind) . '/' . rawurlencode($name),
            $this->optionsQuery($options)
        );
    }

    /**
     * 列出配置项。$kind 为 null 时返回全部 Kind 的配置项。
     */
    public function list(?string $kind = null, array $options = []): array
    {
        $path = $kind === null ? '/v1/config' : '/v1/config/' . rawurlencode($kind);
        return $this->transport->get($path, $this->optionsQuery($options));
    }

    /**
     * 删除配置项。
     *
     * 无 cas 时 Consul 返回空对象（PHP 侧为 []）；带 cas 时返回 bool 表示是否删除成功，
     * 故此处统一按 array 返回，调用方按需转型。
     */
    public function delete(string $kind, string $name, array $options = []): array
    {
        return $this->transport->delete(
            '/v1/config/' . rawurlencode($kind) . '/' . rawurlencode($name),
            $this->optionsQuery($options)
        );
    }

    private function optionsQuery(array $options): array
    {
        return array_intersect_key($options, array_flip(['dc', 'ns', 'partition', 'cas']));
    }
}
