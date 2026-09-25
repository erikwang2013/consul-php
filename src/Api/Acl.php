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

    /*
     * 已移除：v1.14（含）及更早曾有 GET /v1/acl/rules/translate/:accessorId 与
     * POST /v1/acl/rules/translate（handler 都是 ACLLegacy），但 **v1.15.0 起两条路由一并删除**
     * ——v1.15 / v1.19 / v1.20 / main 的 agent/http_register.go 里都没有，ACLLegacy handler
     * 本身也已从 agent/acl_endpoint.go 移除，上游 Go 客户端的 RulesTranslate/RulesTranslateToken
     * 更是改成直接返回错误、不再发请求。该能力只服务于已退役的旧版 ACL 语法迁移，
     * 对现代 Consul 恒 404，故不再提供。
     *
     * 不要再按「翻译任意 rules 文本」的直觉补回 POST 变体——它和 GET 变体是同一次删除掉的。
     */

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

    /*
     * 绑定规则（binding rule）：把 auth method 校验通过的外部身份（JWT claim、证书 CN 等）
     * 映射到本地 role/policy。
     *
     * 这条链是 auth-method -> binding-rule -> role：只用 authMethodCreate() 登录，
     * 拿到的 token 不带任何权限；必须有绑定规则才能把 IdP 身份换成可用的 ACL 权限。
     * 缺了本组方法，外部身份接入 Consul ACL 的链路是断的。
     *
     * 字段：AuthMethod（必填，关联的 auth method 名）、BindType（必填，role 或 service）、
     * BindName（目标 role 名或服务名模板）、Selector（匹配外部身份的表达式）、
     * Description。BindName 支持 ${value.xxx} 之类的插值模板。
     */
    public function bindingRuleList(): array { return $this->transport->get('/v1/acl/binding-rules'); }
    public function bindingRuleCreate(array $rule): array { return $this->transport->put('/v1/acl/binding-rule', $rule); }
    public function bindingRuleRead(string $id): array { return $this->transport->get('/v1/acl/binding-rule/' . rawurlencode($id)); }
    public function bindingRuleUpdate(string $id, array $rule): array { return $this->transport->put('/v1/acl/binding-rule/' . rawurlencode($id), $rule); }
    public function bindingRuleDelete(string $id): void { $this->transport->delete('/v1/acl/binding-rule/' . rawurlencode($id)); }

    public function login(array $auth): array { return $this->transport->post('/v1/acl/login', $auth); }
    public function logout(): void { $this->transport->post('/v1/acl/logout'); }
}
