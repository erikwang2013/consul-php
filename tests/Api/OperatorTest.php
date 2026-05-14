<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Operator;
use Erikwang\Consul\Transport\TransportInterface;
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

        $result = $this->operator->raftConfig();

        $this->assertArrayHasKey('Servers', $result);
    }

    public function testRaftPeer(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/operator/raft/peer', ['address' => '10.0.0.3:8300']);

        $this->operator->raftPeer('10.0.0.3:8300');
    }

    public function testAutopilotConfig(): void
    {
        $this->transport->method('get')
            ->with('/v1/operator/autopilot/configuration')
            ->willReturn(['CleanupDeadServers' => true]);

        $result = $this->operator->autopilotConfig();

        $this->assertTrue($result['CleanupDeadServers']);
    }

    public function testUpdateAutopilotConfig(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/operator/autopilot/configuration', ['CleanupDeadServers' => false]);

        $this->operator->updateAutopilotConfig(['CleanupDeadServers' => false]);
    }

    public function testKeyringList(): void
    {
        $this->transport->method('get')
            ->with('/v1/operator/keyring', [])
            ->willReturn([['PrimaryKey' => 'abc']]);

        $result = $this->operator->keyring('list');

        $this->assertSame('abc', $result[0]['PrimaryKey']);
    }

    public function testKeyringInstall(): void
    {
        $this->transport->method('post')
            ->with('/v1/operator/keyring', ['Key' => 'new-key'], [])
            ->willReturn(['Messages' => []]);

        $result = $this->operator->keyring('install', ['key' => 'new-key']);

        $this->assertSame([], $result['Messages']);
    }

    public function testKeyringUse(): void
    {
        $this->transport->method('put')
            ->with('/v1/operator/keyring', ['Key' => 'primary-key'], [])
            ->willReturn(['Messages' => []]);

        $result = $this->operator->keyring('use', ['key' => 'primary-key']);

        $this->assertSame([], $result['Messages']);
    }
}
