<?php

declare(strict_types=1);

namespace Yiisoft\Cache\Memcached;

final class InvalidArgumentException extends \RuntimeException implements \Psr\SimpleCache\InvalidArgumentException
{
}
