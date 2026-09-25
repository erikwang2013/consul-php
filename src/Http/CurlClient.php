<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * 基于 cURL 扩展的 PSR-18 客户端——让"零框架依赖"变成"零额外依赖"。
 *
 * 只在 php-http/discovery 找不到任何 PSR-18 实现时兜底，装了 Guzzle 之类仍然优先用它们。
 * 超时可调，其余 curl 选项可通过构造参数透传。
 */
final class CurlClient implements ClientInterface
{
    private float $connectTimeout;
    private float $timeout;

    /** @var array<int, mixed> */
    private array $options;

    /** @var \CurlShareHandle|resource|null 跨请求共享 DNS 与连接缓存 */
    private $share = null;

    /**
     * @param float $connectTimeout 连接超时（秒）
     * @param float $timeout        总超时（秒）；0 = 不限制，与 Guzzle 默认一致。
     *                              Consul 的阻塞查询会按 wait 持有连接（默认 30s + jitter），
     *                              总超时若小于它，长轮询必然被判为失败并降级。
     * @param array<int, mixed> $options 额外的 curl_setopt 选项，键为 CURLOPT_* 常量
     */
    public function __construct(float $connectTimeout = 3.0, float $timeout = 0.0, array $options = [])
    {
        $this->connectTimeout = $connectTimeout;
        $this->timeout = $timeout;
        $this->options = $options;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (!\extension_loaded('curl')) {
            throw new HttpClientException('内置 HTTP 客户端需要 curl 扩展；或注入一个 PSR-18 实现');
        }

        $handle = curl_init();
        if ($handle === false) {
            throw new HttpClientException('无法初始化 cURL 句柄');
        }

        $headers = [];
        $statusLine = '';
        $body = '';

        $curlOptions = [
            CURLOPT_URL => (string) $request->getUri(),
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_ENCODING => '',
            CURLOPT_CONNECTTIMEOUT_MS => (int) round($this->connectTimeout * 1000),
            // 不设总超时时，用低速阈值兜住"连上了但永远不返回"的连接：
            // 10 分钟内几乎零字节才中断，任何正常的阻塞查询都不会被它误杀
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME => 600,
            CURLOPT_HEADERFUNCTION => static function ($_, string $line) use (&$headers, &$statusLine): int {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    return strlen($line);
                }

                if (str_starts_with($trimmed, 'HTTP/')) {
                    // 新的响应开始（含 100 Continue），此前的头作废
                    $statusLine = $trimmed;
                    $headers = [];

                    return strlen($line);
                }

                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $key = strtolower(trim($name));
                    $headers[$key] ??= [trim($name), []];
                    $headers[$key][1][] = trim($value);
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($_, string $chunk) use (&$body): int {
                $body .= $chunk;

                return strlen($chunk);
            },
        ];

        $headerLines = [];
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headerLines[] = $name . ': ' . $value;
            }
        }
        if ($headerLines !== []) {
            $curlOptions[CURLOPT_HTTPHEADER] = $headerLines;
        }

        $payload = (string) $request->getBody();
        if ($payload !== '') {
            $curlOptions[CURLOPT_POSTFIELDS] = $payload;
        }

        if ($this->timeout > 0) {
            $curlOptions[CURLOPT_TIMEOUT_MS] = (int) round($this->timeout * 1000);
        }

        // 复用连接与 DNS 缓存：每个请求仍用独立句柄（协程下不会互相踩），
        // 只共享 libcurl 的连接池，省掉重复的 TCP/TLS 握手
        $share = $this->shareHandle();
        if ($share !== null && \defined('CURLOPT_SHARE')) {
            $curlOptions[CURLOPT_SHARE] = $share;
        }

        curl_setopt_array($handle, $curlOptions + $this->options);

        try {
            $ok = curl_exec($handle);
            $errno = curl_errno($handle);
            $error = curl_error($handle);
            $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($handle);
        }

        if ($ok === false || $errno !== 0) {
            throw new NetworkException(
                sprintf('cURL 请求失败[%d]：%s', $errno, $error !== '' ? $error : '未知错误'),
                $request,
                $errno
            );
        }

        $version = '1.1';
        $reason = '';
        $status = $code;
        if (preg_match('#^HTTP/([\d.]+)\s+(\d{3})\s*(.*)$#', $statusLine, $matches) === 1) {
            $version = $matches[1];
            $status = (int) $matches[2];
            $reason = $matches[3];
        }

        if ($status === 0) {
            throw new HttpClientException('cURL 未返回任何 HTTP 状态码');
        }

        // 内部结构是 [小写名 => [原始名, [值...]]]，Response 要的是 [原始名 => [值...]]
        $responseHeaders = [];
        foreach ($headers as $header) {
            $responseHeaders[$header[0]] = $header[1];
        }

        return new Response($status, $responseHeaders, $body, $version, $reason !== '' ? $reason : null);
    }

    /**
     * 懒初始化的 share 句柄。
     *
     * `CURL_LOCK_DATA_CONNECT` 需要 libcurl ≥ 7.57，常量缺失时静默降级为不共享——
     * 复用是优化，不是正确性前提。
     *
     * @return \CurlShareHandle|resource|null
     */
    private function shareHandle()
    {
        if ($this->share !== null) {
            return $this->share;
        }

        if (!\function_exists('curl_share_init') || !\defined('CURL_LOCK_DATA_CONNECT')) {
            return null;
        }

        $share = curl_share_init();
        curl_share_setopt($share, CURLSHOPT_SHARE, CURL_LOCK_DATA_CONNECT);
        curl_share_setopt($share, CURLSHOPT_SHARE, CURL_LOCK_DATA_DNS);

        return $this->share = $share;
    }
}
