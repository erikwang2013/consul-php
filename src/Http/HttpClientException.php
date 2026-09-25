<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Http;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * 内置 cURL 客户端的传输失败（DNS、连接、超时等）。
 *
 * 按 PSR-18 要求实现 ClientExceptionInterface；HTTP 4xx/5xx 不算传输错误，不走这里。
 */
class HttpClientException extends RuntimeException implements ClientExceptionInterface
{
}
