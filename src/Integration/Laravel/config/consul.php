<?php

declare(strict_types=1);

return [
    'base_uri' => env('CONSUL_BASE_URI', 'http://127.0.0.1:8500'),
    'token'    => env('CONSUL_TOKEN', ''),
    'cache'    => [
        'enable' => true,
        'ttl'    => 300,
    ],
    // php artisan consul:watch 默认监听的前缀
    'watch_prefix' => env('CONSUL_WATCH_PREFIX', 'app/'),
];
