<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Query;
use Erikwang2013\Consul\Exception\NotFoundException;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class QueryTest extends TestCase
{
    private $transport;
    private Query $query;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->query = new Query($this->transport);
    }

    public function testList(): void
    {
        $this->transport->method('get')
            ->with('/v1/query', [])
            ->willReturn([['ID' => 'q-1', 'Name' => 'web']]);

        $this->assertSame('web', $this->query->list()[0]['Name']);
    }

    public function testListWithOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/query', ['dc' => 'dc1'])
            ->willReturn([]);

        $this->assertSame([], $this->query->list(['dc' => 'dc1', 'index' => 5]));
    }

    public function testCreate(): void
    {
        $definition = ['Name' => 'web', 'Service' => ['Service' => 'web']];
        $this->transport->method('post')
            ->with('/v1/query', $definition, [])
            ->willReturn(['ID' => 'q-1']);

        $this->assertSame('q-1', $this->query->create($definition)['ID']);
    }

    public function testRead(): void
    {
        $this->transport->method('get')
            ->with('/v1/query/q-1', [])
            ->willReturn(['ID' => 'q-1', 'Name' => 'web']);

        $this->assertSame('web', $this->query->read('q-1')['Name']);
    }

    public function testReadPropagatesNotFound(): void
    {
        $this->transport->method('get')->willThrowException(new NotFoundException('nope'));

        $this->expectException(NotFoundException::class);
        $this->query->read('missing');
    }

    public function testReadEncodesId(): void
    {
        $this->transport->method('get')
            ->with('/v1/query/a%2Fb', [])
            ->willReturn([]);

        $this->assertSame([], $this->query->read('a/b'));
    }

    public function testUpdate(): void
    {
        $definition = ['Name' => 'web-v2', 'Service' => ['Service' => 'web']];
        $this->transport->method('put')
            ->with('/v1/query/q-1', $definition, [])
            ->willReturn([]);

        $this->assertSame([], $this->query->update('q-1', $definition));
    }

    public function testDelete(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/query/q-1', []);

        $this->query->delete('q-1');
    }

    public function testExecuteUsesGet(): void
    {
        // 上游对 /execute 只接受 GET（不是 POST）
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/query/q-1/execute', [])
            ->willReturn(['Nodes' => [['Node' => 'n1']], 'Failover' => []]);

        $this->assertSame('n1', $this->query->execute('q-1')['Nodes'][0]['Node']);
    }

    public function testExecuteAcceptsQueryName(): void
    {
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/query/web/execute', [])
            ->willReturn(['Nodes' => []]);

        $this->assertSame([], $this->query->execute('web')['Nodes']);
    }

    public function testExecuteWithOptions(): void
    {
        $this->transport->method('get')
            ->with(
                '/v1/query/q-1/execute',
                ['dc' => 'dc1', 'limit' => 3, 'connect' => 'true', 'near' => 'n1']
            )
            ->willReturn(['Nodes' => []]);

        $this->assertSame([], $this->query->execute('q-1', [
            'dc' => 'dc1',
            'limit' => 3,
            'connect' => 'true',
            'near' => 'n1',
            'raw' => true,
        ])['Nodes']);
    }

    public function testExplain(): void
    {
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/query/q-1/explain', [])
            ->willReturn(['Query' => ['ID' => 'q-1'], 'Nodes' => []]);

        $this->assertSame('q-1', $this->query->explain('q-1')['Query']['ID']);
    }

    public function testExplainWithOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/query/q-1/explain', ['dc' => 'dc1'])
            ->willReturn([]);

        $this->assertSame([], $this->query->explain('q-1', ['dc' => 'dc1']));
    }
}
