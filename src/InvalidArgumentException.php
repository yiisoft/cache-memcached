<?php

declare(strict_types=1);

namespace Yiisoft\Cache\Memcached;

class InvalidArgumentException extends \RuntimeException implements \Psr\SimpleCache\InvalidArgumentException
{
}
