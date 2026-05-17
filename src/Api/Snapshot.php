<?php

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;

class Snapshot
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function save(array $options = []): string
    {
        $query = [];
        if (isset($options['dc'])) {
            $query['dc'] = $options['dc'];
        }
        if (isset($options['stale'])) {
            $query['stale'] = 'true';
        }
        return $this->transport->getRaw('/v1/snapshot', $query);
    }

    public function restore(string $snapshot, array $options = []): void
    {
        $query = [];
        if (isset($options['dc'])) {
            $query['dc'] = $options['dc'];
        }
        $this->transport->put('/v1/snapshot', ['body' => $snapshot], $query);
    }
}
