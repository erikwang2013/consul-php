<?php

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;

class Agent
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function members(array $options = []): array
    {
        $query = [];
        if (isset($options['wan'])) $query['wan'] = '1';
        return $this->transport->get('/v1/agent/members', $query);
    }

    public function self(): array
    {
        return $this->transport->get('/v1/agent/self');
    }

    public function maintenance(bool $enable, string $reason = ''): void
    {
        $this->transport->put('/v1/agent/maintenance', ['enable' => $enable, 'reason' => $reason]);
    }

    public function join(string $address, bool $wan = false): void
    {
        $query = $wan ? ['wan' => '1'] : [];
        $this->transport->put('/v1/agent/join/' . rawurlencode($address), [], $query);
    }

    public function forceLeave(string $node): void
    {
        $this->transport->put('/v1/agent/force-leave/' . rawurlencode($node));
    }

    public function checks(): array
    {
        return $this->transport->get('/v1/agent/checks');
    }

    public function services(): array
    {
        return $this->transport->get('/v1/agent/services');
    }

    public function registerService(array $service): array
    {
        return $this->transport->put('/v1/agent/service/register', $service);
    }

    public function deregisterService(string $serviceId): void
    {
        $this->transport->put('/v1/agent/service/deregister/' . rawurlencode($serviceId));
    }

    public function enableMaintenance(string $serviceId, string $reason = ''): void
    {
        $params = ['enable' => true];
        if ($reason !== '') {
            $params['reason'] = $reason;
        }
        $this->transport->put('/v1/agent/service/maintenance/' . rawurlencode($serviceId), $params);
    }

    public function disableMaintenance(string $serviceId): void
    {
        $this->transport->put('/v1/agent/service/maintenance/' . rawurlencode($serviceId), ['enable' => false]);
    }

    public function checkPass(string $checkId, string $note = ''): void
    {
        $this->transport->put('/v1/agent/check/pass/' . rawurlencode($checkId), ['note' => $note]);
    }

    public function checkFail(string $checkId, string $note = ''): void
    {
        $this->transport->put('/v1/agent/check/fail/' . rawurlencode($checkId), ['note' => $note]);
    }

    public function checkWarn(string $checkId, string $note = ''): void
    {
        $this->transport->put('/v1/agent/check/warn/' . rawurlencode($checkId), ['note' => $note]);
    }

    public function checkRegister(array $check): void
    {
        $this->transport->put('/v1/agent/check/register', $check);
    }

    public function checkDeregister(string $checkId): void
    {
        $this->transport->put('/v1/agent/check/deregister/' . rawurlencode($checkId));
    }

    public function ttlCheckPass(string $checkId, string $note = ''): void
    {
        $params = [];
        if ($note !== '') {
            $params['note'] = $note;
        }
        $this->transport->put('/v1/agent/check/pass/' . rawurlencode($checkId), $params);
    }

    public function ttlCheckFail(string $checkId, string $note = ''): void
    {
        $params = [];
        if ($note !== '') {
            $params['note'] = $note;
        }
        $this->transport->put('/v1/agent/check/fail/' . rawurlencode($checkId), $params);
    }

    public function ttlCheckWarn(string $checkId, string $note = ''): void
    {
        $params = [];
        if ($note !== '') {
            $params['note'] = $note;
        }
        $this->transport->put('/v1/agent/check/warn/' . rawurlencode($checkId), $params);
    }
}
