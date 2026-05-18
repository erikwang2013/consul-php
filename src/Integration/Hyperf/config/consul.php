<?php

declare(strict_types=1);

return [
    'base_uri' => (function_exists('env') ? env('CONSUL_BASE_URI', 'http://127.0.0.1:8500') : (getenv('CONSUL_BASE_URI') ?: 'http://127.0.0.1:8500')),
    'token'    => (function_exists('env') ? env('CONSUL_TOKEN', '') : (getenv('CONSUL_TOKEN') ?: '')),
    'cache'    => [
        'enable' => true,
        'ttl'    => 300,
    ],
];
