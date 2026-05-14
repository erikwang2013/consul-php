<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

class Session
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function create(array $options = []): array
    {
        return $this->transport->put('/v1/session/create', $options);
    }

    public function destroy(string $sessionId, array $options = []): void
    {
        $this->transport->put("/v1/session/destroy/{$sessionId}", [], $options);
    }

    public function info(string $sessionId, array $options = []): array
    {
        return $this->transport->get("/v1/session/info/{$sessionId}", $options);
    }

    public function node(string $node, array $options = []): array
    {
        return $this->transport->get("/v1/session/node/{$node}", $options);
    }

    public function all(array $options = []): array
    {
        return $this->transport->get('/v1/session/list', $options);
    }

    public function renew(string $sessionId, array $options = []): array
    {
        return $this->transport->put("/v1/session/renew/{$sessionId}", [], $options);
    }
}
