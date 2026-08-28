<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;

class Event
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function fire(string $name, string $payload = '', array $options = []): array
    {
        $body = ['Name' => $name];
        if ($payload !== '') {
            $body['Payload'] = base64_encode($payload);
        }
        $query = array_intersect_key($options, array_flip(['dc', 'node', 'service', 'tag']));
        return $this->transport->put('/v1/event/fire/' . rawurlencode($name), $body, $query);
    }

    public function list(array $options = []): array
    {
        return $this->transport->get('/v1/event/list', array_intersect_key($options, array_flip(['name'])));
    }
}
