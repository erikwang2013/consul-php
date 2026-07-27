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
        $query = [];
        if (isset($options['dc'])) {
            $query['dc'] = $options['dc'];
        }
        if (isset($options['node'])) {
            $query['node'] = $options['node'];
        }
        if (isset($options['service'])) {
            $query['service'] = $options['service'];
        }
        if (isset($options['tag'])) {
            $query['tag'] = $options['tag'];
        }
        return $this->transport->put('/v1/event/fire/' . rawurlencode($name), $body, $query);
    }

    public function list(array $options = []): array
    {
        $query = [];
        if (isset($options['name'])) {
            $query['name'] = $options['name'];
        }
        return $this->transport->get('/v1/event/list', $query);
    }
}
