<?php

namespace Erikwang2013\Consul\Tests\Http;

use Erikwang2013\Consul\Http\CurlClient;
use Erikwang2013\Consul\Http\HttpClientException;
use Erikwang2013\Consul\Http\Request;
use Erikwang2013\Consul\Http\RequestFactory;
use PHPUnit\Framework\TestCase;

/**
 * 内置 cURL 客户端对着本地 php -S 起的真服务器跑一遍，覆盖请求组装、响应解析与错误映射。
 */
class CurlClientTest extends TestCase
{
    /** @var resource|null */
    private $server;

    private string $router = '';

    private string $baseUri = '';

    protected function setUp(): void
    {
        if (!\extension_loaded('curl') || PHP_BINARY === '') {
            $this->markTestSkipped('需要 curl 扩展与 php 二进制');
        }

        $this->router = sys_get_temp_dir() . '/consul-php-router-' . getmypid() . '.php';
        file_put_contents($this->router, <<<'PHP'
            <?php
            header('Content-Type: application/json');
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if ($path === '/v1/kv/app') {
                echo json_encode([['Key' => 'app/db', 'Value' => base64_encode('mysql')]]);
                return;
            }
            if ($path === '/v1/agent/service/register') {
                $body = file_get_contents('php://input');
                echo json_encode(['method' => $_SERVER['REQUEST_METHOD'], 'token' => $_SERVER['HTTP_X_CONSUL_TOKEN'] ?? '', 'body' => $body]);
                return;
            }
            if ($path === '/v1/status/leader') {
                echo json_encode('127.0.0.1:8300');
                return;
            }
            if ($path === '/v1/boom') {
                http_response_code(500);
                echo json_encode(['error' => 'boom']);
                return;
            }
            http_response_code(404);
            echo json_encode(['error' => 'not found']);
            PHP);

        $port = random_int(20000, 45000);
        $command = [PHP_BINARY, '-S', "127.0.0.1:{$port}", $this->router];
        $this->server = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->baseUri = "http://127.0.0.1:{$port}";

        // 等服务器起来
        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
            if ($socket !== false) {
                fclose($socket);

                return;
            }
            usleep(100000);
        }

        $this->markTestSkipped('本地 php -S 未能启动');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }

        if ($this->router !== '' && is_file($this->router)) {
            unlink($this->router);
        }
    }

    public function testGetReturnsParsedResponse(): void
    {
        $response = (new CurlClient())->sendRequest($this->request('GET', '/v1/kv/app'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame([['Key' => 'app/db', 'Value' => base64_encode('mysql')]], json_decode((string) $response->getBody(), true));
    }

    public function testPutSendsBodyAndHeaders(): void
    {
        $request = $this->request('PUT', '/v1/agent/service/register')
            ->withHeader('X-Consul-Token', 'acl-token')
            ->withBody((new \Erikwang2013\Consul\Http\StreamFactory())->createStream('{"Name":"web"}'));

        $payload = json_decode((string) (new CurlClient())->sendRequest($request)->getBody(), true);

        $this->assertSame('PUT', $payload['method']);
        $this->assertSame('acl-token', $payload['token']);
        $this->assertSame('{"Name":"web"}', $payload['body']);
    }

    public function testServerErrorIsNotAnException(): void
    {
        // PSR-18 规定 4xx/5xx 不算传输失败，交回给调用方判断
        $response = (new CurlClient())->sendRequest($this->request('GET', '/v1/boom'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Internal Server Error', $response->getReasonPhrase());
    }

    public function testTransportFailureThrowsClientExceptionInterface(): void
    {
        $this->expectException(HttpClientException::class);

        // 关掉的端口：连接失败必须是 PSR-18 的 ClientExceptionInterface
        (new CurlClient(0.5, 1.0))->sendRequest(new Request('GET', 'http://127.0.0.1:1/v1/status/leader'));
    }

    /** 内置客户端 + 内置工厂走完整链路：ConsulClient → Transport → cURL → 本地服务器 */
    public function testConsulClientWorksWithOnlyBuiltInHttpLayer(): void
    {
        $client = new \Erikwang2013\Consul\Client\ConsulClient(
            ['base_uri' => $this->baseUri],
            new CurlClient(),
            new \Erikwang2013\Consul\Http\RequestFactory(),
            new \Erikwang2013\Consul\Http\StreamFactory()
        );

        $this->assertSame('127.0.0.1:8300', $client->status->leader());

        // 404 也要按既有语义抛出，说明状态码仍由传输层判定
        $this->expectException(\Erikwang2013\Consul\Exception\NotFoundException::class);
        $client->kv->get('missing');
    }

    private function request(string $method, string $path): Request
    {
        return (new RequestFactory())->createRequest($method, $this->baseUri . $path);
    }
}
