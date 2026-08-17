<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Service;

use Erikwang2013\Consul\Api\Agent;

class Registry
{
    private Agent $agent;

    public function __construct(Agent $agent)
    {
        $this->agent = $agent;
    }

    public function register(string $name, string $address, int $port, array $options = []): void
    {
        $service = [
            'Name'    => $name,
            'Address' => $address,
            'Port'    => $port,
        ];

        if (isset($options['id']))   $service['ID'] = $options['id'];
        if (isset($options['tags'])) $service['Tags'] = $options['tags'];
        if (isset($options['meta'])) $service['Meta'] = $options['meta'];

        $payload = array_merge($service, $this->buildCheck($options));

        $this->agent->registerService($payload);
    }

    public function heartbeat(string $serviceId, string $note = ''): void
    {
        $this->agent->checkPass("service:{$serviceId}", $note);
    }

    public function heartbeatFail(string $serviceId, string $note = ''): void
    {
        $this->agent->checkFail("service:{$serviceId}", $note);
    }

    public function deregister(string $serviceId): void
    {
        $this->agent->deregisterService($serviceId);
    }

    private function buildCheck(array $options): array
    {
        if (!isset($options['check'])) {
            return [];
        }

        $check = $options['check'];
        $payload = [];

        if (isset($check['ttl'])) {
            $payload['Check'] = [
                'TTL' => $check['ttl'],
            ];
            if (isset($check['deregister_critical_service_after'])) {
                $payload['Check']['DeregisterCriticalServiceAfter'] =
                    $check['deregister_critical_service_after'];
            }
        } elseif (isset($check['http'])) {
            $payload['Check'] = [
                'HTTP'     => $check['http'],
                'Interval' => $check['interval'] ?? '10s',
            ];
        } elseif (isset($check['tcp'])) {
            $payload['Check'] = [
                'TCP'      => $check['tcp'],
                'Interval' => $check['interval'] ?? '10s',
            ];
        } elseif (isset($check['grpc'])) {
            $payload['Check'] = [
                'GRPC'     => $check['grpc'],
                'Interval' => $check['interval'] ?? '10s',
            ];
        } else {
            throw new \InvalidArgumentException('Unsupported check type: must be one of ttl, http, tcp, grpc');
        }

        if (isset($check['timeout'])) {
            $payload['Check']['Timeout'] = $check['timeout'];
        }

        return $payload;
    }
}
