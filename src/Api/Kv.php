<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

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
        $this->transport->delete('/v1/kv/' . $this->encodeKey($key), $options);
        return true;
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

    private function buildQuery(array $options): array
    {
        $query = [];
        if (isset($options['dc']))          $query['dc'] = $options['dc'];
        if (isset($options['index']))       $query['index'] = $options['index'];
        if (isset($options['wait']))        $query['wait'] = $options['wait'];
        if (isset($options['ns']))          $query['ns'] = $options['ns'];
        if (isset($options['partition']))   $query['partition'] = $options['partition'];
        if (isset($options['raw']))         $query['raw'] = 'true';
        if (isset($options['cas']))         $query['cas'] = $options['cas'];
        return $query;
    }
}
