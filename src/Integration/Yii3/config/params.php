<?php

declare(strict_types=1);

// yiisoft/config 会把这段并进应用的 params，顶层键用本包的包名（与其它 Yii3 包一致）
return [
    'erikwang2013/consul-php' => [
        'consul' => [
            'base_uri' => getenv('CONSUL_BASE_URI') ?: 'http://127.0.0.1:8500',
            'token'    => getenv('CONSUL_TOKEN') ?: '',
            'cache'    => [
                'enable' => true,
                'ttl'    => 300,
            ],
        ],
    ],
];
