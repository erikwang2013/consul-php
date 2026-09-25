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

    public function testRaftPeerById(): void
    {
        // 文档推荐用 server ID（IP 会变）
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/operator/raft/peer', ['id' => 'a1b2c3d4'])
            ->willReturn([]);

        $this->operator->raftPeer('', ['id' => 'a1b2c3d4']);
    }

    public function testRaftPeerByAddressPassedAsOption(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/operator/raft/peer', ['address' => '10.0.0.4:8300'])
            ->willReturn([]);

        $this->operator->raftPeer('', ['address' => '10.0.0.4:8300']);
    }

    public function testRaftPeerRequiresIdOrAddress(): void
    {
        $this->transport->expects($this->never())->method('delete');

        $this->expectException(\InvalidArgumentException::class);
        $this->operator->raftPeer();
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

    public function testKeyringListWithRelayFactorAndLocalOnly(): void
    {
        // Consul 读的是带连字符的 relay-factor / local-only；写成 relay / local 会被静默忽略
        $this->transport->method('get')
            ->with('/v1/operator/keyring', ['relay-factor' => 3, 'local-only' => 'true'])
            ->willReturn([]);

        $this->assertSame([], $this->operator->keyring('list', ['relay-factor' => 3, 'local-only' => 'true']));
    }

    public function testKeyringInstall(): void
    {
        $this->transport->method('post')
            ->with('/v1/operator/keyring', ['Key' => 'new-key'], [])
            ->willReturn(['Messages' => []]);

        $this->assertSame([], $this->operator->keyring('install', ['key' => 'new-key'])['Messages']);
    }

    public function testKeyringInstallWithRelayFactor(): void
    {
        $this->transport->method('post')
            ->with('/v1/operator/keyring', ['Key' => 'new-key'], ['relay-factor' => 2])
            ->willReturn([]);

        $this->assertSame([], $this->operator->keyring('install', ['key' => 'new-key', 'relay-factor' => 2]));
    }

    public function testKeyringUse(): void
    {
        $this->transport->method('put')
            ->with('/v1/operator/keyring', ['Key' => 'primary-key'], [])
            ->willReturn(['Messages' => []]);

        $this->assertSame([], $this->operator->keyring('use', ['key' => 'primary-key'])['Messages']);
    }

    public function testKeyringUseWithLocalOnly(): void
    {
        $this->transport->method('put')
            ->with('/v1/operator/keyring', ['Key' => 'primary-key'], ['local-only' => 'true'])
            ->willReturn([]);

        $this->assertSame([], $this->operator->keyring('use', ['key' => 'primary-key', 'local-only' => 'true']));
    }

    public function testKeyringRemoveSendsKeyInBody(): void
    {
        // keyring 端点只从 JSON body 读参数，DELETE 必须带 body，否则上游 400
        $this->transport->expects($this->once())
            ->method('deleteWithBody')
            ->with('/v1/operator/keyring', ['Key' => 'old-key'], [])
            ->willReturn([]);
        $this->transport->expects($this->never())->method('delete');

        $this->assertSame([], $this->operator->keyring('remove', ['key' => 'old-key']));
    }

    public function testKeyringRemoveWithLocalOnlyKeepsQuery(): void
    {
        $this->transport->expects($this->once())
            ->method('deleteWithBody')
            ->with('/v1/operator/keyring', ['Key' => 'old-key'], ['local-only' => 'true'])
            ->willReturn([]);

        $this->operator->keyring('remove', ['key' => 'old-key', 'local-only' => 'true']);
    }

    public function testKeyringRemoveRequiresKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->operator->keyring('remove', []);
    }

    public function testRaftTransferLeader(): void
    {
        $this->transport->expects($this->once())
            ->method('post')
            ->with('/v1/operator/raft/transfer-leader', ['Address' => '10.0.0.2:8300'], [])
            ->willReturn([]);

        $this->assertSame([], $this->operator->raftTransferLeader('10.0.0.2:8300'));
    }

    public function testAutopilotState(): void
    {
        $this->transport->method('get')
            ->with('/v1/operator/autopilot/state')
            ->willReturn(['Healthy' => true, 'Leader' => 'node1']);

        $result = $this->operator->autopilotState();

        $this->assertTrue($result['Healthy']);
        $this->assertSame('node1', $result['Leader']);
    }

    public function testFeatures(): void
    {
        $this->transport->method('get')
            ->with('/v1/operator/features')
            ->willReturn([['Name' => 'resource-apis', 'Enabled' => false]]);

        $result = $this->operator->features();

        $this->assertCount(1, $result);
        $this->assertSame('resource-apis', $result[0]['Name']);
        $this->assertFalse($result[0]['Enabled']);
    }

    public function testFeaturesReturnsEmptyArray(): void
    {
        $this->transport->method('get')
            ->with('/v1/operator/features')
            ->willReturn([]);

        $this->assertSame([], $this->operator->features());
    }

    public function testFeature(): void
    {
        $this->transport->method('get')
            ->with('/v1/operator/feature/resource-apis')
            ->willReturn(['Name' => 'resource-apis', 'Enabled' => true]);

        $result = $this->operator->feature('resource-apis');

        $this->assertSame('resource-apis', $result['Name']);
        $this->assertTrue($result['Enabled']);
    }

    public function testFeatureUrlEncodesSpecialCharacters(): void
    {
        $this->transport->method('get')
            ->with('/v1/operator/feature/my%20gate%2F1')
            ->willReturn([]);

        $this->assertSame([], $this->operator->feature('my gate/1'));
    }

    public function testUpdateFeature(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/operator/feature/resource-apis', ['Enabled' => true], [])
            ->willReturn(['Applied' => true, 'Feature' => ['Name' => 'resource-apis', 'Enabled' => true]]);

        $result = $this->operator->updateFeature('resource-apis', ['Enabled' => true]);

        $this->assertTrue($result['Applied']);
        $this->assertTrue($result['Feature']['Enabled']);
    }

    public function testUpdateFeatureWithCas(): void
    {
        // cas 是上游给的乐观锁：策略版本号，比对失败则拒绝本次修改
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/operator/feature/resource-apis', ['Enabled' => false], ['cas' => 7])
            ->willReturn(['Applied' => false]);

        $result = $this->operator->updateFeature('resource-apis', ['Enabled' => false], ['cas' => 7]);

        $this->assertFalse($result['Applied']);
    }

    public function testUpdateFeatureDropsUnknownOptions(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/operator/feature/resource-apis', ['Enabled' => true], [])
            ->willReturn([]);

        $this->assertSame([], $this->operator->updateFeature('resource-apis', ['Enabled' => true], ['bogus' => 1]));
    }
}
