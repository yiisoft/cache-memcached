<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px">
    </a>
    <h1 align="center">Yii Cache Library - Memcached Handler</h1>
    <br>
</p>

[![Latest Stable Version](https://poser.pugx.org/yiisoft/cache-memcached/v/stable.png)](https://packagist.org/packages/yiisoft/cache-memcached)
[![Total Downloads](https://poser.pugx.org/yiisoft/cache-memcached/downloads.png)](https://packagist.org/packages/yiisoft/cache-memcached)
[![Build status](https://github.com/yiisoft/cache-memcached/workflows/build/badge.svg)](https://github.com/yiisoft/cache-memcached/actions?query=workflow%3Abuild)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/cache-memcached/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/cache-memcached/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/cache-memcached/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/cache-memcached/?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fcache-memcached%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/cache-memcached/master)
[![static analysis](https://github.com/yiisoft/cache-memcached/workflows/static%20analysis/badge.svg)](https://github.com/yiisoft/cache-memcached/actions?query=workflow%3A%22static+analysis%22)
[![type-coverage](https://shepherd.dev/github/yiisoft/cache-memcached/coverage.svg)](https://shepherd.dev/github/yiisoft/cache-memcached)

This package provides the [Memcached](https://www.php.net/manual/book.memcached.php)
handler and implements [PSR-16](https://www.php-fig.org/psr/psr-16/) cache.

This option can be considered as the fastest one when dealing with a cache in
a distributed applications (e.g. with several servers, load balancers, etc.).

## Requirements

- PHP 8.0 or higher.
- `Memcached` PHP extension.

## Installation

The package could be installed with [composer](https://getcomposer.org/download/)

```shell
composer require yiisoft/cache-memcached
```

## Configuration

Creating an instance:

```php
$cache = new \Yiisoft\Cache\Memcached\Memcached($persistentId, $servers);
```

`$persistentId (string)` - The ID that identifies the Memcached instance is an empty string by default.
By default, the Memcached instances are destroyed at the end of the request.
To create an instance that persists between requests, use persistent_id to specify a unique ID for the instance.
All instances created with the same `$persistentId` will share the same connection.

For more information, see the description of the
[`\Memcached::__construct()`](https://www.php.net/manual/memcached.construct.php).

`$servers (array)` - List of memcached servers that will be added to the server pool.

List has the following structure:

```php
$servers => [
    [
        'host' => 'server-1',
        'port' => 11211,
        'weight' => 100,
    ],
    [
        'host' => 'server-2',
        'port' => 11211,
        'weight' => 50,
    ],
];
```

The default value:

```php
$servers => [
    [
        'host' => Memcached::DEFAULT_SERVER_HOST, // '127.0.0.1'
        'port' => Memcached::DEFAULT_SERVER_PORT, // 11211
        'weight' => Memcached::DEFAULT_SERVER_WEIGHT, // 1
    ],
];
```

For more information, see the description of the
[`\Memcached::addServers()`](https://www.php.net/manual/memcached.addservers.php).

## General usage

The package does not contain any additional functionality for interacting with the cache,
except those defined in the [PSR-16](https://www.php-fig.org/psr/psr-16/) interface.

```php
$cache = new \Yiisoft\Cache\Memcached\Memcached();
$parameters = ['user_id' => 42];
$key = 'demo';

// try retrieving $data from cache
$data = $cache->get($key);

if ($data === null) {
    // $data is not found in cache, calculate it from scratch
    $data = calculateData($parameters);
    
    // store $data in cache for an hour so that it can be retrieved next time
    $cache->set($key, $data, 3600);
}

// $data is available here
```

In order to delete value you can use:

```php
$cache->delete($key);
// Or all cache
$cache->clear();
```

To work with values in a more efficient manner, batch operations should be used:

- `getMultiple()`
- `setMultiple()`
- `deleteMultiple()`

## Documentation

- This package can be used as a cache handler for the [Yii Caching Library](https://github.com/yiisoft/cache).
- [Internals](docs/internals.md)

If you need help or have a question, the [Yii Forum](https://forum.yiiframework.com/c/yii-3-0/63) is a good place for
that. You may also check out other [Yii Community Resources](https://www.yiiframework.com/community).

## License

The Yii Cache Library - Memcached Handler is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.

Maintained by [Yii Software](https://www.yiiframework.com/).

## Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

## Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)](https://www.yiiframework.com/)
[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=flat&logo=telegram)](https://t.me/yii3en)
[![Facebook](https://img.shields.io/badge/facebook-join-1DA1F2?style=flat&logo=facebook&logoColor=ffffff)](https://www.facebook.com/groups/yiitalk)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=flat&logo=slack)](https://yiiframework.com/go/slack)
