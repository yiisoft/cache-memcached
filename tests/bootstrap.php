<?php

declare(strict_types=1);

use Yiisoft\Cache\Memcached\Memcached;

defined('MEMCACHED_HOST') || define('MEMCACHED_HOST', getenv('MEMCACHED_HOST') ?: Memcached::DEFAULT_SERVER_HOST);
defined('MEMCACHED_PORT') || define('MEMCACHED_PORT', getenv('MEMCACHED_PORT') ?: Memcached::DEFAULT_SERVER_PORT);
