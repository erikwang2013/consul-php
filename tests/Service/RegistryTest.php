<?php

namespace Erikwang2013\Consul\Tests\Service;

use Erikwang2013\Consul\Api\Agent;
use Erikwang2013\Consul\Service\Registry;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
    private $agent;
    private Registry $registry;

    protected function setUp(): void
    {
        $this->agent = $this->createMock(Agent::class);
        $this->registry = new Registry($this->agent);
    }

    public function testRegisterWithTtlCheck(): void
    {
        $this->agent->expects($this->once())
            ->method('registerService')
            ->with([
                'Name'    => 'user-service',
                'Address' => '10.0.0.1',
                'Port'    => 8080,
                'ID'      => 'user-service-1',
                'Tags'    => ['v1'],
                'Check'   => ['TTL' => '30s'],
            ]);

        $this->registry->register('user-service', '10.0.0.1', 8080, [
            'id'    => 'user-service-1',
            'tags'  => ['v1'],
            'check' => ['ttl' => '30s'],
        ]);
    }

    public function testRegisterWithHttpCheck(): void
    {
        $this->agent->expects($this->once())
            ->method('registerService')
            ->with([
                'Name'    => 'web',
                'Address' => '10.0.0.2',
                'Port'    => 80,
                'Check'   => ['HTTP' => 'http://10.0.0.2:80/health', 'Interval' => '10s'],
            ]);

        $this->registry->register('web', '10.0.0.2', 80, [
            'check' => ['http' => 'http://10.0.0.2:80/health', 'interval' => '10s'],
        ]);
    }

    public function testHeartbeat(): void
    {
        $this->agent->expects($this->once())
            ->method('ttlCheckPass')
            ->with('service:web-1', '');

        $this->registry->heartbeat('web-1');
    }

    public function testHeartbeatFail(): void
    {
        $this->agent->expects($this->once())
            ->method('ttlCheckFail')
            ->with('service:web-1', 'manual fail');

        $this->registry->heartbeatFail('web-1', 'manual fail');
    }

    public function testDeregister(): void
    {
        $this->agent->expects($this->once())
            ->method('deregisterService')
            ->with('web-1');

        $this->registry->deregister('web-1');
    }
}
