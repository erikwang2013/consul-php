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

    /**
     * 事件列表。上游 event_endpoint.go 的 EventList 走 parseQuery：dc / filter / name，
     * 外加 parseBlockingQuery 的 index / wait —— 也就是说 events 同样支持阻塞查询。
     */
    public function list(array $options = []): array
    {
        return $this->transport->get(
            '/v1/event/list',
            array_intersect_key($options, array_flip(['name', 'dc', 'filter', 'index', 'wait']))
        );
    }
}
