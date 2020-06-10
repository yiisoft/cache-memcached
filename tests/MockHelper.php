<?php

declare(strict_types=1);

namespace Yiisoft\Cache\Memcached;

/**
 * Mock for the time() function
 * @return int
 */
function time(): int
{
    return MockHelper::$time ?: \time();
}

class MockHelper
{
    /**
     * @var int virtual time to be returned by mocked time() function.
     * null means normal time() behavior.
     */
    public static $time;
}
