<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;

class Health
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

    public function node(string $node, array $options = []): array
    {
        return $this->transport->get('/v1/health/node/' . rawurlencode($node), $this->optionsQuery($options));
    }

    public function checks(string $service, array $options = []): array
    {
        return $this->transport->get('/v1/health/checks/' . rawurlencode($service), $this->optionsQuery($options));
    }

    public function service(string $service, array $options = []): array
    {
        return $this->transport->get('/v1/health/service/' . rawurlencode($service), $this->optionsQuery($options));
    }

    public function connect(string $service, array $options = []): array
    {
        return $this->transport->get('/v1/health/connect/' . rawurlencode($service), $this->optionsQuery($options));
    }

    public function state(string $state, array $options = []): array
    {
        return $this->transport->get('/v1/health/state/' . rawurlencode($state), $this->optionsQuery($options));
    }

    public function ingress(string $service, array $options = []): array
    {
        return $this->transport->get('/v1/health/ingress/' . rawurlencode($service), $this->optionsQuery($options));
    }

    private function optionsQuery(array $options): array
    {
        $query = array_intersect_key($options, array_flip(['dc', 'ns', 'filter', 'index', 'wait', 'passing', 'near', 'node_meta']));
        if (isset($query['node_meta'])) {
            $query['node-meta'] = $query['node_meta'];
            unset($query['node_meta']);
        }
        return $query;
    }
}
