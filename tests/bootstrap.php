<?php

declare(strict_types=1);

defined('MEMCACHED_HOST') || define('MEMCACHED_HOST', getenv('MEMCACHED_HOST') ?: \Yiisoft\Cache\Memcached\Memcached::DEFAULT_SERVER_HOST);
defined('MEMCACHED_PORT') || define('MEMCACHED_PORT', getenv('MEMCACHED_PORT') ?: \Yiisoft\Cache\Memcached\Memcached::DEFAULT_SERVER_PORT);
