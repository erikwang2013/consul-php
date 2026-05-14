<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Acl;
use Erikwang\Consul\Transport\TransportInterface;
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

        $result = $this->acl->bootstrap();

        $this->assertSame('master-token', $result['ID']);
    }

    public function testTokenCreate(): void
    {
        $this->transport->method('put')
            ->with('/v1/acl/token', ['Description' => 'test'])
            ->willReturn(['AccessorID' => 'abc']);

        $result = $this->acl->tokenCreate(['Description' => 'test']);

        $this->assertSame('abc', $result['AccessorID']);
    }

    public function testTokenList(): void
    {
        $this->transport->method('get')
            ->with('/v1/acl/tokens')
            ->willReturn([['AccessorID' => 'abc']]);

        $result = $this->acl->tokenList();

        $this->assertCount(1, $result);
    }

    public function testTokenDelete(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/acl/token/abc');

        $this->acl->tokenDelete('abc');
    }

    public function testPolicyCreate(): void
    {
        $this->transport->method('put')
            ->with('/v1/acl/policy', ['Name' => 'test', 'Rules' => 'node "" { policy = "read" }'])
            ->willReturn(['ID' => 'policy-1']);

        $result = $this->acl->policyCreate(['Name' => 'test', 'Rules' => 'node "" { policy = "read" }']);

        $this->assertSame('policy-1', $result['ID']);
    }

    public function testLogin(): void
    {
        $this->transport->method('post')
            ->with('/v1/acl/login', ['AuthMethod' => 'kubernetes', 'BearerToken' => 'tok'])
            ->willReturn(['AccessorID' => 'abc']);

        $result = $this->acl->login(['AuthMethod' => 'kubernetes', 'BearerToken' => 'tok']);

        $this->assertSame('abc', $result['AccessorID']);
    }
}
