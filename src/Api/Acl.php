<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;

class Acl
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function bootstrap(): array
    {
        return $this->transport->put('/v1/acl/bootstrap');
    }

    public function replication(): array
    {
        return $this->transport->get('/v1/acl/replication');
    }

    public function translate(string $accessorId): array
    {
        return $this->transport->get('/v1/acl/rules/translate/' . rawurlencode($accessorId));
    }

    public function tokenList(): array
    {
        return $this->transport->get('/v1/acl/tokens');
    }

    public function tokenCreate(array $token): array
    {
        return $this->transport->put('/v1/acl/token', $token);
    }

    public function tokenRead(string $accessorId): array
    {
        return $this->transport->get('/v1/acl/token/' . rawurlencode($accessorId));
    }

    public function tokenUpdate(string $accessorId, array $token): array
    {
        return $this->transport->put('/v1/acl/token/' . rawurlencode($accessorId), $token);
    }

    public function tokenDelete(string $accessorId): void
    {
        $this->transport->delete('/v1/acl/token/' . rawurlencode($accessorId));
    }

    public function tokenClone(string $accessorId): array
    {
        return $this->transport->put('/v1/acl/token/' . rawurlencode($accessorId) . '/clone');
    }

    public function roleList(): array { return $this->transport->get('/v1/acl/roles'); }
    public function roleCreate(array $role): array { return $this->transport->put('/v1/acl/role', $role); }
    public function roleRead(string $roleId): array { return $this->transport->get('/v1/acl/role/' . rawurlencode($roleId)); }
    public function roleUpdate(string $roleId, array $role): array { return $this->transport->put('/v1/acl/role/' . rawurlencode($roleId), $role); }
    public function roleDelete(string $roleId): void { $this->transport->delete('/v1/acl/role/' . rawurlencode($roleId)); }

    public function policyList(): array { return $this->transport->get('/v1/acl/policies'); }
    public function policyCreate(array $policy): array { return $this->transport->put('/v1/acl/policy', $policy); }
    public function policyRead(string $policyId): array { return $this->transport->get('/v1/acl/policy/' . rawurlencode($policyId)); }
    public function policyUpdate(string $policyId, array $policy): array { return $this->transport->put('/v1/acl/policy/' . rawurlencode($policyId), $policy); }
    public function policyDelete(string $policyId): void { $this->transport->delete('/v1/acl/policy/' . rawurlencode($policyId)); }

    public function authMethodList(): array { return $this->transport->get('/v1/acl/auth-methods'); }
    public function authMethodCreate(array $method): array { return $this->transport->put('/v1/acl/auth-method', $method); }
    public function authMethodRead(string $name): array { return $this->transport->get('/v1/acl/auth-method/' . rawurlencode($name)); }
    public function authMethodUpdate(string $name, array $method): array { return $this->transport->put('/v1/acl/auth-method/' . rawurlencode($name), $method); }
    public function authMethodDelete(string $name): void { $this->transport->delete('/v1/acl/auth-method/' . rawurlencode($name)); }

    public function login(array $auth): array { return $this->transport->post('/v1/acl/login', $auth); }
    public function logout(): void { $this->transport->post('/v1/acl/logout'); }
}
