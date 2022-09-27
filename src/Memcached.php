<?php

declare(strict_types=1);

namespace Yiisoft\Cache\Memcached;

use DateInterval;
use DateTime;
use Psr\SimpleCache\CacheInterface;
use Traversable;

use function array_fill_keys;
use function array_key_exists;
use function array_keys;
use function array_map;
use function gettype;
use function is_array;
use function is_iterable;
use function is_string;
use function iterator_to_array;
use function strpbrk;
use function time;

/**
 * Memcached implements a cache application component based on
 * [memcached](http://pecl.php.net/package/memcached) PECL extension.
 *
 * Memcached can be configured with a list of memcached servers passed to the constructor.
 * By default, Memcached assumes there is a memcached server running on localhost at port 11211.
 *
 * See {@see \Psr\SimpleCache\CacheInterface} for common cache operations that MemCached supports.
 *
 * Note, there is no security measure to protected data in memcached.
 * All data in memcached can be accessed by any process running in the system.
 */
final class Memcached implements CacheInterface
{
    public const DEFAULT_SERVER_HOST = '127.0.0.1';
    public const DEFAULT_SERVER_PORT = 11211;
    public const DEFAULT_SERVER_WEIGHT = 1;

    private const TTL_INFINITY = 0;
    private const TTL_EXPIRED = -1;

    /**
     * @var \Memcached The Memcached instance.
     */
    private \Memcached $cache;

    /**
     * @param string $persistentId The ID that identifies the Memcached instance.
     * By default, the Memcached instances are destroyed at the end of the request.
     * To create an instance that persists between requests, use `persistentId` to specify a unique ID for the instance.
     * All instances created with the same persistent_id will share the same connection.
     * @param array $servers List of memcached servers that will be added to the server pool.
     *
     * @see https://www.php.net/manual/en/memcached.construct.php
     * @see https://www.php.net/manual/en/memcached.addservers.php
     */
    public function __construct(string $persistentId = '', array $servers = [])
    {
        $this->cache = new \Memcached($persistentId);
        $this->initServers($servers, $persistentId);
    }

    public function get($key, $default = null)
    {
        $this->validateKey($key);
        $value = $this->cache->get($key);

        if ($this->cache->getResultCode() === \Memcached::RES_SUCCESS) {
            return $value;
        }

        return $default;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->validateKey($key);
        $ttl = $this->normalizeTtl($ttl);

        if ($ttl <= self::TTL_EXPIRED) {
            return $this->delete($key);
        }

        return $this->cache->set($key, $value, $ttl);
    }

    public function delete($key): bool
    {
        $this->validateKey($key);
        return $this->cache->delete($key);
    }

    public function clear(): bool
    {
        return $this->cache->flush();
    }

    public function getMultiple($keys, $default = null): iterable
    {
        $keys = $this->iterableToArray($keys);
        $this->validateKeys($keys);
        $values = array_fill_keys($keys, $default);
        $valuesFromCache = $this->cache->getMulti($keys);

        foreach ($values as $key => $value) {
            $values[$key] = $valuesFromCache[$key] ?? $value;
        }

        return $values;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        $values = $this->iterableToArray($values);
        $this->validateKeysOfValues($values);
        return $this->cache->setMulti($values, $this->normalizeTtl($ttl));
    }

    public function deleteMultiple($keys): bool
    {
        $keys = $this->iterableToArray($keys);
        $this->validateKeys($keys);

        foreach ($this->cache->deleteMulti($keys) as $result) {
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    public function has($key): bool
    {
        $this->validateKey($key);
        $this->cache->get($key);
        return $this->cache->getResultCode() === \Memcached::RES_SUCCESS;
    }

    /**
     * Normalizes cache TTL handling `null` value, strings and {@see DateInterval} objects.
     *
     * @param DateInterval|int|string|null $ttl The raw TTL.
     *
     * @return int TTL value as UNIX timestamp.
     *
     * @see https://secure.php.net/manual/en/memcached.expiration.php
     */
    private function normalizeTtl($ttl): int
    {
        if ($ttl === null) {
            return self::TTL_INFINITY;
        }

        if ($ttl instanceof DateInterval) {
            $ttl = (new DateTime('@0'))
                ->add($ttl)
                ->getTimestamp();
        }

        $ttl = (int) $ttl;

        if ($ttl > 2_592_000) {
            return $ttl + time();
        }

        return $ttl > 0 ? $ttl : self::TTL_EXPIRED;
    }

    /**
     * Converts iterable to array. If provided value is not iterable it throws an InvalidArgumentException.
     *
     *
     */
    private function iterableToArray(mixed $iterable): array
    {
        if (!is_iterable($iterable)) {
            throw new InvalidArgumentException('Iterable is expected, got ' . gettype($iterable));
        }

        /** @psalm-suppress RedundantCast */
        return $iterable instanceof Traversable ? iterator_to_array($iterable) : (array) $iterable;
    }

    /**
     * @throws CacheException If an error occurred when adding servers to the server pool.
     * @throws InvalidArgumentException If the servers format is incorrect.
     */
    private function initServers(array $servers, string $persistentId): void
    {
        $servers = $this->normalizeServers($servers);

        if ($persistentId !== '') {
            $servers = $this->getNewServers($servers);
        }

        if (!$this->cache->addServers($servers)) {
            throw new CacheException('An error occurred while adding servers to the server pool.');
        }
    }

    /**
     * Returns the list of the servers that are not in the pool.
     *
     *
     */
    private function getNewServers(array $servers): array
    {
        $existingServers = [];
        $newServers = [];

        foreach ($this->cache->getServerList() as $existingServer) {
            $existingServers["{$existingServer['host']}:{$existingServer['port']}"] = true;
        }

        foreach ($servers as $server) {
            if (!array_key_exists("{$server[0]}:{$server[1]}", $existingServers)) {
                $newServers[] = $server;
            }
        }

        return $newServers;
    }

    /**
     * Validates and normalizes the format of the servers.
     *
     * @param array $servers The raw servers.
     *
     * @throws InvalidArgumentException If the servers format is incorrect.
     *
     * @return array The normalized servers.
     */
    private function normalizeServers(array $servers): array
    {
        $normalized = [];

        foreach ($servers as $server) {
            if (!is_array($server) || !isset($server['host'], $server['port'])) {
                throw new InvalidArgumentException(
                    'Each entry in servers parameter is supposed to be an array'
                    . ' containing hostname, port, and, optionally, weight of the server.',
                );
            }

            $normalized[] = [$server['host'], $server['port'], $server['weight'] ?? self::DEFAULT_SERVER_WEIGHT];
        }

        return $normalized ?: [[self::DEFAULT_SERVER_HOST, self::DEFAULT_SERVER_PORT, self::DEFAULT_SERVER_WEIGHT]];
    }

    private function validateKey(mixed $key): void
    {
        if (!is_string($key) || $key === '' || strpbrk($key, '{}()/\@:')) {
            throw new InvalidArgumentException('Invalid key value.');
        }
    }

    private function validateKeys(array $keys): void
    {
        foreach ($keys as $key) {
            $this->validateKey($key);
        }
    }

    private function validateKeysOfValues(array $values): void
    {
        $keys = array_map('\strval', array_keys($values));
        $this->validateKeys($keys);
    }
}
