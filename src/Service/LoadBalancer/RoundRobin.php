<?php

namespace Erikwang\Consul\Service\LoadBalancer;

class RoundRobin implements LoadBalancerInterface
{
    private int $count = 0;

    public function select(array $instances): ?array
    {
        if (empty($instances)) {
            return null;
        }

        $index = $this->count % count($instances);
        $this->count++;

        return array_values($instances)[$index];
    }
}
