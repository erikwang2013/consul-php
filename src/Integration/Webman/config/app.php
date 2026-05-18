<?php

return [
    'enable'   => true,
    'base_uri' => getenv('CONSUL_BASE_URI') ?: 'http://127.0.0.1:8500',
    'token'    => getenv('CONSUL_TOKEN') ?: '',
    'cache'    => [
        'enable' => true,
        'ttl'    => 300,
    ],
];
