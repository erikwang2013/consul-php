<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

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
        return $this->transport->get("/v1/coordinate/node/{$node}", $options);
    }
}
