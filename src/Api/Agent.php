<?php

declare(strict_types=1);

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
        $this->transport->put('/v1/agent/maintenance', [], $this->maintenanceQuery($enable, $reason));
    }

    private function maintenanceQuery(bool $enable, string $reason): array
    {
        $query = ['enable' => $enable ? 'true' : 'false'];
        if ($reason !== '') {
            $query['reason'] = $reason;
        }
        return $query;
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
        $this->transport->put('/v1/agent/service/maintenance/' . rawurlencode($serviceId), [], $this->maintenanceQuery(true, $reason));
    }

    public function disableMaintenance(string $serviceId): void
    {
        $this->transport->put('/v1/agent/service/maintenance/' . rawurlencode($serviceId), [], $this->maintenanceQuery(false, ''));
    }

    public function checkPass(string $checkId, string $note = ''): void
    {
        $this->transport->putRaw('/v1/agent/check/pass/' . rawurlencode($checkId), '', $this->noteQuery($note));
    }

    public function checkFail(string $checkId, string $note = ''): void
    {
        $this->transport->putRaw('/v1/agent/check/fail/' . rawurlencode($checkId), '', $this->noteQuery($note));
    }

    public function checkWarn(string $checkId, string $note = ''): void
    {
        $this->transport->putRaw('/v1/agent/check/warn/' . rawurlencode($checkId), '', $this->noteQuery($note));
    }

    /**
     * Consul's check TTL endpoints read the note from the query string,
     * not the request body.
     */
    private function noteQuery(string $note): array
    {
        return $note !== '' ? ['note' => $note] : [];
    }

    public function checkRegister(array $check): void
    {
        $this->transport->put('/v1/agent/check/register', $check);
    }

    public function checkDeregister(string $checkId): void
    {
        $this->transport->put('/v1/agent/check/deregister/' . rawurlencode($checkId));
    }

    /** @deprecated Use checkPass() instead. */
    public function ttlCheckPass(string $checkId, string $note = ''): void
    {
        $this->checkPass($checkId, $note);
    }

    /** @deprecated Use checkFail() instead. */
    public function ttlCheckFail(string $checkId, string $note = ''): void
    {
        $this->checkFail($checkId, $note);
    }

    /** @deprecated Use checkWarn() instead. */
    public function ttlCheckWarn(string $checkId, string $note = ''): void
    {
        $this->checkWarn($checkId, $note);
    }
}
