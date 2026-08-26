<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Operator;
use Erikwang2013\Consul\Exception\ClientException;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class OperatorTest extends TestCase
{
    private $transport;
    private Operator $operator;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->operator = new Operator($this->transport);
    }

    public function testRaftConfig(): void
    {
        $this->transport->method('get')
            ->with('/v1/operator/raft/configuration')
            ->willReturn(['Servers' => [['ID' => 'node1', 'Address' => '10.0.0.1:8300']]]);

        $this->assertArrayHasKey('Servers', $this->operator->raftConfig());
    }

    public function testRaftConfigPropagatesTransportError(): void
    {
        $this->transport->method('get')->willThrowException(new ClientException('down'));

        $this->expectException(ClientException::class);
        $this->operator->raftConfig();
    }

    public function testRaftPeer(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/operator/raft/peer', ['address' => '10.0.0.3:8300']);

        $this->operator->raftPeer('10.0.0.3:8300');
    }

    public function testRaftPeerWithSpecialAddress(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/operator/raft/peer', ['address' => 'a b:8300']);

        $this->operator->raftPeer('a b:8300');
    }

    public function testAutopilotConfig(): void
    {
        $this->transport->method('get')
            ->with('/v1/operator/autopilot/configuration')
            ->willReturn(['CleanupDeadServers' => true]);

        $this->assertTrue($this->operator->autopilotConfig()['CleanupDeadServers']);
    }

    public function testUpdateAutopilotConfig(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/operator/autopilot/configuration', ['CleanupDeadServers' => false]);

        $this->operator->updateAutopilotConfig(['CleanupDeadServers' => false]);
    }

    public function testAutopilotHealth(): void
    {
        $this->transport->method('get')
            ->with('/v1/operator/autopilot/health')
            ->willReturn(['Healthy' => true, 'FailureTolerance' => 0]);

        $result = $this->operator->autopilotHealth();

        $this->assertTrue($result['Healthy']);
        $this->assertSame(0, $result['FailureTolerance']);
    }

    public function testKeyringList(): void
    {
        $this->transport->method('get')
            ->with('/v1/operator/keyring', [])
            ->willReturn([['PrimaryKey' => 'abc']]);

        $this->assertSame('abc', $this->operator->keyring('list')[0]['PrimaryKey']);
    }

    public function testKeyringListWithRelayAndLocal(): void
    {
        $this->transport->method('get')
            ->with('/v1/operator/keyring', ['relay' => '10.0.0.2:8300', 'local' => 'true'])
            ->willReturn([]);

        $this->assertSame([], $this->operator->keyring('list', ['relay' => '10.0.0.2:8300', 'local' => 'true']));
    }

    public function testKeyringInstall(): void
    {
        $this->transport->method('post')
            ->with('/v1/operator/keyring', ['Key' => 'new-key'], [])
            ->willReturn(['Messages' => []]);

        $this->assertSame([], $this->operator->keyring('install', ['key' => 'new-key'])['Messages']);
    }

    public function testKeyringInstallWithRelay(): void
    {
        $this->transport->method('post')
            ->with('/v1/operator/keyring', ['Key' => 'new-key'], ['relay' => '10.0.0.2:8300'])
            ->willReturn([]);

        $this->assertSame([], $this->operator->keyring('install', ['key' => 'new-key', 'relay' => '10.0.0.2:8300']));
    }

    public function testKeyringUse(): void
    {
        $this->transport->method('put')
            ->with('/v1/operator/keyring', ['Key' => 'primary-key'], [])
            ->willReturn(['Messages' => []]);

        $this->assertSame([], $this->operator->keyring('use', ['key' => 'primary-key'])['Messages']);
    }

    public function testKeyringUseWithLocal(): void
    {
        $this->transport->method('put')
            ->with('/v1/operator/keyring', ['Key' => 'primary-key'], ['local' => 'true'])
            ->willReturn([]);

        $this->assertSame([], $this->operator->keyring('use', ['key' => 'primary-key', 'local' => 'true']));
    }

    public function testKeyringRemove(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/operator/keyring', ['Key' => 'old-key']);

        $this->operator->keyring('remove', ['key' => 'old-key']);
    }
}
