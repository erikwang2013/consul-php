<?php

declare(strict_types=1);

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
        $query = array_intersect_key($options, array_flip(['dc']));
        if (!empty($options['stale'])) {
            $query['stale'] = 'true';
        }
        return $this->transport->getRaw('/v1/snapshot', $query);
    }

    public function restore(string $snapshot, array $options = []): void
    {
        $this->transport->putRaw('/v1/snapshot', $snapshot, array_intersect_key($options, array_flip(['dc'])));
    }
}
