<?php

declare(strict_types=1);

use Yiisoft\Cache\Memcached\Memcached;

return [
    'yiisoft/cache-memcached' => [
        'memcached' => [
            'persistentId' => '',
            'servers' => [
                [
                    'host' => Memcached::DEFAULT_SERVER_HOST,
                    'port' => Memcached::DEFAULT_SERVER_PORT,
                    'weight' => Memcached::DEFAULT_SERVER_WEIGHT,
                ],
            ],
        ],
    ],
];
