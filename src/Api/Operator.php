<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

class Operator
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function raftConfig(): array
    {
        return $this->transport->get('/v1/operator/raft/configuration');
    }

    public function raftPeer(string $address): void
    {
        $this->transport->delete('/v1/operator/raft/peer', ['address' => $address]);
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

    public function keyring(string $method, array $options = []): array
    {
        $query = [];
        if (isset($options['relay'])) {
            $query['relay'] = $options['relay'];
        }
        if (isset($options['local'])) {
            $query['local'] = $options['local'];
        }

        if ($method === 'list') {
            return $this->transport->get('/v1/operator/keyring', $query);
        }
        $body = ['Key' => $options['key']];
        if ($method === 'install') {
            return $this->transport->post('/v1/operator/keyring', $body, $query);
        }
        if ($method === 'use') {
            return $this->transport->put('/v1/operator/keyring', $body, $query);
        }
        return $this->transport->delete('/v1/operator/keyring', array_merge($query, $body));
    }
}
