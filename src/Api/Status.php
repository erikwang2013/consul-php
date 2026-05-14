<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

class Status
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function leader(): string
    {
        $result = $this->transport->get('/v1/status/leader');
        return $result['body'] ?? '';
    }

    public function peers(): array
    {
        $result = $this->transport->get('/v1/status/peers');
        return $result['body'] ?? $result;
    }
}
