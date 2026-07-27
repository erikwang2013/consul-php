<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Service\LoadBalancer;

class RoundRobin implements LoadBalancerInterface
{
    private int $count = 0;

    public function select(array $instances): ?array
    {
        if (empty($instances)) {
            return null;
        }

        $instances = array_values($instances);
        $index = $this->count % count($instances);
        $this->count++;

        return $instances[$index];
    }
}
