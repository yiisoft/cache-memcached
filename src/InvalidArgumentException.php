<?php

declare(strict_types=1);

namespace Yiisoft\Cache\Memcached;

use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;
use RuntimeException;

final class InvalidArgumentException extends RuntimeException implements PsrInvalidArgumentException {}
