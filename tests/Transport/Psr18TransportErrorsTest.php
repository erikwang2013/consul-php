<?php

namespace Erikwang2013\Consul\Tests\Transport;

use Erikwang2013\Consul\Exception\ClientException;
use Erikwang2013\Consul\Exception\NotFoundException;
use Erikwang2013\Consul\Exception\ServerException;
use Erikwang2013\Consul\Exception\UnauthorizedException;
use Erikwang2013\Consul\Transport\Psr18Transport;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class Psr18TransportErrorsTest extends TestCase
{
    private $httpClient;
    private $requestFactory;
    private $streamFactory;
    private Psr18Transport $transport;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->transport = new Psr18Transport(
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory
        );
    }

    private function createStreamWithContent(string $content): StreamInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($content);
        return $stream;
    }

    private function stubResponse(int $statusCode, string $body): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getBody')->willReturn($this->createStreamWithContent($body));
        return $response;
    }

    public function test401ThrowsUnauthorizedException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($this->stubResponse(401, 'no token'));

        $this->expectException(UnauthorizedException::class);
        $this->transport->get('/v1/agent/self');
    }

    public function testErrorCodeIsPreservedOnException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($this->stubResponse(404, 'missing'));

        try {
            $this->transport->get('/v1/kv/nope');
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertSame(404, $e->getCode());
            $this->assertStringContainsString('404', $e->getMessage());
            $this->assertStringContainsString('missing', $e->getMessage());
        }
    }

    public function testGetRaw404ThrowsNotFoundException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($this->stubResponse(404, ''));

        $this->expectException(NotFoundException::class);
        $this->transport->getRaw('/v1/kv/missing', ['raw' => 'true']);
    }

    public function testGetRaw500ThrowsServerException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($this->stubResponse(503, 'unavailable'));

        $this->expectException(ServerException::class);
        $this->transport->getRaw('/v1/snapshot');
    }

    public function testGetWithHeadersTransportErrorThrowsClientException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')
            ->willThrowException(new \RuntimeException('timeout'));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP transport error');
        $this->transport->getWithHeaders('/v1/kv/app/', ['index' => '0']);
    }

    public function testGetWithHeaders500ThrowsServerException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($this->stubResponse(500, 'boom'));

        $this->expectException(ServerException::class);
        $this->transport->getWithHeaders('/v1/kv/app/', []);
    }

    public function testInvalidJsonResponseThrowsClientException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($this->stubResponse(200, '{not-json'));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Failed to decode Consul response');
        $this->transport->get('/v1/agent/self');
    }

    public function testEmptyJsonBodyDecodesToEmptyArray(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($this->stubResponse(200, ''));

        $result = $this->transport->get('/v1/status/leader');

        $this->assertSame([], $result);
    }

    public function testPutRawWithErrorStatusThrowsMappedException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('withBody')->willReturn($request);
        $request->method('withHeader')->willReturn($request);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($this->stubResponse(403, 'forbidden'));

        $this->streamFactory->method('createStream')->willReturn($this->createMock(StreamInterface::class));

        $this->expectException(\Erikwang2013\Consul\Exception\AccessDeniedException::class);
        $this->transport->putRaw('/v1/kv/key', 'data');
    }
}
