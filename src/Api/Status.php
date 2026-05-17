<?php

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;

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
        return (string)($result['body'] ?? '');
    }

    public function peers(): array
    {
        return $this->transport->get('/v1/status/peers');
    }
}
