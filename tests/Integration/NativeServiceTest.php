<?php

namespace Erikwang2013\Consul\Tests\Integration;

use Erikwang2013\Consul\Integration\Native\NativeService;
use Erikwang2013\Consul\Service\Registry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

class NativeServiceTest extends TestCase
{
    public function testStartRegistersWithGeneratedId(): void
    {
        $registry = $this->createMock(Registry::class);
        $registry->expects($this->once())
            ->method('register')
            ->with('web', '10.0.0.1', 8080, $this->callback(
                static fn (array $options): bool => $options['id'] === 'web-10.0.0.1:8080'
                    && $options['check'] === ['ttl' => '30s']
            ));

        $service = NativeService::start($registry, 'web', '10.0.0.1', 8080, ['check' => ['ttl' => '30s']]);

        $this->assertSame('web-10.0.0.1:8080', $service->serviceId());
        $service->stop();
    }

    public function testStartKeepsExplicitId(): void
    {
        $registry = $this->createMock(Registry::class);
        $registry->expects($this->once())
            ->method('register')
            ->with($this->anything(), $this->anything(), $this->anything(), $this->callback(
                static fn (array $options): bool => $options['id'] === 'web-1'
            ));

        NativeService::start($registry, 'web', '10.0.0.1', 8080, ['id' => 'web-1'])->stop();
    }

    public function testStopDeregistersOnceAndIsIdempotent(): void
    {
        $registry = $this->createMock(Registry::class);
        $registry->expects($this->once())->method('deregister')->with('web-1');

        $service = NativeService::start($registry, 'web', '10.0.0.1', 8080, ['id' => 'web-1']);
        $service->stop();
        $service->stop();
    }

    public function testHeartbeatIsSentForTheRegisteredId(): void
    {
        $registry = $this->createMock(Registry::class);
        $registry->expects($this->once())->method('heartbeat')->with('web-1', 'tick');

        $service = NativeService::start($registry, 'web', '10.0.0.1', 8080, ['id' => 'web-1']);
        $service->heartbeat('tick');
        $service->stop();
    }

    public function testHeartbeatFailureDoesNotBubbleUp(): void
    {
        $registry = $this->createMock(Registry::class);
        $registry->method('heartbeat')->willThrowException(new RuntimeException('consul 不可达'));

        $service = NativeService::start($registry, 'web', '10.0.0.1', 8080, ['id' => 'web-1'], 10, new NullLogger());
        $service->heartbeat();   // 不应抛出：业务进程不该被心跳拖垮

        $this->assertSame('web-1', $service->serviceId());
        $service->stop();
    }

    public function testDeregisterFailureDoesNotBubbleUp(): void
    {
        $registry = $this->createMock(Registry::class);
        $registry->method('deregister')->willThrowException(new RuntimeException('consul 不可达'));

        NativeService::start($registry, 'web', '10.0.0.1', 8080, ['id' => 'web-1'], 10, new NullLogger())->stop();

        $this->assertTrue(true);   // 走到这里就说明没抛异常
    }
}
