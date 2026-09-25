<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Support\Boolean;
use Erikwang2013\Consul\Transport\TransportInterface;

class Kv
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    public function get(string $key, array $options = []): ?array
    {
        if (isset($options['raw'])) {
            $raw = $this->transport->getRaw('/v1/kv/' . $this->encodeKey($key), $this->buildQuery($options));
            return ['body' => $raw];
        }
        $query = $this->buildQuery($options);
        $result = $this->transport->get('/v1/kv/' . $this->encodeKey($key), $query);
        return !empty($result) ? $result[0] : null;
    }

    public function all(string $prefix = '', array $options = []): array
    {
        $query = $this->buildQuery($options);
        return $this->transport->get('/v1/kv/' . $this->encodeKey($prefix), array_merge($query, ['recurse' => 'true']));
    }

    public function put(string $key, string $value, array $options = []): bool
    {
        $response = $this->transport->putRaw('/v1/kv/' . $this->encodeKey($key), $value, $options);
        return ($response['body'] ?? null) === true;
    }

    public function delete(string $key, array $options = []): bool
    {
        $response = $this->transport->delete('/v1/kv/' . $this->encodeKey($key), $options);
        // 与 put() 一致：DELETE /v1/kv/:key?cas=N 在 CAS 失败时仍返回 200，body 为 false
        return ($response['body'] ?? null) === true;
    }

    public function keys(string $prefix = '', string $separator = ''): array
    {
        $query = [];
        if ($separator !== '') {
            $query['separator'] = $separator;
        }
        return $this->transport->get('/v1/kv/' . $this->encodeKey($prefix), array_merge($query, ['keys' => 'true']));
    }

    /**
     * URL-encode a Consul KV key, preserving path separators (public: used by Config\Watcher).
     */
    public function encodeKey(string $key): string
    {
        return str_replace('%2F', '/', rawurlencode($key));
    }

    /**
     * Decode a Consul KV item value (base64), falling back to the raw value
     * when it is not valid base64 (public: used by Config\Watcher and Config\ConfigCenter).
     */
    public function decodeValue(array $item): string
    {
        $raw = $item['Value'] ?? '';
        $decoded = base64_decode($raw, true);
        return $decoded !== false ? $decoded : $raw;
    }

    private function buildQuery(array $options): array
    {
        $query = array_intersect_key($options, array_flip(['dc', 'index', 'wait', 'ns', 'partition', 'cas']));

        // 一致性参数（上游 agent/http.go 的 parseConsistency）：stale 跟随者读降延迟、consistent 走 leader。
        // 归一成字符串 "true"——上游是 `b.Get("stale") == "true"`，布尔 true 会被编成 stale=1 而读不到。
        foreach (['stale', 'consistent'] as $flag) {
            if (Boolean::isTrue($options[$flag] ?? false)) {
                $query[$flag] = 'true';
            }
        }

        if (isset($options['raw'])) {
            $query['raw'] = 'true';
        }
        return $query;
    }
}
