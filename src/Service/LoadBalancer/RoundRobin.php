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
        $n = count($instances);
        $index = $this->count % $n;
        $this->count++;

        if ($this->count > 1000000000) {
            $this->count = 0;
        }

        return $instances[$index];
    }
}
