<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Peering;
use Erikwang2013\Consul\Exception\NotFoundException;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class PeeringTest extends TestCase
{
    private $transport;
    private Peering $peering;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->peering = new Peering($this->transport);
    }

    public function testGenerateToken(): void
    {
        $payload = ['PeerName' => 'cluster-b'];
        $this->transport->method('post')
            ->with('/v1/peering/token', $payload, [])
            ->willReturn(['PeeringToken' => 'eyJ0b2tlbiI6...']);

        $this->assertSame('eyJ0b2tlbiI6...', $this->peering->generateToken($payload)['PeeringToken']);
    }

    public function testGenerateTokenWithExternalAddresses(): void
    {
        $payload = [
            'PeerName' => 'cluster-b',
            'ServerExternalAddresses' => ['10.0.0.1:8300'],
            'Meta' => ['env' => 'prod'],
        ];
        $this->transport->method('post')
            ->with('/v1/peering/token', $payload, [])
            ->willReturn(['PeeringToken' => 'tok']);

        $this->assertSame('tok', $this->peering->generateToken($payload)['PeeringToken']);
    }

    public function testEstablish(): void
    {
        $payload = ['PeerName' => 'cluster-a', 'PeeringToken' => 'tok'];
        $this->transport->method('post')
            ->with('/v1/peering/establish', $payload, [])
            ->willReturn(['Name' => 'cluster-a', 'State' => 'PENDING']);

        $this->assertSame('PENDING', $this->peering->establish($payload)['State']);
    }

    public function testEstablishWithPartition(): void
    {
        $payload = ['PeerName' => 'cluster-a', 'PeeringToken' => 'tok'];
        $this->transport->method('post')
            ->with('/v1/peering/establish', $payload, ['partition' => 'part1'])
            ->willReturn(['Name' => 'cluster-a']);

        $this->assertSame('cluster-a', $this->peering->establish($payload, ['partition' => 'part1'])['Name']);
    }

    public function testList(): void
    {
        $this->transport->method('get')
            ->with('/v1/peerings', [])
            ->willReturn([['Name' => 'cluster-a', 'State' => 'ACTIVE']]);

        $this->assertSame('ACTIVE', $this->peering->list()[0]['State']);
    }

    public function testListWithOptionsFiltersUnknownKeys(): void
    {
        $this->transport->method('get')
            ->with('/v1/peerings', ['dc' => 'dc1'])
            ->willReturn([]);

        $this->assertSame([], $this->peering->list(['dc' => 'dc1', 'index' => 9]));
    }

    public function testRead(): void
    {
        $this->transport->method('get')
            ->with('/v1/peering/cluster-a', [])
            ->willReturn(['Name' => 'cluster-a', 'State' => 'ACTIVE']);

        $this->assertSame('ACTIVE', $this->peering->read('cluster-a')['State']);
    }

    public function testReadEncodesName(): void
    {
        $this->transport->method('get')
            ->with('/v1/peering/a%20b', [])
            ->willReturn([]);

        $this->assertSame([], $this->peering->read('a b'));
    }

    public function testReadPropagatesNotFound(): void
    {
        $this->transport->method('get')->willThrowException(new NotFoundException('nope'));

        $this->expectException(NotFoundException::class);
        $this->peering->read('missing');
    }

    public function testDelete(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/peering/cluster-a', []);

        $this->peering->delete('cluster-a');
    }

    public function testDeleteWithOptions(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/peering/cluster-a', ['partition' => 'part1']);

        $this->peering->delete('cluster-a', ['partition' => 'part1']);
    }
}
