<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Txn;
use Erikwang2013\Consul\Exception\ConsulRequestException;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class TxnTest extends TestCase
{
    private $transport;
    private Txn $txn;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->txn = new Txn($this->transport);
    }

    public function testApply(): void
    {
        $ops = [Txn::get('k')];
        $this->transport->method('put')
            ->with('/v1/txn', ['Operations' => $ops], [])
            ->willReturn(['Results' => [['KV' => ['Key' => 'k']]], 'Errors' => []]);

        $result = $this->txn->apply($ops);

        $this->assertSame('k', $result['Results'][0]['KV']['Key']);
        $this->assertSame([], $result['Errors']);
    }

    public function testApplyReindexesOperations(): void
    {
        // 关联数组（如按 key 拼出来的 op map）不能直接进 JSON，必须是 list
        $ops = ['a' => Txn::get('k1'), 'b' => Txn::get('k2')];
        $this->transport->method('put')
            ->with('/v1/txn', ['Operations' => [Txn::get('k1'), Txn::get('k2')]], [])
            ->willReturn(['Results' => [], 'Errors' => []]);

        $this->assertSame(['Results' => [], 'Errors' => []], $this->txn->apply($ops));
    }

    public function testApplyWithOptionsFiltersUnknownKeys(): void
    {
        $this->transport->method('put')
            ->with('/v1/txn', ['Operations' => []], ['dc' => 'dc1', 'ns' => 'default'])
            ->willReturn(['Results' => [], 'Errors' => []]);

        $this->assertSame(
            ['Results' => [], 'Errors' => []],
            $this->txn->apply([], ['dc' => 'dc1', 'ns' => 'default', 'index' => 5, 'raw' => true])
        );
    }

    public function testApplyPropagatesConflictAs409(): void
    {
        $this->transport->method('put')
            ->willThrowException(new ConsulRequestException('Consul request error [409]', 409));

        try {
            $this->txn->apply([Txn::checkIndex('k', 1), Txn::set('k', 'v')]);
            $this->fail('expected ConsulRequestException');
        } catch (ConsulRequestException $e) {
            $this->assertSame(409, $e->getCode());
        }
    }

    public function testSet(): void
    {
        $this->assertSame(
            ['KV' => ['Verb' => 'set', 'Key' => 'k', 'Value' => 'dg==']],
            Txn::set('k', 'v')
        );
    }

    public function testSetEncodesValueAsBase64(): void
    {
        // Consul 侧 Value 是 []byte，按 StdEncoding 解 base64；多字节内容也不能走原始字节
        $this->assertSame(
            ['KV' => ['Verb' => 'set', 'Key' => 'k', 'Value' => '5Lit5paHIHZhbHVl']],
            Txn::set('k', '中文 value')
        );
    }

    public function testSetSendsFlagsWhenNonZero(): void
    {
        $this->assertSame(
            ['KV' => ['Verb' => 'set', 'Key' => 'k', 'Value' => 'dg==', 'Flags' => 42]],
            Txn::set('k', 'v', 42)
        );
    }

    public function testCas(): void
    {
        $this->assertSame(
            ['KV' => ['Verb' => 'cas', 'Key' => 'k', 'Value' => 'dg==', 'Index' => 7]],
            Txn::cas('k', 'v', 7)
        );
    }

    public function testLock(): void
    {
        $this->assertSame(
            ['KV' => ['Verb' => 'lock', 'Key' => 'k', 'Value' => 'dg==', 'Session' => 'sess-1']],
            Txn::lock('k', 'v', 'sess-1')
        );
    }

    public function testUnlock(): void
    {
        $this->assertSame(
            ['KV' => ['Verb' => 'unlock', 'Key' => 'k', 'Value' => 'dg==', 'Session' => 'sess-1']],
            Txn::unlock('k', 'v', 'sess-1')
        );
    }

    public function testGet(): void
    {
        $this->assertSame(['KV' => ['Verb' => 'get', 'Key' => 'k']], Txn::get('k'));
    }

    public function testGetTree(): void
    {
        $this->assertSame(['KV' => ['Verb' => 'get-tree', 'Key' => 'prefix/']], Txn::getTree('prefix/'));
    }

    public function testDelete(): void
    {
        $this->assertSame(['KV' => ['Verb' => 'delete', 'Key' => 'k']], Txn::delete('k'));
    }

    public function testDeleteTree(): void
    {
        $this->assertSame(['KV' => ['Verb' => 'delete-tree', 'Key' => 'prefix/']], Txn::deleteTree('prefix/'));
    }

    public function testDeleteCas(): void
    {
        $this->assertSame(
            ['KV' => ['Verb' => 'delete-cas', 'Key' => 'k', 'Index' => 3]],
            Txn::deleteCas('k', 3)
        );
    }

    public function testCheckIndex(): void
    {
        $this->assertSame(
            ['KV' => ['Verb' => 'check-index', 'Key' => 'k', 'Index' => 9]],
            Txn::checkIndex('k', 9)
        );
    }

    public function testCheckSession(): void
    {
        $this->assertSame(
            ['KV' => ['Verb' => 'check-session', 'Key' => 'k', 'Session' => 'sess-1']],
            Txn::checkSession('k', 'sess-1')
        );
    }

    public function testCheckNotExists(): void
    {
        $this->assertSame(['KV' => ['Verb' => 'check-not-exists', 'Key' => 'k']], Txn::checkNotExists('k'));
    }

    public function testRawPassesOperationThrough(): void
    {
        $op = ['Node' => ['Verb' => 'get', 'Node' => 'n1']];

        $this->assertSame($op, Txn::raw($op));
    }

    public function testMixedOperationsGoThroughOneRequest(): void
    {
        $ops = [
            Txn::checkIndex('lock/k', 0),
            Txn::lock('lock/k', 'holder', 'sess-1'),
            Txn::set('config/x', 'on'),
        ];
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/txn', ['Operations' => $ops], [])
            ->willReturn(['Results' => [], 'Errors' => []]);

        $this->txn->apply($ops);
    }
}
