<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;
use InvalidArgumentException;

class Operator
{
    public const KEYRING_LIST = 'list';
    public const KEYRING_INSTALL = 'install';
    public const KEYRING_USE = 'use';
    public const KEYRING_REMOVE = 'remove';

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
        $query = array_intersect_key($options, array_flip(['relay', 'local']));

        if ($method === self::KEYRING_LIST) {
            return $this->transport->get('/v1/operator/keyring', $query);
        }

        if (!in_array($method, [self::KEYRING_INSTALL, self::KEYRING_USE, self::KEYRING_REMOVE], true)) {
            throw new InvalidArgumentException("Unknown keyring method: {$method}");
        }

        if (!isset($options['key']) || $options['key'] === '') {
            throw new InvalidArgumentException("Keyring method \"{$method}\" requires a non-empty \"key\" option");
        }
        $body = ['Key' => $options['key']];

        return match ($method) {
            self::KEYRING_INSTALL => $this->transport->post('/v1/operator/keyring', $body, $query),
            self::KEYRING_USE => $this->transport->put('/v1/operator/keyring', $body, $query),
            // Transport::delete() does not support request body; Key sent as query parameter
            self::KEYRING_REMOVE => $this->transport->delete('/v1/operator/keyring', array_merge($query, $body)),
        };
    }
}
