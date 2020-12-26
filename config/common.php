<?php

declare(strict_types=1);

use Yiisoft\Cache\Memcached\Memcached;

/* @var $params array */

return [
    Memcached::class => [
        '__class' => Memcached::class,
        '__construct()' => [
            $params['yiisoft/cache-memcached']['memcached']['persistentId'],
            $params['yiisoft/cache-memcached']['memcached']['servers'],
        ],
    ],
];
