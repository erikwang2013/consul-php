<?php

namespace Erikwang\Consul\Transport;

interface TransportInterface
{
    public function get(string $path, array $query = []): array;
    public function put(string $path, array $body = [], array $query = []): array;
    public function post(string $path, array $body = [], array $query = []): array;
    public function delete(string $path, array $query = []): array;
}
