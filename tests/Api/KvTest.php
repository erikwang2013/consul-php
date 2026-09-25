<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Kv;
use Erikwang2013\Consul\Exception\ClientException;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class KvTest extends TestCase
{
    private $transport;
    private Kv $kv;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->kv = new Kv($this->transport);
    }

    public function testGetReturnsValue(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/config/app', [])
            ->willReturn([['Key' => 'config/app', 'Value' => base64_encode('hello')]]);

        $result = $this->kv->get('config/app');

        $this->assertSame('Key', array_key_first($result));
        $this->assertSame('config/app', $result['Key']);
    }

    public function testGetReturnsNullWhenMissing(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/missing', [])
            ->willReturn([]);

        $this->assertNull($this->kv->get('missing'));
    }

    public function testGetWithQueryOptions(): void
    {
        $options = ['dc' => 'dc1', 'index' => '5', 'wait' => '3s', 'ns' => 'ns1', 'partition' => 'p1'];
        $this->transport->method('get')
            ->with('/v1/kv/config/app', $options)
            ->willReturn([['Key' => 'config/app']]);

        $this->assertSame('config/app', $this->kv->get('config/app', $options)['Key']);
    }

    public function testGetWithRawOptionReturnsRawBody(): void
    {
        $this->transport->expects($this->once())
            ->method('getRaw')
            ->with('/v1/kv/key', ['raw' => 'true'])
            ->willReturn('raw-data');
        $this->transport->expects($this->never())->method('get');

        $this->assertSame(['body' => 'raw-data'], $this->kv->get('key', ['raw' => true]));
    }

    public function testGetWithRawOptionAndQueryParams(): void
    {
        $this->transport->method('getRaw')
            ->with('/v1/kv/key', ['dc' => 'dc1', 'raw' => 'true'])
            ->willReturn('');

        $this->assertSame(['body' => ''], $this->kv->get('key', ['raw' => true, 'dc' => 'dc1']));
    }

    public function testGetPropagatesTransportError(): void
    {
        $this->transport->method('get')->willThrowException(new ClientException('down'));

        $this->expectException(ClientException::class);
        $this->kv->get('key');
    }

    public function testAllReturnsRecursiveList(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/config/', ['recurse' => 'true'])
            ->willReturn([
                ['Key' => 'config/app', 'Value' => base64_encode('a')],
                ['Key' => 'config/db', 'Value' => base64_encode('b')],
            ]);

        $this->assertCount(2, $this->kv->all('config/'));
    }

    public function testAllWithEmptyPrefixHitsRoot(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/', ['recurse' => 'true'])
            ->willReturn([]);

        $this->assertSame([], $this->kv->all());
    }

    public function testAllMergesQueryOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/config/', ['dc' => 'dc1', 'recurse' => 'true'])
            ->willReturn([]);

        $this->assertSame([], $this->kv->all('config/', ['dc' => 'dc1']));
    }

    public function testPutStoresRawValue(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/kv/key', 'data', [])
            ->willReturn(['body' => true]);
        $this->transport->expects($this->never())->method('put');

        $this->assertTrue($this->kv->put('key', 'data'));
    }

    public function testPutWithCasOptions(): void
    {
        $this->transport->method('putRaw')
            ->with('/v1/kv/key', 'data', ['cas' => '42'])
            ->willReturn(['body' => true]);

        $this->assertTrue($this->kv->put('key', 'data', ['cas' => '42']));
    }

    public function testPutReturnsFalseWhenCasFails(): void
    {
        $this->transport->method('putRaw')
            ->with('/v1/kv/key', 'data', ['cas' => '99'])
            ->willReturn(['body' => false]);

        $this->assertFalse($this->kv->put('key', 'data', ['cas' => '99']));
    }

    public function testPutReturnsFalseWhenBodyMissing(): void
    {
        $this->transport->method('putRaw')
            ->with('/v1/kv/key', 'data', [])
            ->willReturn(['headers' => []]);

        $this->assertFalse($this->kv->put('key', 'data'));
    }

    public function testGetForwardsStale(): void
    {
        // 归一成字符串 "true"（上游是 b.Get("stale") == "true"），而不是布尔 true 编出来的 stale=1
        $this->transport->method('get')
            ->with('/v1/kv/config/app', ['stale' => 'true'])
            ->willReturn([['Key' => 'config/app']]);

        $this->assertSame('config/app', $this->kv->get('config/app', ['stale' => true])['Key']);
    }

    public function testGetForwardsConsistent(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/config/app', ['consistent' => 'true'])
            ->willReturn([]);

        $this->assertNull($this->kv->get('config/app', ['consistent' => true]));
    }

    public function testGetDropsFalseConsistencyFlags(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/config/app', [])
            ->willReturn([]);

        $this->assertNull($this->kv->get('config/app', ['stale' => false, 'consistent' => false]));
    }

    public function testGetCombinesStaleWithOtherOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/config/app', ['dc' => 'dc1', 'stale' => 'true'])
            ->willReturn([]);

        $this->assertNull($this->kv->get('config/app', ['dc' => 'dc1', 'stale' => true]));
    }

    public function testGetRawForwardsStale(): void
    {
        $this->transport->method('getRaw')
            ->with('/v1/kv/key', ['stale' => 'true', 'raw' => 'true'])
            ->willReturn('raw-data');

        $this->assertSame(['body' => 'raw-data'], $this->kv->get('key', ['raw' => true, 'stale' => true]));
    }

    public function testAllForwardsStale(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/config/', ['stale' => 'true', 'recurse' => 'true'])
            ->willReturn([]);

        $this->assertSame([], $this->kv->all('config/', ['stale' => true]));
    }

    public function testDelete(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/kv/key', [])
            ->willReturn(['body' => true]);

        $this->assertTrue($this->kv->delete('key'));
    }

    public function testDeleteWithOptions(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/kv/config/', ['dc' => 'dc1', 'recurse' => 'true'])
            ->willReturn(['body' => true]);

        $this->assertTrue($this->kv->delete('config/', ['dc' => 'dc1', 'recurse' => 'true']));
    }

    public function testDeleteWithCasReturnsTrueOnSuccess(): void
    {
        $this->transport->method('delete')
            ->with('/v1/kv/key', ['cas' => '42'])
            ->willReturn(['body' => true]);

        $this->assertTrue($this->kv->delete('key', ['cas' => '42']));
    }

    public function testDeleteReturnsFalseWhenCasFails(): void
    {
        // Consul 在 CAS 不匹配时仍返回 200，但 body 为 false
        $this->transport->method('delete')
            ->with('/v1/kv/key', ['cas' => '99'])
            ->willReturn(['body' => false]);

        $this->assertFalse($this->kv->delete('key', ['cas' => '99']));
    }

    public function testDeleteReturnsFalseWhenBodyMissing(): void
    {
        $this->transport->method('delete')
            ->with('/v1/kv/key', [])
            ->willReturn(['headers' => []]);

        $this->assertFalse($this->kv->delete('key'));
    }

    public function testKeysReturnsKeyList(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/config/', ['keys' => 'true'])
            ->willReturn(['config/app', 'config/db']);

        $this->assertSame(['config/app', 'config/db'], $this->kv->keys('config/'));
    }

    public function testKeysWithSeparator(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/config/', ['separator' => '/', 'keys' => 'true'])
            ->willReturn(['config/', 'other/']);

        $this->assertSame(['config/', 'other/'], $this->kv->keys('config/', '/'));
    }

    public function testEncodeKeyPreservesPathSeparators(): void
    {
        $this->assertSame('config/app/db', $this->kv->encodeKey('config/app/db'));
    }

    public function testEncodeKeyEscapesSpecialCharacters(): void
    {
        $this->assertSame('a%20b', $this->kv->encodeKey('a b'));
        $this->assertSame('a%23b%3Fc%3Dd%26e', $this->kv->encodeKey('a#b?c=d&e'));
        $this->assertSame('%E9%85%8D%E7%BD%AE/%E5%80%BC', $this->kv->encodeKey('配置/值'));
    }

    public function testEncodeKeyPreservesLiteralPercentSequences(): void
    {
        $this->assertSame('a%252Fb', $this->kv->encodeKey('a%2Fb'));
        $this->assertSame('100%25', $this->kv->encodeKey('100%'));
    }

    public function testGetTransport(): void
    {
        $this->assertSame($this->transport, $this->kv->getTransport());
    }
    public function testStringFalseIsNotTreatedAsTrue(): void
    {
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/kv/app', [])
            ->willReturn([]);

        $this->assertNull($this->kv->get('app', ['stale' => 'false']));
    }
}
