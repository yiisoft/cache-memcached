<?php

declare(strict_types=1);

namespace Yiisoft\Cache\Memcached\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\Cache\Memcached\Memcached;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

final class ConfigTest extends TestCase
{
    public function testBase(): void
    {
        $container = $this->createContainer();

        $memcached = $container->get(Memcached::class);

        $this->assertInstanceOf(Memcached::class, $memcached);
    }

    private function createContainer(): Container
    {
        return new Container(
            ContainerConfig::create()->withDefinitions(
                $this->getDiConfig()
            )
        );
    }

    private function getDiConfig(): array
    {
        $params = $this->getParams();
        return require dirname(__DIR__) . '/config/di.php';
    }

    private function getParams(): array
    {
        return require dirname(__DIR__) . '/config/params.php';
    }
}
