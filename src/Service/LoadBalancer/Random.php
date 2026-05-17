<?php

namespace Erikwang2013\Consul\Service\LoadBalancer;

class Random implements LoadBalancerInterface
{
    public function select(array $instances): ?array
    {
        if (empty($instances)) {
            return null;
        }

        return $instances[array_rand($instances)];
    }
}
