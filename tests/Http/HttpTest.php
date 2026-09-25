<?php

namespace Erikwang2013\Consul\Tests\Http;

use Erikwang2013\Consul\Http\Request;
use Erikwang2013\Consul\Http\RequestFactory;
use Erikwang2013\Consul\Http\Response;
use Erikwang2013\Consul\Http\Stream;
use Erikwang2013\Consul\Http\StreamFactory;
use Erikwang2013\Consul\Http\Uri;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HttpTest extends TestCase
{
    public function testUriParsesAndRebuilds(): void
    {
        $uri = new Uri('http://user:pass@consul.local:8500/v1/kv/app/db?recurse=1#frag');

        $this->assertSame('http', $uri->getScheme());
        $this->assertSame('consul.local', $uri->getHost());
        $this->assertSame(8500, $uri->getPort());
        $this->assertSame('user:pass', $uri->getUserInfo());
        $this->assertSame('/v1/kv/app/db', $uri->getPath());
        $this->assertSame('recurse=1', $uri->getQuery());
        $this->assertSame('frag', $uri->getFragment());
        $this->assertSame('user:pass@consul.local:8500', $uri->getAuthority());
        $this->assertSame(
            'http://user:pass@consul.local:8500/v1/kv/app/db?recurse=1#frag',
            (string) $uri
        );
    }

    public function testUriHidesDefaultPort(): void
    {
        $this->assertNull((new Uri('http://consul.local:80/v1/status/leader'))->getPort());
        $this->assertNull((new Uri('https://consul.local:443/v1/status/leader'))->getPort());
        $this->assertSame(8080, (new Uri('http://consul.local:8080/x'))->getPort());
    }

    public function testUriWithMethodsReturnNewInstances(): void
    {
        $uri = new Uri('http://consul.local:8500/v1/kv');
        $changed = $uri->withPath('/v1/health/service/web')->withQuery('passing=true');

        $this->assertSame('/v1/kv', $uri->getPath());
        $this->assertSame('http://consul.local:8500/v1/health/service/web?passing=true', (string) $changed);
    }

    public function testInvalidUriThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Uri('http:///');
    }

    public function testStreamReadWriteSeek(): void
    {
        $stream = new Stream('hello');
        $this->assertSame(5, $stream->getSize());
        $this->assertSame('hello', (string) $stream);

        $stream->seek(1);
        $this->assertSame('ello', $stream->getContents());
        $this->assertTrue($stream->eof());

        // 指针停在末尾，写入即追加
        $stream->write('!');
        $this->assertSame('hello!', (string) $stream);
    }

    public function testStreamDetachAndClose(): void
    {
        $stream = new Stream('data');
        $this->assertTrue(is_resource($stream->detach()));
        $this->assertNull($stream->getSize());
        $this->assertSame('', (string) $stream);

        $closed = new Stream('x');
        $closed->close();
        $this->assertNull($closed->getSize());
    }

    public function testRequestHeadersAndBody(): void
    {
        $request = new Request('put', 'http://consul.local:8500/v1/kv/key', ['x-consul-token' => 'abc'], 'value');

        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('abc', $request->getHeaderLine('X-Consul-Token'));
        $this->assertTrue($request->hasHeader('x-consul-token'));
        $this->assertSame('value', (string) $request->getBody());
        $this->assertSame('/v1/kv/key', $request->getRequestTarget());
    }

    public function testRequestWithHeadersIsImmutable(): void
    {
        $request = new Request('GET', 'http://consul.local:8500/v1/kv');
        $withToken = $request->withHeader('X-Consul-Token', 'abc')->withAddedHeader('X-Consul-Token', 'def');

        $this->assertFalse($request->hasHeader('X-Consul-Token'));
        $this->assertSame(['abc', 'def'], $withToken->getHeader('X-Consul-Token'));
        $this->assertSame('abc, def', $withToken->getHeaderLine('X-Consul-Token'));
        $this->assertFalse($withToken->withoutHeader('X-Consul-Token')->hasHeader('X-Consul-Token'));
    }

    public function testHeaderInjectionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Request('GET', 'http://consul.local'))->withHeader('X-Test', "bad\r\nInjected: 1");
    }

    public function testResponseStatusAndReason(): void
    {
        $response = new Response(404, ['Content-Type' => 'application/json'], '{"ok":false}');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Not Found', $response->getReasonPhrase());
        $this->assertSame('application/json', $response->getHeaderLine('content-type'));
        $this->assertSame('{"ok":false}', (string) $response->getBody());
        $this->assertSame(503, $response->withStatus(503)->getStatusCode());
    }

    public function testRequestFactorySetsHostHeader(): void
    {
        $request = (new RequestFactory())->createRequest('GET', 'http://consul.local:8500/v1/status/leader');

        $this->assertSame('consul.local:8500', $request->getHeaderLine('Host'));
        $this->assertSame('', (string) $request->getBody());
    }

    public function testStreamFactoryFromStringFileAndResource(): void
    {
        $factory = new StreamFactory();
        $this->assertSame('abc', (string) $factory->createStream('abc'));

        $file = tempnam(sys_get_temp_dir(), 'consul');
        file_put_contents($file, 'file-content');
        $this->assertSame('file-content', (string) $factory->createStreamFromFile($file));
        unlink($file);

        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'res');
        rewind($resource);
        $this->assertSame('res', (string) $factory->createStreamFromResource($resource));
    }

    public function testStreamFactoryRejectsMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        (new StreamFactory())->createStreamFromFile('/nonexistent/consul-php-test');
    }
}
