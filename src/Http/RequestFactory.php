<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Http;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/** 最小可用的 PSR-17 请求工厂。 */
final class RequestFactory implements RequestFactoryInterface
{
    /** @param string|UriInterface $uri */
    public function createRequest(string $method, $uri): RequestInterface
    {
        $request = new Request($method, $uri);

        $host = $request->getUri()->getHost();
        if ($host !== '') {
            $port = $request->getUri()->getPort();

            return $request->withHeader('Host', $port === null ? $host : $host . ':' . $port);
        }

        return $request;
    }
}
