<?php

declare(strict_types=1);

namespace Yiisoft\Cache\Memcached;

use Psr\SimpleCache\CacheException as PsrCacheException;
use RuntimeException;

final class CacheException extends RuntimeException implements PsrCacheException {}
