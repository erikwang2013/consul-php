<?php

namespace Erikwang2013\Consul\Integration\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class Consul extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Erikwang2013\Consul\Client\ConsulClient::class;
    }
}
