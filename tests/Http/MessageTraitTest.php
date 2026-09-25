<?php

namespace Erikwang2013\Consul\Tests\Http;

use Erikwang2013\Consul\Http\Request;
use Erikwang2013\Consul\Http\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MessageTraitTest extends TestCase
{
    public function testProtocolVersionDefaultsToHttp11AndIsImmutable(): void
    {
        $request = new Request('GET', 'http://consul.local');

        $this->assertSame('1.1', $request->getProtocolVersion());
        $this->assertSame('1.1', (new Response())->getProtocolVersion());

        $downgraded = $request->withProtocolVersion('1.0');

        $this->assertSame('1.0', $downgraded->getProtocolVersion());
        $this->assertSame('1.1', $request->getProtocolVersion());
    }

    public function testInvalidHeaderNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('非法的头名');

        (new Request('GET', 'http://consul.local'))->withHeader('X Bad Header', '1');
    }

    public function testEmptyHeaderNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Request('GET', 'http://consul.local'))->withHeader('', '1');
    }

    public function testNonStringHeaderValueIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('头值必须是字符串');

        (new Request('GET', 'http://consul.local'))->withHeader('X-Test', true);
    }

    public function testNonStringHeaderValueInArrayIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('头值必须是字符串');

        (new Request('GET', 'http://consul.local'))->withAddedHeader('X-Test', ['ok', null]);
    }
}
