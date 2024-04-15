# Internals

## Development environment

For greater ease it is recommended to use docker containers.

Run container with memcached directly via command:

```shell
docker run --rm --name yiisoft-cache-memcached-cache --detach --publish 11211:11211 memcached:1.6.23
```

Memcached must be accessible by address `127.0.0.1`. If you use PHP via docker container, run PHP container in network
of memcached container. Use `docker run` command argument for it:

```dockerfile
--network container:yiisoft-cache-memcached-cache
```

## Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
./vendor/bin/phpunit
```

## Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework with
[Infection Static Analysis Plugin](https://github.com/Roave/infection-static-analysis-plugin). To run it:

```shell
./vendor/bin/roave-infection-static-analysis-plugin
```

## Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/). To run static analysis:

```shell
./vendor/bin/psalm
```

## Code style

Use [Rector](https://github.com/rectorphp/rector) to make codebase follow some specific rules or
use either newest or any specific version of PHP:

```shell
./vendor/bin/rector
```

## Dependencies

Use [ComposerRequireChecker](https://github.com/maglnet/ComposerRequireChecker) to detect transitive
[Composer](https://getcomposer.org/) dependencies.

To run the checker, execute the following command:

```shell
./vendor/bin/composer-require-checker
```
