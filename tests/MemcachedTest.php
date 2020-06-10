<?php

declare(strict_types=1);

namespace Yiisoft\Cache\Memcached\Tests;

require_once __DIR__ . '/MockHelper.php';

use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use ReflectionException;
use Yiisoft\Cache\Memcached\CacheException;
use Yiisoft\Cache\Memcached\Memcached;
use Yiisoft\Cache\Memcached\MockHelper;

class MemcachedTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('memcached')) {
            self::markTestSkipped('Required extension "memcached" is not loaded');
        }

        // check whether memcached is running and skip tests if not.
        if (!@stream_socket_client(MEMCACHED_HOST . ':' . MEMCACHED_PORT, $errorNumber, $errorDescription, 0.5)) {
            self::markTestSkipped('No memcached server running at ' . MEMCACHED_HOST . ':' . MEMCACHED_PORT . ' : ' . $errorNumber . ' - ' . $errorDescription);
        }
    }

    protected function tearDown(): void
    {
        MockHelper::$time = null;
    }

    protected function createCacheInstance($persistentId = '', array $servers = []): CacheInterface
    {
        if ($servers === []) {
            $servers = [[MEMCACHED_HOST, MEMCACHED_PORT]];
        }
        return new Memcached($persistentId, $servers);
    }

    public function testDeleteMultipleReturnsFalse(): void
    {
        $cache = $this->createCacheInstance();

        $memcachedStub = $this->createMock(\Memcached::class);
        $memcachedStub->method('deleteMulti')->willReturn([false]);

        $this->setInaccessibleProperty($cache, 'cache', $memcachedStub);

        $this->assertFalse($cache->deleteMultiple(['a', 'b']));
    }

    public function testExpire(): void
    {
        $ttl = 2;
        MockHelper::$time = \time();
        $expiration = MockHelper::$time + $ttl;

        $cache = $this->createCacheInstance();

        $memcached = $this->createMock(\Memcached::class);

        $memcached->expects($this->once())
            ->method('set')
            ->with($this->equalTo('key'), $this->equalTo('value'), $this->equalTo($expiration))
            ->willReturn(true);

        $this->setInaccessibleProperty($cache, 'cache', $memcached);

        $cache->set('key', 'value', $ttl);
    }

    /**
     * @dataProvider dataProvider
     * @param $key
     * @param $value
     * @throws InvalidArgumentException
     */
    public function testSet($key, $value): void
    {
        $cache = $this->createCacheInstance();
        $cache->clear();

        for ($i = 0; $i < 2; $i++) {
            $this->assertTrue($cache->set($key, $value));
        }
    }

    /**
     * @dataProvider dataProvider
     * @param $key
     * @param $value
     * @throws InvalidArgumentException
     */
    public function testGet($key, $value): void
    {
        $cache = $this->createCacheInstance();
        $cache->clear();

        $cache->set($key, $value);
        $valueFromCache = $cache->get($key, 'default');

        $this->assertSameExceptObject($value, $valueFromCache);
    }

    /**
     * @dataProvider dataProvider
     * @param $key
     * @param $value
     * @throws InvalidArgumentException
     */
    public function testValueInCacheCannotBeChanged($key, $value): void
    {
        $cache = $this->createCacheInstance();
        $cache->clear();

        $cache->set($key, $value);
        $valueFromCache = $cache->get($key, 'default');

        $this->assertSameExceptObject($value, $valueFromCache);

        if (is_object($value)) {
            $originalValue = clone $value;
            $valueFromCache->test_field = 'changed';
            $value->test_field = 'changed';
            $valueFromCacheNew = $cache->get($key, 'default');
            $this->assertSameExceptObject($originalValue, $valueFromCacheNew);
        }
    }

    /**
     * @dataProvider dataProvider
     * @param $key
     * @param $value
     * @throws InvalidArgumentException
     */
    public function testHas($key, $value): void
    {
        $cache = $this->createCacheInstance();
        $cache->clear();

        $cache->set($key, $value);

        $this->assertTrue($cache->has($key));
        // check whether exists affects the value
        $this->assertSameExceptObject($value, $cache->get($key));

        $this->assertTrue($cache->has($key));
        $this->assertFalse($cache->has('not_exists'));
    }

    public function testGetNonExistent(): void
    {
        $cache = $this->createCacheInstance();
        $cache->clear();

        $this->assertNull($cache->get('non_existent_key'));
    }

    /**
     * @dataProvider dataProvider
     * @param $key
     * @param $value
     * @throws InvalidArgumentException
     */
    public function testDelete($key, $value): void
    {
        $cache = $this->createCacheInstance();
        $cache->clear();

        $cache->set($key, $value);

        $this->assertSameExceptObject($value, $cache->get($key));
        $this->assertTrue($cache->delete($key));
        $this->assertNull($cache->get($key));
    }

    /**
     * @dataProvider dataProvider
     * @param $key
     * @param $value
     * @throws InvalidArgumentException
     */
    public function testClear($key, $value): void
    {
        $cache = $this->createCacheInstance();
        $cache = $this->prepare($cache);

        $this->assertTrue($cache->clear());
        $this->assertNull($cache->get($key));
    }

    /**
     * @dataProvider dataProviderSetMultiple
     * @param int|null $ttl
     * @throws InvalidArgumentException
     */
    public function testSetMultiple(?int $ttl): void
    {
        $cache = $this->createCacheInstance();
        $cache->clear();

        $data = $this->getDataProviderData();

        $cache->setMultiple($data, $ttl);

        foreach ($data as $key => $value) {
            $this->assertSameExceptObject($value, $cache->get((string)$key));
        }
    }

    /**
     * @return array testing multiSet with and without expiry
     */
    public function dataProviderSetMultiple(): array
    {
        return [
            [null],
            [2],
        ];
    }

    public function testGetMultiple(): void
    {
        $cache = $this->createCacheInstance();
        $cache->clear();

        $data = $this->getDataProviderData();

        $cache->setMultiple($data);

        $this->assertSameExceptObject($data, $cache->getMultiple(array_map('strval', array_keys($data))));
    }

    public function testDeleteMultiple(): void
    {
        $cache = $this->createCacheInstance();
        $cache->clear();

        $data = $this->getDataProviderData();
        $keys = array_map('strval', array_keys($data));

        $cache->setMultiple($data);

        $this->assertSameExceptObject($data, $cache->getMultiple($keys));

        $cache->deleteMultiple($keys);

        $emptyData = array_map(static function ($v) {
            return null;
        }, $data);

        $this->assertSameExceptObject($emptyData, $cache->getMultiple($keys));
    }

    public function testZeroAndNegativeTtl(): void
    {
        $cache = $this->createCacheInstance();
        $cache->clear();
        $cache->setMultiple([
            'a' => 1,
            'b' => 2,
        ]);

        $this->assertTrue($cache->has('a'));
        $this->assertTrue($cache->has('b'));

        $cache->set('a', 11, -1);

        $this->assertFalse($cache->has('a'));

        $cache->set('b', 22, 0);

        $this->assertFalse($cache->has('b'));
    }

    /**
     * @dataProvider dataProviderNormalizeTtl
     * @param mixed $ttl
     * @param mixed $expectedResult
     * @throws ReflectionException
     */
    public function testNormalizeTtl($ttl, $expectedResult): void
    {
        $cache = $this->createCacheInstance();
        $this->assertSameExceptObject($expectedResult, $this->invokeMethod($cache, 'normalizeTtl', [$ttl]));
    }

    /**
     * Data provider for {@see testNormalizeTtl()}
     * @return array test data
     *
     * @throws \Exception
     */
    public function dataProviderNormalizeTtl(): array
    {
        return [
            [123, 123],
            ['123', 123],
            ['', 0],
            [null, null],
            [0, 0],
            [new DateInterval('PT6H8M'), 6 * 3600 + 8 * 60],
            [new DateInterval('P2Y4D'), 2 * 365 * 24 * 3600 + 4 * 24 * 3600],
        ];
    }

    /**
     * @dataProvider ttlToExpirationProvider
     * @param mixed $ttl
     * @param mixed $expected
     * @throws ReflectionException
     */
    public function testTtlToExpiration($ttl, $expected): void
    {
        if ($expected === 'calculate_expiration') {
            MockHelper::$time = \time();
            $expected = MockHelper::$time + $ttl;
        }
        $cache = $this->createCacheInstance();
        $this->assertSameExceptObject($expected, $this->invokeMethod($cache, 'ttlToExpiration', [$ttl]));
    }

    public function ttlToExpirationProvider(): array
    {
        return [
            [3, 'calculate_expiration'],
            [null, 0],
            [-5, -1],
        ];
    }

    /**
     * @dataProvider iterableProvider
     * @param array $array
     * @param iterable $iterable
     * @throws InvalidArgumentException
     */
    public function testValuesAsIterable(array $array, iterable $iterable): void
    {
        $cache = $this->createCacheInstance();
        $cache->clear();

        $cache->setMultiple($iterable);

        $this->assertSameExceptObject($array, $cache->getMultiple(array_keys($array)));
    }

    public function iterableProvider(): array
    {
        return [
            'array' => [
                ['a' => 1, 'b' => 2,],
                ['a' => 1, 'b' => 2,],
            ],
            'ArrayIterator' => [
                ['a' => 1, 'b' => 2,],
                new \ArrayIterator(['a' => 1, 'b' => 2,]),
            ],
            'IteratorAggregate' => [
                ['a' => 1, 'b' => 2,],
                new class() implements \IteratorAggregate {
                    public function getIterator()
                    {
                        return new \ArrayIterator(['a' => 1, 'b' => 2,]);
                    }
                }
            ],
            'generator' => [
                ['a' => 1, 'b' => 2,],
                (static function () {
                    yield 'a' => 1;
                    yield 'b' => 2;
                })()
            ]
        ];
    }

    public function testGetCache(): void
    {
        $cache = $this->createCacheInstance();
        $memcached = $cache->getCache();
        $this->assertInstanceOf(\Memcached::class, $memcached);
    }

    public function testPersistentId(): void
    {
        $cache1 = $this->createCacheInstance();
        $memcached1 = $cache1->getCache();
        $this->assertFalse($memcached1->isPersistent());

        $cache2 = $this->createCacheInstance(microtime() . __METHOD__);
        $memcached2 = $cache2->getCache();
        $this->assertTrue($memcached2->isPersistent());
    }

    public function testGetNewServers(): void
    {
        $cache = $this->createCacheInstance();

        $memcachedStub = $this->createMock(\Memcached::class);
        $memcachedStub->method('getServerList')->willReturn([['host' => '1.1.1.1', 'port' => 11211]]);

        $this->setInaccessibleProperty($cache, 'cache', $memcachedStub);

        $newServers = $this->invokeMethod($cache, 'getNewServers', [
            [
                ['1.1.1.1', 11211, 1],
                ['2.2.2.2', 11211, 1],
            ],
        ]);

        $this->assertEquals([['2.2.2.2', 11211, 1]], $newServers);
    }

    public function testThatServerWeightIsOptional(): void
    {
        $cache = $this->createCacheInstance(microtime() . __METHOD__, [
            ['1.1.1.1', 11211, 1],
            ['2.2.2.2', 11211],
        ]);

        $memcached = $cache->getCache();
        $this->assertEquals([
            [
                'host' => '1.1.1.1',
                'port' => 11211,
                'type' => 'TCP',
            ],
            [
                'host' => '2.2.2.2',
                'port' => 11211,
                'type' => 'TCP',
            ],
        ], $memcached->getServerList());
    }

    /**
     * @dataProvider invalidServersConfigProvider
     * @param $servers
     */
    public function testInvalidServersConfig($servers): void
    {
        $this->expectException(CacheException::class);
        $cache = $this->createCacheInstance('', $servers);
    }

    public function invalidServersConfigProvider(): array
    {
        return [
            [[[]]],
            [[['1.1.1.1']]],
        ];
    }

    public function testSetWithDateIntervalTtl(): void
    {
        $cache = $this->createCacheInstance();
        $cache->clear();

        $cache->set('a', 1, new DateInterval('PT1H'));
        $this->assertSameExceptObject(1, $cache->get('a'));

        $cache->setMultiple(['b' => 2]);
        $this->assertSameExceptObject(['b' => 2], $cache->getMultiple(['b']));
    }

    public function testFailInitServers(): void
    {
        $this->expectException(CacheException::class);

        $cache = $this->createCacheInstance();

        $memcachedStub = $this->createMock(\Memcached::class);
        $memcachedStub->method('addServers')->willReturn(false);

        $this->setInaccessibleProperty($cache, 'cache', $memcachedStub);

        $this->invokeMethod($cache, 'initServers', [[]]);
    }

    public function testInitDefaultServer(): void
    {
        $cache = new Memcached();
        $memcached = $cache->getCache();
        $this->assertEquals([
            [
                'host' => '127.0.0.1',
                'port' => 11211,
                'type' => 'TCP',
            ],
        ], $memcached->getServerList());
    }

    public function testGetInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $cache = $this->createCacheInstance();
        $cache->get(1);
    }

    public function testSetInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $cache = $this->createCacheInstance();
        $cache->set(1, 1);
    }

    public function testDeleteInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $cache = $this->createCacheInstance();
        $cache->delete(1);
    }

    public function testGetMultipleInvalidKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $cache = $this->createCacheInstance();
        $cache->getMultiple([true]);
    }

    public function testGetMultipleInvalidKeysNotIterable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $cache = $this->createCacheInstance();
        $cache->getMultiple(1);
    }

    public function testSetMultipleInvalidKeysNotIterable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $cache = $this->createCacheInstance();
        $cache->setMultiple(1);
    }

    public function testDeleteMultipleInvalidKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $cache = $this->createCacheInstance();
        $cache->deleteMultiple([true]);
    }

    public function testDeleteMultipleInvalidKeysNotIterable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $cache = $this->createCacheInstance();
        $cache->deleteMultiple(1);
    }

    public function testHasInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $cache = $this->createCacheInstance();
        $cache->has(1);
    }
}
