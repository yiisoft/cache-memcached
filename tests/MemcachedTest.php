<?php

declare(strict_types=1);

namespace Yiisoft\Cache\Memcached\Tests;

use ArrayIterator;
use DateInterval;
use Exception;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use ReflectionException;
use ReflectionClass;
use ReflectionObject;
use stdClass;
use Yiisoft\Cache\Memcached\CacheException;
use Yiisoft\Cache\Memcached\Memcached;

use function array_keys;
use function array_map;
use function extension_loaded;
use function is_array;
use function is_object;
use function stream_socket_client;
use function time;

final class MemcachedTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('memcached')) {
            self::markTestSkipped('Required extension "memcached" is not loaded');
        }

        // Check whether memcached is running and skip tests if not.
        if (!@stream_socket_client(MEMCACHED_HOST . ':' . MEMCACHED_PORT, $errorNumber, $errorDescription, 0.5)) {
            self::markTestSkipped('No memcached server running at ' . MEMCACHED_HOST . ':' . MEMCACHED_PORT . ' : ' . $errorNumber . ' - ' . $errorDescription);
        }
    }

    public function dataProvider(): array
    {
        $object = new stdClass();
        $object->test_field = 'test_value';

        return [
            'integer' => ['test_integer', 1],
            'double' => ['test_double', 1.1],
            'string' => ['test_string', 'a'],
            'boolean_true' => ['test_boolean_true', true],
            'boolean_false' => ['test_boolean_false', false],
            'object' => ['test_object', $object],
            'array' => ['test_array', ['test_key' => 'test_value']],
            'null' => ['test_null', null],
            'supported_key_characters' => ['AZaz09_.', 'b'],
            '64_characters_key_max' => ['bVGEIeslJXtDPrtK.hgo6HL25_.1BGmzo4VA25YKHveHh7v9tUP8r5BNCyLhx4zy', 'c'],
            'string_with_number_key' => ['111', 11],
            'string_with_number_key_1' => ['022', 22],
        ];
    }

    /**
     * @dataProvider dataProvider
     *
     * @param $key
     * @param $value
     *
     * @throws InvalidArgumentException
     */
    public function testSet($key, $value): void
    {
        $cache = $this->createCacheInstance();

        for ($i = 0; $i < 2; $i++) {
            $this->assertTrue($cache->set($key, $value));
        }
    }

    /**
     * @dataProvider dataProvider
     *
     * @param $key
     * @param $value
     *
     * @throws InvalidArgumentException
     */
    public function testGet($key, $value): void
    {
        $cache = $this->createCacheInstance();
        $cache->set($key, $value);
        $valueFromCache = $cache->get($key, 'default');

        $this->assertSameExceptObject($value, $valueFromCache);
    }

    /**
     * @dataProvider dataProvider
     *
     * @param $key
     * @param $value
     *
     * @throws InvalidArgumentException
     */
    public function testValueInCacheCannotBeChanged($key, $value): void
    {
        $cache = $this->createCacheInstance();
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
     *
     * @param $key
     * @param $value
     *
     * @throws InvalidArgumentException
     */
    public function testHas($key, $value): void
    {
        $cache = $this->createCacheInstance();
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

        $this->assertNull($cache->get('non_existent_key'));
    }

    /**
     * @dataProvider dataProvider
     *
     * @param $key
     * @param $value
     *
     * @throws InvalidArgumentException
     */
    public function testDelete($key, $value): void
    {
        $cache = $this->createCacheInstance();
        $cache->set($key, $value);

        $this->assertSameExceptObject($value, $cache->get($key));
        $this->assertTrue($cache->delete($key));
        $this->assertNull($cache->get($key));
    }

    /**
     * @dataProvider dataProvider
     *
     * @param $key
     * @param $value
     *
     * @throws InvalidArgumentException
     */
    public function testClear($key, $value): void
    {
        $cache = $this->createCacheInstance();

        foreach ($this->dataProvider() as $datum) {
            $cache->set($datum[0], $datum[1]);
        }

        $this->assertTrue($cache->clear());
        $this->assertNull($cache->get($key));
    }

    /**
     * @dataProvider dataProviderSetMultiple
     *
     * @param int|null $ttl
     *
     * @throws InvalidArgumentException
     */
    public function testSetMultiple(?int $ttl): void
    {
        $cache = $this->createCacheInstance();
        $data = $this->getDataProviderData();
        $cache->setMultiple($data, $ttl);

        foreach ($data as $key => $value) {
            $this->assertSameExceptObject($value, $cache->get((string) $key));
        }
    }

    /**
     * @return array testing multiSet with and without expiry
     */
    public function dataProviderSetMultiple(): array
    {
        return [[null], [2]];
    }

    public function testGetMultiple(): void
    {
        $cache = $this->createCacheInstance();
        $data = $this->getDataProviderData();
        $cache->setMultiple($data);

        $this->assertSameExceptObject($data, $cache->getMultiple(array_map('\strval', array_keys($data))));
    }

    public function testDeleteMultiple(): void
    {
        $cache = $this->createCacheInstance();
        $data = $this->getDataProviderData();
        $keys = array_map('\strval', array_keys($data));
        $cache->setMultiple($data);

        $this->assertSameExceptObject($data, $cache->getMultiple($keys));

        $cache->deleteMultiple($keys);
        $emptyData = array_map(static fn () => null, $data);

        $this->assertSameExceptObject($emptyData, $cache->getMultiple($keys));
    }

    public function testExpire(): void
    {
        $ttl = 2;
        $cache = $this->createCacheInstance();
        $memcached = $this->createPartialMock(\Memcached::class, ['set']);

        $memcached->expects($this->once())
            ->method('set')
            ->with($this->equalTo('key'), $this->equalTo('value'), $this->equalTo($ttl))
            ->willReturn(true);

        $this->setInaccessibleProperty($cache, 'cache', $memcached);
        $cache->set('key', 'value', $ttl);
    }

    public function testZeroAndNegativeTtl(): void
    {
        $cache = $this->createCacheInstance();
        $cache->setMultiple(['a' => 1, 'b' => 2]);

        $this->assertTrue($cache->has('a'));
        $this->assertTrue($cache->has('b'));

        $cache->set('a', 11, -1);
        $this->assertFalse($cache->has('a'));

        $cache->set('b', 22, 0);
        $this->assertFalse($cache->has('b'));
    }

    /**
     * Data provider for {@see testNormalizeTtl()}
     *
     * @throws Exception
     *
     * @return array test data
     */
    public function dataProviderNormalizeTtl(): array
    {
        return [
            [123, 123],
            ['123', 123],
            [0, -1],
            ['', -1],
            [-1, -1],
            [-5, -1],
            [null, 0],
            [new DateInterval('PT6H8M'), 6 * 3600 + 8 * 60],
            [new DateInterval('P2Y4D'), 2 * 365 * 24 * 3600 + 4 * 24 * 3600],
        ];
    }

    /**
     * @dataProvider dataProviderNormalizeTtl
     *
     * @param mixed $ttl
     * @param mixed $expectedResult
     *
     * @throws ReflectionException
     */
    public function testNormalizeTtl($ttl, $expectedResult): void
    {
        $cache = $this->createCacheInstance();
        $ttl = $this->invokeMethod($cache, 'normalizeTtl', [$ttl]);
        $ttl = $ttl < 2592001 ? $ttl : $ttl - time();

        $this->assertSameExceptObject($expectedResult, $ttl);
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
                new ArrayIterator(['a' => 1, 'b' => 2,]),
            ],
            'IteratorAggregate' => [
                ['a' => 1, 'b' => 2,],
                new class() implements IteratorAggregate {
                    public function getIterator(): ArrayIterator
                    {
                        return new ArrayIterator(['a' => 1, 'b' => 2,]);
                    }
                },
            ],
            'generator' => [
                ['a' => 1, 'b' => 2,],
                (static function () {
                    yield 'a' => 1;
                    yield 'b' => 2;
                })(),
            ],
        ];
    }

    /**
     * @dataProvider iterableProvider
     *
     * @param array $array
     * @param iterable $iterable
     *
     * @throws InvalidArgumentException
     */
    public function testValuesAsIterable(array $array, iterable $iterable): void
    {
        $cache = $this->createCacheInstance();
        $cache->setMultiple($iterable);

        $this->assertSameExceptObject($array, $cache->getMultiple(array_keys($array)));
    }

    public function testGetCache(): void
    {
        $cache = $this->createCacheInstance();
        $memcached = $this->getInaccessibleProperty($cache, 'cache');

        $this->assertInstanceOf(\Memcached::class, $memcached);
    }

    public function testGetNewServers(): void
    {
        $cache = $this->createCacheInstance();

        $memcached = $this->createPartialMock(\Memcached::class, ['getServerList']);
        $memcached->method('getServerList')->willReturn([['host' => '1.1.1.1', 'port' => 11211]]);

        $this->setInaccessibleProperty($cache, 'cache', $memcached);

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
            ['host' => '1.1.1.1', 'port' => 11211, 'weight' => 1],
            ['host' => '2.2.2.2', 'port' => 11211, 'weight' => 1],
        ]);

        $memcached = $this->getInaccessibleProperty($cache, 'cache');

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

    public function testSetWithDateIntervalTtl(): void
    {
        $cache = $this->createCacheInstance();
        $cache->set('a', 1, new DateInterval('PT1H'));
        $this->assertSameExceptObject(1, $cache->get('a'));

        $cache->setMultiple(['b' => 2]);
        $this->assertSameExceptObject(['b' => 2], $cache->getMultiple(['b']));
    }

    public function testInitDefaultServer(): void
    {
        $memcached = $this->getInaccessibleProperty(new Memcached(), 'cache');

        $this->assertEquals([
            [
                'host' => '127.0.0.1',
                'port' => 11211,
                'type' => 'TCP',
            ],
        ], $memcached->getServerList());
    }

    public function testFailInitServers(): void
    {
        $this->expectException(CacheException::class);

        $cache = $this->createCacheInstance();

        $memcached = $this->createPartialMock(\Memcached::class, ['addServers']);
        $memcached->method('addServers')->willReturn(false);

        $this->setInaccessibleProperty($cache, 'cache', $memcached);
        $this->invokeMethod($cache, 'initServers', [[], '']);
    }

    public function invalidServersConfigProvider(): array
    {
        return [
            [[[]]],
            [[['1.1.1.1']]],
            [['host' => MEMCACHED_HOST]],
            [['port' => MEMCACHED_PORT]],
            [['host' => null, 'port' => MEMCACHED_PORT]],
            [['host' => MEMCACHED_HOST, 'port' => null]],
        ];
    }

    /**
     * @dataProvider invalidServersConfigProvider
     *
     * @param $servers
     */
    public function testConstructorThrowExceptionForInvalidServersConfig($servers): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->createCacheInstance('', $servers);
    }

    public function invalidKeyProvider(): array
    {
        return [
            'int' => [1],
            'float' => [1.1],
            'null' => [null],
            'bool' => [true],
            'object' => [new stdClass()],
            'callable' => [fn () => 'key'],
            'psr-reserved' => ['{}()/\@:'],
            'empty-string' => [''],
        ];
    }

    /**
     * @dataProvider invalidKeyProvider
     *
     * @param mixed $key
     */
    public function testGetThrowExceptionForInvalidKey($key): void
    {
        $cache = $this->createCacheInstance();
        $this->expectException(InvalidArgumentException::class);
        $cache->get($key);
    }

    /**
     * @dataProvider invalidKeyProvider
     *
     * @param mixed $key
     */
    public function testSetThrowExceptionForInvalidKey($key): void
    {
        $cache = $this->createCacheInstance();
        $this->expectException(InvalidArgumentException::class);
        $cache->set($key, 'value');
    }

    /**
     * @dataProvider invalidKeyProvider
     *
     * @param mixed $key
     */
    public function testDeleteThrowExceptionForInvalidKey($key): void
    {
        $cache = $this->createCacheInstance();
        $this->expectException(InvalidArgumentException::class);
        $cache->delete($key);
    }

    /**
     * @dataProvider invalidKeyProvider
     *
     * @param mixed $key
     */
    public function testGetMultipleThrowExceptionForInvalidKeys($key): void
    {
        $cache = $this->createCacheInstance();
        $this->expectException(InvalidArgumentException::class);
        $cache->getMultiple([$key]);
    }

    /**
     * @dataProvider invalidKeyProvider
     *
     * @param mixed $key
     */
    public function testGetMultipleThrowExceptionForInvalidKeysNotIterable($key): void
    {
        $cache = $this->createCacheInstance();
        $this->expectException(InvalidArgumentException::class);
        $cache->getMultiple($key);
    }

    /**
     * @dataProvider invalidKeyProvider
     *
     * @param mixed $key
     */
    public function testSetMultipleThrowExceptionForInvalidKeysNotIterable($key): void
    {
        $cache = $this->createCacheInstance();
        $this->expectException(InvalidArgumentException::class);
        $cache->setMultiple($key);
    }

    /**
     * @dataProvider invalidKeyProvider
     *
     * @param mixed $key
     */
    public function testDeleteMultipleThrowExceptionForInvalidKeys($key): void
    {
        $cache = $this->createCacheInstance();
        $this->expectException(InvalidArgumentException::class);
        $cache->deleteMultiple([$key]);
    }

    /**
     * @dataProvider invalidKeyProvider
     *
     * @param mixed $key
     */
    public function testDeleteMultipleThrowExceptionForInvalidKeysNotIterable($key): void
    {
        $cache = $this->createCacheInstance();
        $this->expectException(InvalidArgumentException::class);
        $cache->deleteMultiple($key);
    }

    /**
     * @dataProvider invalidKeyProvider
     *
     * @param mixed $key
     */
    public function testHasInvalidKey($key): void
    {
        $cache = $this->createCacheInstance();
        $this->expectException(InvalidArgumentException::class);
        $cache->has($key);
    }

    private function createCacheInstance($persistentId = '', array $servers = []): CacheInterface
    {
        if ($servers === []) {
            $servers[] = ['host' => MEMCACHED_HOST, 'port' => MEMCACHED_PORT];
        }

        return new Memcached($persistentId, $servers);
    }

    /**
     * Invokes a inaccessible method.
     *
     * @param $object
     * @param $method
     * @param array $args
     * @param bool $revoke whether to make method inaccessible after execution
     *
     * @throws ReflectionException
     *
     * @return mixed
     */
    private function invokeMethod($object, $method, array $args = [], bool $revoke = true)
    {
        $reflection = new ReflectionObject($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        $result = $method->invokeArgs($object, $args);

        if ($revoke) {
            $method->setAccessible(false);
        }

        return $result;
    }

    /**
     * Sets an inaccessible object property to a designated value.
     *
     * @param object $object
     * @param string $propertyName
     * @param mixed $value
     * @param bool $revoke whether to make property inaccessible after setting
     */
    private function setInaccessibleProperty(object $object, string $propertyName, $value, bool $revoke = true): void
    {
        $class = new ReflectionClass($object);

        while (!$class->hasProperty($propertyName)) {
            $class = $class->getParentClass();
        }

        $property = $class->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);

        if ($revoke) {
            $property->setAccessible(false);
        }
    }

    /**
     * Gets an inaccessible object property.
     *
     * @param object $object
     * @param string $propertyName
     * @param bool $revoke whether to make property inaccessible after getting
     *
     * @return mixed
     */
    private function getInaccessibleProperty(object $object, string $propertyName, bool $revoke = true)
    {
        $class = new ReflectionClass($object);

        while (!$class->hasProperty($propertyName)) {
            $class = $class->getParentClass();
        }

        $property = $class->getProperty($propertyName);
        $property->setAccessible(true);
        $result = $property->getValue($object);

        if ($revoke) {
            $property->setAccessible(false);
        }

        return $result;
    }

    private function getDataProviderData(): array
    {
        $dataProvider = $this->dataProvider();
        $data = [];

        foreach ($dataProvider as $item) {
            $data[$item[0]] = $item[1];
        }

        return $data;
    }

    private function assertSameExceptObject($expected, $actual): void
    {
        // Assert for all types.
        $this->assertEquals($expected, $actual);

        // No more asserts for objects.
        if (is_object($expected)) {
            return;
        }

        // Assert same for all types except objects and arrays that can contain objects.
        if (!is_array($expected)) {
            $this->assertSame($expected, $actual);
            return;
        }

        // Assert same for each element of the array except objects.
        foreach ($expected as $key => $value) {
            if (is_object($value)) {
                $this->assertEquals($expected[$key], $actual[$key]);
            } else {
                $this->assertSame($expected[$key], $actual[$key]);
            }
        }
    }
}
