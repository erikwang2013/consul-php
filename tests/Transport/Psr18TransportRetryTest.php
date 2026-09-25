<?php

namespace Erikwang2013\Consul\Tests\Transport;

use Erikwang2013\Consul\Exception\ClientException;
use Erikwang2013\Consul\Exception\ServerException;
use Erikwang2013\Consul\Transport\Psr18Transport;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * 重试只覆盖"传输层异常"（连接被拒、超时），且只对幂等方法做。
 */
class Psr18TransportRetryTest extends TestCase
{
    private $httpClient;
    private $requestFactory;
    private $streamFactory;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
    }

    private function transport(int $retries): Psr18Transport
    {
        return new Psr18Transport(
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
            'http://127.0.0.1:8500',
            null,
            null,
            $retries,
            0   // 测试里不真等
        );
    }

    private function stubResponse(int $status = 200, string $body = '{"ok":true}'): ResponseInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($body);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn($stream);

        return $response;
    }

    /** 可链式调用的请求桩：withHeader/withBody 都返回自身。 */
    private function stubRequest(): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();
        $request->method('withBody')->willReturnSelf();

        return $request;
    }

    private function stubRequestFactory(string $expectedMethod, string $expectedUri): RequestInterface
    {
        $request = $this->stubRequest();
        $this->requestFactory->method('createRequest')
            ->with($expectedMethod, $expectedUri)
            ->willReturn($request);

        return $request;
    }

    /** 前 $failures 次调用抛传输异常，之后返回成功响应。 */
    private function failThenSucceed(int $failures, ResponseInterface $response, int &$calls): void
    {
        $this->httpClient->method('sendRequest')->willReturnCallback(
            function () use ($failures, $response, &$calls) {
                $calls++;
                if ($calls <= $failures) {
                    throw new RuntimeException('连接被拒');
                }

                return $response;
            }
        );
    }

    public function testGetIsRetriedUntilItSucceeds(): void
    {
        $this->stubRequestFactory('GET', 'http://127.0.0.1:8500/v1/status/leader');
        $calls = 0;
        $this->failThenSucceed(2, $this->stubResponse(), $calls);

        $this->assertSame(['ok' => true], $this->transport(2)->get('/v1/status/leader'));
        $this->assertSame(3, $calls);
    }

    public function testPutIsRetriedWithItsBody(): void
    {
        $this->stubRequestFactory('PUT', 'http://127.0.0.1:8500/v1/kv/key');
        $this->streamFactory->method('createStream')->willReturn($this->createMock(StreamInterface::class));
        $calls = 0;
        $this->failThenSucceed(1, $this->stubResponse(200, 'true'), $calls);

        $this->assertSame(['body' => true], $this->transport(1)->put('/v1/kv/key', ['Value' => 'v']));
        $this->assertSame(2, $calls);
    }

    public function testDeleteIsRetried(): void
    {
        $this->stubRequestFactory('DELETE', 'http://127.0.0.1:8500/v1/kv/key');
        $calls = 0;
        $this->failThenSucceed(1, $this->stubResponse(200, ''), $calls);

        $this->assertSame([], $this->transport(1)->delete('/v1/kv/key'));
        $this->assertSame(2, $calls);
    }

    public function testPostIsNeverRetried(): void
    {
        $this->stubRequestFactory('POST', 'http://127.0.0.1:8500/v1/txn');
        $this->streamFactory->method('createStream')->willReturn($this->createMock(StreamInterface::class));

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException(new RuntimeException('连接被拒'));

        try {
            $this->transport(3)->post('/v1/txn', ['a' => 1]);
            $this->fail('POST 不应该成功');
        } catch (ClientException $e) {
            $this->assertSame('HTTP transport error', $e->getMessage());
            $this->assertInstanceOf(RuntimeException::class, $e->getPrevious());
        }
    }

    public function testRetriesAreExhaustedThenClientExceptionIsThrown(): void
    {
        $this->stubRequestFactory('GET', 'http://127.0.0.1:8500/v1/status/leader');

        $calls = 0;
        $this->httpClient->method('sendRequest')->willReturnCallback(
            function () use (&$calls) {
                $calls++;

                throw new RuntimeException('连接被拒');
            }
        );

        try {
            $this->transport(1)->get('/v1/status/leader');
            $this->fail('重试耗尽后应该抛 ClientException');
        } catch (ClientException $e) {
            $this->assertSame(2, $calls, 'retry.times=1 表示总共尝试 2 次');
            $this->assertSame('HTTP transport error', $e->getMessage());
        }
    }

    public function testNoRetriesByDefault(): void
    {
        $this->stubRequestFactory('GET', 'http://127.0.0.1:8500/v1/status/leader');

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willThrowException(new RuntimeException('连接被拒'));

        $this->expectException(ClientException::class);

        $this->transport(0)->get('/v1/status/leader');
    }

    public function testServerErrorIsNotRetried(): void
    {
        $this->stubRequestFactory('GET', 'http://127.0.0.1:8500/v1/status/leader');

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->stubResponse(503, 'no leader'));

        $this->expectException(ServerException::class);

        $this->transport(3)->get('/v1/status/leader');
    }

    public function testDeleteWithBodySendsDeleteWithJsonBody(): void
    {
        $request = $this->stubRequest();
        $bodyStream = $this->createMock(StreamInterface::class);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('DELETE', 'http://127.0.0.1:8500/v1/operator/keyring')
            ->willReturn($request);

        $this->streamFactory->expects($this->once())
            ->method('createStream')
            ->with(json_encode(['Keys' => ['abc']]))
            ->willReturn($bodyStream);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($this->stubResponse());

        $this->assertSame(
            ['ok' => true],
            $this->transport(0)->deleteWithBody('/v1/operator/keyring', ['Keys' => ['abc']])
        );
    }

    public function testDeleteWithoutBodySendsNoContentType(): void
    {
        $this->stubRequestFactory('DELETE', 'http://127.0.0.1:8500/v1/kv/key');
        $this->streamFactory->expects($this->never())->method('createStream');
        $this->httpClient->method('sendRequest')->willReturn($this->stubResponse(200, ''));

        $this->assertSame([], $this->transport(0)->delete('/v1/kv/key'));
    }
}
