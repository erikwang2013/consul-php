<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Kv;
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

        $result = $this->kv->get('missing');

        $this->assertNull($result);
    }

    public function testAllReturnsRecursiveList(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/config/', ['recurse' => 'true'])
            ->willReturn([
                ['Key' => 'config/app', 'Value' => base64_encode('a')],
                ['Key' => 'config/db', 'Value' => base64_encode('b')],
            ]);

        $result = $this->kv->all('config/');

        $this->assertCount(2, $result);
    }

    public function testPutEncodesValue(): void
    {
        $this->transport->method('put')
            ->with('/v1/kv/key', ['value' => base64_encode('data')], [])
            ->willReturn(['body' => true]);

        $result = $this->kv->put('key', 'data');

        $this->assertTrue($result);
    }

    public function testKeysReturnsKeyList(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/config/', ['keys' => 'true'])
            ->willReturn(['config/app', 'config/db']);

        $result = $this->kv->keys('config/');

        $this->assertSame(['config/app', 'config/db'], $result);
    }

    public function testDelete(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/kv/key', []);

        $result = $this->kv->delete('key');

        $this->assertTrue($result);
    }
}
