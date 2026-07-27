<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Service\LoadBalancer;

interface LoadBalancerInterface
{
    public function select(array $instances): ?array;
}
