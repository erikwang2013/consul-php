<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Transport;

interface TransportInterface
{
    public function get(string $path, array $query = []): array;
    public function put(string $path, array $body = [], array $query = []): array;
    public function post(string $path, array $body = [], array $query = []): array;
    public function delete(string $path, array $query = []): array;

    /**
     * 带 JSON body 的 DELETE。Consul 有端点（如 /v1/operator/keyring）用 DELETE 但只从 body 读参数，
     * 不带 body 会被服务端以 400 拒绝。
     */
    public function deleteWithBody(string $path, array $body = [], array $query = []): array;

    public function getRaw(string $path, array $query = []): string;
    public function putRaw(string $path, string $body, array $query = []): array;
    public function getWithHeaders(string $path, array $query = []): array;
}
