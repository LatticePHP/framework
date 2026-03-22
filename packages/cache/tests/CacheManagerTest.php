<?php

declare(strict_types=1);

namespace Lattice\Cache\Tests;

use Lattice\Cache\CacheInterface;
use Lattice\Cache\CacheManager;
use Lattice\Cache\Driver\ArrayCacheDriver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CacheManagerTest extends TestCase
{
    #[Test]
    public function it_returns_default_store(): void
    {
        $driver = new ArrayCacheDriver();
        $manager = new CacheManager();
        $manager->addDriver('default', $driver);

        $this->assertSame($driver, $manager->store());
    }

    #[Test]
    public function it_returns_named_store(): void
    {
        $redis = new ArrayCacheDriver();
        $file = new ArrayCacheDriver();

        $manager = new CacheManager();
        $manager->addDriver('redis', $redis);
        $manager->addDriver('file', $file);

        $this->assertSame($redis, $manager->store('redis'));
        $this->assertSame($file, $manager->store('file'));
    }

    #[Test]
    public function it_throws_for_unknown_store(): void
    {
        $manager = new CacheManager();

        $this->expectException(\InvalidArgumentException::class);
        $manager->store('nonexistent');
    }
}
