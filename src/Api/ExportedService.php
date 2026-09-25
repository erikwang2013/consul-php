<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;

/**
 * 跨分区 / peering 的服务可见性（/v1/exported-services、/v1/imported-services）。
 *
 * 「导出」由 exported-services 配置项声明（见 ConfigEntry 模块），决定本分区的哪些服务
 * 对哪些 peer / 分区可见；「导入」是反向视角——本分区实际能看到的外来服务。
 * 排查 peering 通了但服务解析不出来时，两边对一下就知道是没导出还是没导入。
 */
class ExportedService
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    /**
     * 列出本分区被导出的服务，上游返回 ResolvedExportedService 数组
     * （形如 [{Service, Consumers, ...}]）。**返回结构未对活集群验证。**
     */
    public function exported(): array
    {
        return $this->transport->get('/v1/exported-services');
    }

    /**
     * 列出本分区导入的服务（外来服务在本地的可见视图）。**返回结构未对活集群验证。**
     */
    public function imported(): array
    {
        return $this->transport->get('/v1/imported-services');
    }
}
