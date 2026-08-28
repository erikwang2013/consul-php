<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;
use InvalidArgumentException;

class Catalog
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function register(array $node, array $service, array $check = []): array
    {
        if (!isset($node['node'], $node['address'], $service['service'])) {
            throw new InvalidArgumentException('register() requires node.node, node.address and service.service');
        }

        $payload = ['Node' => $node['node'], 'Address' => $node['address']];
        if (isset($node['datacenter'])) $payload['Datacenter'] = $node['datacenter'];
        if (isset($node['meta'])) $payload['NodeMeta'] = $node['meta'];

        $payload['Service'] = [
            'Service' => $service['service'],
            'Address' => $service['address'] ?? $node['address'],
        ];
        if (array_key_exists('port', $service) && $service['port'] !== null) {
            $payload['Service']['Port'] = $service['port'];
        }
        if (isset($service['id'])) $payload['Service']['ID'] = $service['id'];
        if (isset($service['tags'])) $payload['Service']['Tags'] = $service['tags'];
        if (isset($service['meta'])) $payload['Service']['Meta'] = $service['meta'];

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

    public function nodes(array $options = []): array
    {
        return $this->transport->get('/v1/catalog/nodes', $this->optionsQuery($options));
    }

    public function services(array $options = []): array
    {
        return $this->transport->get('/v1/catalog/services', $this->optionsQuery($options));
    }

    public function service(string $service, array $options = []): array
    {
        return $this->transport->get('/v1/catalog/service/' . rawurlencode($service), $this->optionsQuery($options));
    }

    public function connect(string $service, array $options = []): array
    {
        return $this->transport->get('/v1/catalog/connect/' . rawurlencode($service), $this->optionsQuery($options));
    }

    public function node(string $node, array $options = []): array
    {
        return $this->transport->get('/v1/catalog/node/' . rawurlencode($node), $this->optionsQuery($options));
    }

    public function nodeServices(string $node, array $options = []): array
    {
        return $this->transport->get('/v1/catalog/node-services/' . rawurlencode($node), $this->optionsQuery($options));
    }

    private function optionsQuery(array $options): array
    {
        return array_intersect_key($options, array_flip(['dc', 'ns', 'filter', 'index', 'wait']));
    }
}
