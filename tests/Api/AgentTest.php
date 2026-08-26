<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Agent;
use Erikwang2013\Consul\Exception\ClientException;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class AgentTest extends TestCase
{
    private $transport;
    private Agent $agent;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->agent = new Agent($this->transport);
    }

    public function testMembers(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/members', [])
            ->willReturn([['Name' => 'node1']]);

        $this->assertSame('node1', $this->agent->members()[0]['Name']);
    }

    public function testMembersWithWan(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/members', ['wan' => '1'])
            ->willReturn([['Name' => 'node2']]);

        $this->assertSame('node2', $this->agent->members(['wan' => true])[0]['Name']);
    }

    public function testMembersPropagatesTransportError(): void
    {
        $this->transport->method('get')->willThrowException(new ClientException('down'));

        $this->expectException(ClientException::class);
        $this->agent->members();
    }

    public function testSelf(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/self')
            ->willReturn(['Config' => ['NodeName' => 'node1']]);

        $this->assertSame('node1', $this->agent->self()['Config']['NodeName']);
    }

    public function testMaintenanceUsesQueryParameters(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/maintenance', [], ['enable' => 'true', 'reason' => 'upgrade']);

        $this->agent->maintenance(true, 'upgrade');
    }

    public function testMaintenanceWithoutReason(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/maintenance', [], ['enable' => 'true']);

        $this->agent->maintenance(true);
    }

    public function testMaintenanceDisableUsesQueryParameters(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/maintenance', [], ['enable' => 'false']);

        $this->agent->maintenance(false);
    }

    public function testJoin(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/join/10.0.0.1%3A8301', [], []);

        $this->agent->join('10.0.0.1:8301');
    }

    public function testJoinWithWan(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/join/10.0.0.1%3A8302', [], ['wan' => '1']);

        $this->agent->join('10.0.0.1:8302', true);
    }

    public function testJoinEncodesAddress(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/join/a%20b', [], []);

        $this->agent->join('a b');
    }

    public function testForceLeave(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/force-leave/node-9');

        $this->agent->forceLeave('node-9');
    }

    public function testChecks(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/checks')
            ->willReturn(['check1' => ['Status' => 'passing']]);

        $this->assertArrayHasKey('check1', $this->agent->checks());
    }

    public function testServices(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/services')
            ->willReturn(['web' => ['Service' => 'web', 'Port' => 80]]);

        $this->assertArrayHasKey('web', $this->agent->services());
    }

    public function testRegisterService(): void
    {
        $service = ['Name' => 'web', 'Port' => 80];
        $this->transport->method('put')
            ->with('/v1/agent/service/register', $service)
            ->willReturn([]);

        $this->assertSame([], $this->agent->registerService($service));
    }

    public function testDeregisterService(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/service/deregister/web-1');

        $this->agent->deregisterService('web-1');
    }

    public function testEnableMaintenanceUsesQueryParameters(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/service/maintenance/web-1', [], ['enable' => 'true', 'reason' => 'deploy']);

        $this->agent->enableMaintenance('web-1', 'deploy');
    }

    public function testEnableMaintenanceWithoutReason(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/service/maintenance/web-1', [], ['enable' => 'true']);

        $this->agent->enableMaintenance('web-1');
    }

    public function testDisableMaintenanceUsesQueryParameters(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/service/maintenance/web-1', [], ['enable' => 'false']);

        $this->agent->disableMaintenance('web-1');
    }

    public function testCheckPassSendsNoteAsQueryParameter(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/pass/check-1', '', ['note' => 'all good']);

        $this->agent->checkPass('check-1', 'all good');
    }

    public function testCheckPassWithoutNote(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/pass/check-1', '', []);

        $this->agent->checkPass('check-1');
    }

    public function testCheckFailSendsNoteAsQueryParameter(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/fail/check-1', '', ['note' => 'boom']);

        $this->agent->checkFail('check-1', 'boom');
    }

    public function testCheckWarnSendsNoteAsQueryParameter(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/warn/check-1', '', ['note' => 'slow']);

        $this->agent->checkWarn('check-1', 'slow');
    }

    public function testCheckPassEncodesCheckId(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/pass/svc%3Aweb', '', []);

        $this->agent->checkPass('svc:web');
    }

    public function testCheckRegister(): void
    {
        $check = ['ID' => 'chk1', 'Name' => 'http', 'TTL' => '10s'];
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/check/register', $check);

        $this->agent->checkRegister($check);
    }

    public function testCheckDeregister(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/check/deregister/chk1');

        $this->agent->checkDeregister('chk1');
    }

    public function testTtlCheckPassDelegates(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/pass/chk1', '', ['note' => 'n']);

        $this->agent->ttlCheckPass('chk1', 'n');
    }

    public function testTtlCheckFailDelegates(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/fail/chk1', '', []);

        $this->agent->ttlCheckFail('chk1');
    }

    public function testTtlCheckWarnDelegates(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/warn/chk1', '', []);

        $this->agent->ttlCheckWarn('chk1');
    }
}
