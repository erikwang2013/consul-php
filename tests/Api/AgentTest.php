<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Agent;
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

        $result = $this->agent->members();

        $this->assertSame('node1', $result[0]['Name']);
    }

    public function testSelf(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/self')
            ->willReturn(['Config' => ['NodeName' => 'node1']]);

        $result = $this->agent->self();

        $this->assertSame('node1', $result['Config']['NodeName']);
    }

    public function testRegisterService(): void
    {
        $service = ['Name' => 'web', 'Port' => 80];
        $this->transport->method('put')
            ->with('/v1/agent/service/register', $service)
            ->willReturn([]);

        $result = $this->agent->registerService($service);

        $this->assertSame([], $result);
    }

    public function testDeregisterService(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/service/deregister/web-1');

        $this->agent->deregisterService('web-1');
    }

    public function testChecks(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/checks')
            ->willReturn(['check1' => ['Status' => 'passing']]);

        $result = $this->agent->checks();

        $this->assertArrayHasKey('check1', $result);
    }

    public function testServices(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/services')
            ->willReturn(['web' => ['Service' => 'web', 'Port' => 80]]);

        $result = $this->agent->services();

        $this->assertArrayHasKey('web', $result);
    }

    public function testMaintenanceUsesQueryParameters(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/maintenance', [], ['enable' => 'true', 'reason' => 'upgrade']);

        $this->agent->maintenance(true, 'upgrade');
    }

    public function testMaintenanceDisableUsesQueryParameters(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/maintenance', [], ['enable' => 'false']);

        $this->agent->maintenance(false);
    }

    public function testEnableMaintenanceUsesQueryParameters(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/service/maintenance/web-1', [], ['enable' => 'true', 'reason' => 'deploy']);

        $this->agent->enableMaintenance('web-1', 'deploy');
    }

    public function testDisableMaintenanceUsesQueryParameters(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/service/maintenance/web-1', [], ['enable' => 'false']);

        $this->agent->disableMaintenance('web-1');
    }
}
