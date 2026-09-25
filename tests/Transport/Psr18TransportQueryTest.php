<?php

namespace Erikwang2013\Consul\Tests\Transport;

use Erikwang2013\Consul\Http\RequestFactory;
use Erikwang2013\Consul\Http\Response;
use Erikwang2013\Consul\Http\StreamFactory;
use Erikwang2013\Consul\Transport\Psr18Transport;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 查询串编码：数组值必须展开成**重复键**（`?name=a&name=b`）。
 *
 * `http_build_query` 会编成 `name[0]=a`，而 Consul（Go 的 url.Values）只读重复的纯键——
 * `node-meta`、intention 的 `name` 都是这种形态，编错了服务端会静默取不到值。
 * 这里断言的是最终 URI，即真正的线上形态。
 */
class Psr18TransportQueryTest extends TestCase
{
    /** @var list<string> */
    private array $uris = [];

    private function transport(): Psr18Transport
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturnCallback(function (RequestInterface $request): ResponseInterface {
            $this->uris[] = (string) $request->getUri();

            return new Response(200, [], '{}');
        });

        return new Psr18Transport($client, new RequestFactory(), new StreamFactory(), 'http://consul.local:8500');
    }

    public function testArrayValueBecomesRepeatedKeys(): void
    {
        $this->transport()->get('/v1/health/node/node1', ['node-meta' => ['rack=2', 'zone=a']]);

        $this->assertSame(
            'http://consul.local:8500/v1/health/node/node1?node-meta=rack%3D2&node-meta=zone%3Da',
            $this->uris[0]
        );
    }

    public function testMixedScalarsAndArrayKeepOrder(): void
    {
        $this->transport()->get('/v1/connect/intentions/match', ['by' => 'source', 'name' => ['web', 'api']]);

        $this->assertSame(
            'http://consul.local:8500/v1/connect/intentions/match?by=source&name=web&name=api',
            $this->uris[0]
        );
    }

    public function testScalarOnlyQueryKeepsHttpBuildQueryOutput(): void
    {
        // 不含数组时不改用自建编码，保证既有调用方的 URL 逐字节不变
        $this->transport()->get('/v1/kv/app', ['recurse' => 'true', 'index' => 5]);

        $this->assertSame('http://consul.local:8500/v1/kv/app?recurse=true&index=5', $this->uris[0]);
    }

    public function testEmptyArrayValueLeavesNoDanglingQuestionMark(): void
    {
        $this->transport()->get('/v1/health/node/node1', ['node-meta' => []]);

        $this->assertSame('http://consul.local:8500/v1/health/node/node1', $this->uris[0]);
    }

    public function testArrayValuesAreRawUrlEncoded(): void
    {
        $this->transport()->get('/v1/kv/app', ['node-meta' => ['env=prod 1']]);

        $this->assertSame('http://consul.local:8500/v1/kv/app?node-meta=env%3Dprod%201', $this->uris[0]);
    }
}
