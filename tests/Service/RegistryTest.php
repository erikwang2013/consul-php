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
            ->method('checkPass')
            ->with('service:web-1', '');

        $this->registry->heartbeat('web-1');
    }

    public function testHeartbeatFail(): void
    {
        $this->agent->expects($this->once())
            ->method('checkFail')
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

    public function testRegisterWithTcpCheck(): void
    {
        $this->agent->expects($this->once())
            ->method('registerService')
            ->with([
                'Name'    => 'db',
                'Address' => '10.0.0.3',
                'Port'    => 3306,
                'Check'   => ['TCP' => '10.0.0.3:3306', 'Interval' => '15s'],
            ]);

        $this->registry->register('db', '10.0.0.3', 3306, [
            'check' => ['tcp' => '10.0.0.3:3306', 'interval' => '15s'],
        ]);
    }

    public function testRegisterWithGrpcCheck(): void
    {
        $this->agent->expects($this->once())
            ->method('registerService')
            ->with([
                'Name'    => 'rpc',
                'Address' => '10.0.0.4',
                'Port'    => 9000,
                'Check'   => ['GRPC' => '10.0.0.4:9000', 'Interval' => '5s'],
            ]);

        $this->registry->register('rpc', '10.0.0.4', 9000, [
            'check' => ['grpc' => '10.0.0.4:9000', 'interval' => '5s'],
        ]);
    }

    public function testRegisterWithTtlCheckTimeoutAndDeregisterAfterCritical(): void
    {
        $this->agent->expects($this->once())
            ->method('registerService')
            ->with([
                'Name'    => 'svc',
                'Address' => '10.0.0.5',
                'Port'    => 80,
                'Check'   => [
                    'TTL'                         => '30s',
                    'DeregisterCriticalServiceAfter' => '1h',
                    'Timeout'                     => '5s',
                ],
            ]);

        $this->registry->register('svc', '10.0.0.5', 80, [
            'check' => [
                'ttl'                             => '30s',
                'deregister_critical_service_after' => '1h',
                'timeout'                         => '5s',
            ],
        ]);
    }

    public function testRegisterWithMeta(): void
    {
        $this->agent->expects($this->once())
            ->method('registerService')
            ->with([
                'Name'    => 'svc',
                'Address' => '10.0.0.6',
                'Port'    => 80,
                'Meta'    => ['env' => 'prod'],
            ]);

        $this->registry->register('svc', '10.0.0.6', 80, [
            'meta' => ['env' => 'prod'],
        ]);
    }

    public function testRegisterWithoutCheckAndOptions(): void
    {
        $this->agent->expects($this->once())
            ->method('registerService')
            ->with([
                'Name'    => 'plain',
                'Address' => '10.0.0.7',
                'Port'    => 81,
            ]);

        $this->registry->register('plain', '10.0.0.7', 81);
    }

    public function testRegisterWithUnsupportedCheckTypeThrows(): void
    {
        $this->agent->expects($this->never())->method('registerService');

        $this->expectException(\InvalidArgumentException::class);
        $this->registry->register('svc', '10.0.0.8', 80, [
            'check' => ['docker' => 'container://x'],
        ]);
    }

    public function testRegisterWithEmptyCheckArrayThrows(): void
    {
        $this->agent->expects($this->never())->method('registerService');

        $this->expectException(\InvalidArgumentException::class);
        $this->registry->register('svc', '10.0.0.9', 80, ['check' => []]);
    }

    public function testHeartbeatWithNote(): void
    {
        $this->agent->expects($this->once())
            ->method('checkPass')
            ->with('service:web-1', 'ok note');

        $this->registry->heartbeat('web-1', 'ok note');
    }
}
