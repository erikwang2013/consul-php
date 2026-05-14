<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

class Health
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function node(string $node, array $options = []): array
    {
        return $this->transport->get("/v1/health/node/{$node}", $this->optionsQuery($options));
    }

    public function checks(string $service, array $options = []): array
    {
        return $this->transport->get("/v1/health/checks/{$service}", $this->optionsQuery($options));
    }

    public function service(string $service, array $options = []): array
    {
        return $this->transport->get("/v1/health/service/{$service}", $this->optionsQuery($options));
    }

    public function connect(string $service, array $options = []): array
    {
        return $this->transport->get("/v1/health/connect/{$service}", $this->optionsQuery($options));
    }

    public function state(string $state, array $options = []): array
    {
        return $this->transport->get("/v1/health/state/{$state}", $this->optionsQuery($options));
    }

    public function ingress(string $service, array $options = []): array
    {
        return $this->transport->get("/v1/health/ingress/{$service}", $this->optionsQuery($options));
    }

    private function optionsQuery(array $options): array
    {
        $query = [];
        foreach (['dc', 'ns', 'filter', 'index', 'wait', 'passing', 'near', 'node_meta'] as $key) {
            if (isset($options[$key])) {
                $k = $key === 'node_meta' ? 'node-meta' : $key;
                $query[$k] = $options[$key];
            }
        }
        return $query;
    }
}
