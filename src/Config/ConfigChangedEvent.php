<?php

namespace Erikwang\Consul\Config;

class ConfigChangedEvent
{
    private string $prefix;
    private array $config;

    public function __construct(string $prefix, array $config)
    {
        $this->prefix = $prefix;
        $this->config = $config;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
