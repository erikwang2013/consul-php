<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Acl;
use Erikwang2013\Consul\Exception\ClientException;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class AclTest extends TestCase
{
    private $transport;
    private Acl $acl;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->acl = new Acl($this->transport);
    }

    public function testBootstrap(): void
    {
        $this->transport->method('put')
            ->with('/v1/acl/bootstrap')
            ->willReturn(['ID' => 'master-token']);

        $this->assertSame('master-token', $this->acl->bootstrap()['ID']);
    }

    public function testBootstrapPropagatesTransportError(): void
    {
        $this->transport->method('put')->willThrowException(new ClientException('down'));

        $this->expectException(ClientException::class);
        $this->acl->bootstrap();
    }

    public function testReplication(): void
    {
        $this->transport->method('get')
            ->with('/v1/acl/replication')
            ->willReturn(['Enabled' => true, 'ReplicatedIndex' => 42]);

        $result = $this->acl->replication();

        $this->assertTrue($result['Enabled']);
        $this->assertSame(42, $result['ReplicatedIndex']);
    }

    public function testTranslate(): void
    {
        $this->transport->method('get')
            ->with('/v1/acl/rules/translate/abc-123')
            ->willReturn(['body' => 'node "" { policy = "read" }']);

        $this->assertStringContainsString('policy = "read"', $this->acl->translate('abc-123')['body']);
    }

    public function testTranslateEncodesAccessorId(): void
    {
        $this->transport->method('get')
            ->with('/v1/acl/rules/translate/a%20b%2Fc')
            ->willReturn(['body' => '']);

        $this->assertSame('', $this->acl->translate('a b/c')['body']);
    }

    public function testTokenList(): void
    {
        $this->transport->method('get')
            ->with('/v1/acl/tokens')
            ->willReturn([['AccessorID' => 'abc']]);

        $this->assertCount(1, $this->acl->tokenList());
    }

    public function testTokenCreate(): void
    {
        $this->transport->method('put')
            ->with('/v1/acl/token', ['Description' => 'test'])
            ->willReturn(['AccessorID' => 'abc']);

        $this->assertSame('abc', $this->acl->tokenCreate(['Description' => 'test'])['AccessorID']);
    }

    public function testTokenRead(): void
    {
        $this->transport->method('get')
            ->with('/v1/acl/token/abc')
            ->willReturn(['AccessorID' => 'abc', 'Description' => 'svc']);

        $this->assertSame('svc', $this->acl->tokenRead('abc')['Description']);
    }

    public function testTokenUpdate(): void
    {
        $this->transport->method('put')
            ->with('/v1/acl/token/abc', ['Description' => 'renamed'])
            ->willReturn(['AccessorID' => 'abc', 'Description' => 'renamed']);

        $this->assertSame('renamed', $this->acl->tokenUpdate('abc', ['Description' => 'renamed'])['Description']);
    }

    public function testTokenDelete(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/acl/token/abc');

        $this->acl->tokenDelete('abc');
    }

    public function testTokenDeleteEncodesAccessorId(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/acl/token/a%2Fb%20c');

        $this->acl->tokenDelete('a/b c');
    }

    public function testTokenClone(): void
    {
        $this->transport->method('put')
            ->with('/v1/acl/token/abc/clone')
            ->willReturn(['AccessorID' => 'clone-1']);

        $this->assertSame('clone-1', $this->acl->tokenClone('abc')['AccessorID']);
    }

    public function testRoleList(): void
    {
        $this->transport->method('get')
            ->with('/v1/acl/roles')
            ->willReturn([['ID' => 'role-1']]);

        $this->assertSame('role-1', $this->acl->roleList()[0]['ID']);
    }

    public function testRoleCreate(): void
    {
        $this->transport->method('put')
            ->with('/v1/acl/role', ['Name' => 'web'])
            ->willReturn(['ID' => 'role-1']);

        $this->assertSame('role-1', $this->acl->roleCreate(['Name' => 'web'])['ID']);
    }

    public function testRoleRead(): void
    {
        $this->transport->method('get')
            ->with('/v1/acl/role/role-1')
            ->willReturn(['ID' => 'role-1', 'Name' => 'web']);

        $this->assertSame('web', $this->acl->roleRead('role-1')['Name']);
    }

    public function testRoleUpdate(): void
    {
        $this->transport->method('put')
            ->with('/v1/acl/role/role-1', ['Name' => 'web2'])
            ->willReturn(['ID' => 'role-1', 'Name' => 'web2']);

        $this->assertSame('web2', $this->acl->roleUpdate('role-1', ['Name' => 'web2'])['Name']);
    }

    public function testRoleDelete(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/acl/role/role-1');

        $this->acl->roleDelete('role-1');
    }

    public function testPolicyList(): void
    {
        $this->transport->method('get')
            ->with('/v1/acl/policies')
            ->willReturn([['ID' => 'policy-1']]);

        $this->assertCount(1, $this->acl->policyList());
    }

    public function testPolicyCreate(): void
    {
        $policy = ['Name' => 'test', 'Rules' => 'node "" { policy = "read" }'];
        $this->transport->method('put')
            ->with('/v1/acl/policy', $policy)
            ->willReturn(['ID' => 'policy-1']);

        $this->assertSame('policy-1', $this->acl->policyCreate($policy)['ID']);
    }

    public function testPolicyRead(): void
    {
        $this->transport->method('get')
            ->with('/v1/acl/policy/policy-1')
            ->willReturn(['ID' => 'policy-1', 'Name' => 'test']);

        $this->assertSame('test', $this->acl->policyRead('policy-1')['Name']);
    }

    public function testPolicyUpdate(): void
    {
        $this->transport->method('put')
            ->with('/v1/acl/policy/policy-1', ['Name' => 'renamed'])
            ->willReturn(['ID' => 'policy-1', 'Name' => 'renamed']);

        $this->assertSame('renamed', $this->acl->policyUpdate('policy-1', ['Name' => 'renamed'])['Name']);
    }

    public function testPolicyDelete(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/acl/policy/policy-1');

        $this->acl->policyDelete('policy-1');
    }

    public function testAuthMethodList(): void
    {
        $this->transport->method('get')
            ->with('/v1/acl/auth-methods')
            ->willReturn([['Name' => 'kubernetes']]);

        $this->assertSame('kubernetes', $this->acl->authMethodList()[0]['Name']);
    }

    public function testAuthMethodCreate(): void
    {
        $this->transport->method('put')
            ->with('/v1/acl/auth-method', ['Name' => 'kubernetes'])
            ->willReturn(['Name' => 'kubernetes']);

        $this->assertSame('kubernetes', $this->acl->authMethodCreate(['Name' => 'kubernetes'])['Name']);
    }

    public function testAuthMethodRead(): void
    {
        $this->transport->method('get')
            ->with('/v1/acl/auth-method/kubernetes')
            ->willReturn(['Name' => 'kubernetes', 'Type' => 'kubernetes']);

        $this->assertSame('kubernetes', $this->acl->authMethodRead('kubernetes')['Name']);
    }

    public function testAuthMethodReadEncodesName(): void
    {
        $this->transport->method('get')
            ->with('/v1/acl/auth-method/a%20b')
            ->willReturn([]);

        $this->assertSame([], $this->acl->authMethodRead('a b'));
    }

    public function testAuthMethodUpdate(): void
    {
        $this->transport->method('put')
            ->with('/v1/acl/auth-method/kubernetes', ['Type' => 'oidc'])
            ->willReturn(['Name' => 'kubernetes', 'Type' => 'oidc']);

        $this->assertSame('oidc', $this->acl->authMethodUpdate('kubernetes', ['Type' => 'oidc'])['Type']);
    }

    public function testAuthMethodDelete(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/acl/auth-method/kubernetes');

        $this->acl->authMethodDelete('kubernetes');
    }

    public function testLogin(): void
    {
        $this->transport->method('post')
            ->with('/v1/acl/login', ['AuthMethod' => 'kubernetes', 'BearerToken' => 'tok'])
            ->willReturn(['AccessorID' => 'abc']);

        $this->assertSame('abc', $this->acl->login(['AuthMethod' => 'kubernetes', 'BearerToken' => 'tok'])['AccessorID']);
    }

    public function testLogout(): void
    {
        $this->transport->expects($this->once())
            ->method('post')
            ->with('/v1/acl/logout');

        $this->acl->logout();
    }
}
