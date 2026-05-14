<?php

namespace Erikwang\Consul\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class Consul extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Erikwang\Consul\Client\ConsulClient::class;
    }
}
