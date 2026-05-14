<?php

namespace Erikwang\Consul\Client;

use Erikwang\Consul\Api\Acl;
use Erikwang\Consul\Api\Agent;
use Erikwang\Consul\Api\Catalog;
use Erikwang\Consul\Api\Coordinate;
use Erikwang\Consul\Api\Event;
use Erikwang\Consul\Api\Health;
use Erikwang\Consul\Api\Kv;
use Erikwang\Consul\Api\Operator;
use Erikwang\Consul\Api\Session;
use Erikwang\Consul\Api\Snapshot;
use Erikwang\Consul\Api\Status;
use Erikwang\Consul\Config\ConfigCenter;
use Erikwang\Consul\Service\Discovery;
use Erikwang\Consul\Service\Registry;
use Erikwang\Consul\Transport\Psr18Transport;
use Erikwang\Consul\Transport\TransportInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class ConsulClient
{
    private TransportInterface $transport;

    private ?Kv $kv = null;
    private ?Agent $agent = null;
    private ?Catalog $catalog = null;
    private ?Health $health = null;
    private ?Session $session = null;
    private ?Acl $acl = null;
    private ?Event $event = null;
    private ?Status $status = null;
    private ?Coordinate $coordinate = null;
    private ?Operator $operator = null;
    private ?Snapshot $snapshot = null;
    private ?Registry $serviceRegistry = null;
    private ?Discovery $serviceDiscovery = null;
    private ?ConfigCenter $configCenter = null;

    private ?CacheInterface $cache;
    private ?EventDispatcherInterface $eventDispatcher;

    public function __construct(
        array $config = [],
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?LoggerInterface $logger = null,
        ?CacheInterface $cache = null,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $baseUri = $config['base_uri'] ?? 'http://127.0.0.1:8500';

        if ($httpClient === null) {
            $httpClient = $this->discoverHttpClient();
        }
        if ($requestFactory === null) {
            $requestFactory = $this->discoverRequestFactory();
        }
        if ($streamFactory === null) {
            $streamFactory = $this->discoverStreamFactory();
        }

        $this->transport = new Psr18Transport(
            $httpClient,
            $requestFactory,
            $streamFactory,
            $baseUri,
            $logger
        );

        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function __get(string $name)
    {
        return match ($name) {
            'kv'        => $this->kv ??= new Kv($this->transport),
            'agent'     => $this->agent ??= new Agent($this->transport),
            'catalog'   => $this->catalog ??= new Catalog($this->transport),
            'health'    => $this->health ??= new Health($this->transport),
            'session'   => $this->session ??= new Session($this->transport),
            'acl'       => $this->acl ??= new Acl($this->transport),
            'event'     => $this->event ??= new Event($this->transport),
            'status'    => $this->status ??= new Status($this->transport),
            'coordinate' => $this->coordinate ??= new Coordinate($this->transport),
            'operator'  => $this->operator ??= new Operator($this->transport),
            'snapshot'  => $this->snapshot ??= new Snapshot($this->transport),
            default     => throw new \RuntimeException("Unknown API module: {$name}"),
        };
    }

    public function serviceRegistry(): Registry
    {
        return $this->serviceRegistry ??= new Registry($this->__get('agent'));
    }

    public function serviceDiscovery(): Discovery
    {
        return $this->serviceDiscovery ??= new Discovery($this->__get('health'), $this->cache);
    }

    public function configCenter(): ConfigCenter
    {
        return $this->configCenter ??= new ConfigCenter(
            $this->__get('kv'),
            $this->cache,
            300,
            $this->eventDispatcher
        );
    }

    private function discoverHttpClient(): ClientInterface
    {
        if (class_exists(\Http\Discovery\Psr18ClientDiscovery::class)) {
            return \Http\Discovery\Psr18ClientDiscovery::find();
        }
        throw new \RuntimeException(
            'No PSR-18 HTTP client found. Require php-http/discovery and guzzlehttp/guzzle, or inject manually.'
        );
    }

    private function discoverRequestFactory(): RequestFactoryInterface
    {
        if (class_exists(\Http\Discovery\Psr17FactoryDiscovery::class)) {
            return \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory();
        }
        throw new \RuntimeException('No PSR-17 request factory found.');
    }

    private function discoverStreamFactory(): StreamFactoryInterface
    {
        if (class_exists(\Http\Discovery\Psr17FactoryDiscovery::class)) {
            return \Http\Discovery\Psr17FactoryDiscovery::findStreamFactory();
        }
        throw new \RuntimeException('No PSR-17 stream factory found.');
    }
}
