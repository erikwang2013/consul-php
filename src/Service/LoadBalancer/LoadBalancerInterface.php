<?php

namespace Erikwang\Consul\Service\LoadBalancer;

interface LoadBalancerInterface
{
    public function select(array $instances): ?array;
}
