<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Http;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * 网络层失败（DNS、连接被拒、超时），按 PSR-18 实现 NetworkExceptionInterface——
 * 调用方可以据此区分"网络不通"与"服务端返回了错误状态"。
 */
final class NetworkException extends HttpClientException implements NetworkExceptionInterface
{
    private RequestInterface $request;

    public function __construct(string $message, RequestInterface $request, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->request = $request;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
