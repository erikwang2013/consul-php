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

    /** @param array<int, mixed> $options 额外的 curl_setopt 选项，键为 CURLOPT_* 常量 */
    public function __construct(float $connectTimeout = 3.0, float $timeout = 30.0, array $options = [])
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
            CURLOPT_TIMEOUT_MS => (int) round($this->timeout * 1000),
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
            throw new HttpClientException(sprintf('cURL 请求失败[%d]：%s', $errno, $error !== '' ? $error : '未知错误'));
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
}
