<?php

declare(strict_types=1);

namespace Lattice\RoadRunner\Tests;

use Lattice\RoadRunner\RoadRunnerConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RoadRunnerConfigTest extends TestCase
{
    #[Test]
    public function it_has_sensible_defaults(): void
    {
        $config = new RoadRunnerConfig();

        $this->assertSame(4, $config->httpWorkers);
        $this->assertSame(4, $config->grpcWorkers);
        $this->assertSame(128, $config->maxMemory);
        $this->assertSame([], $config->resetProviders);
    }

    #[Test]
    public function it_accepts_custom_values(): void
    {
        $config = new RoadRunnerConfig(
            httpWorkers: 8,
            grpcWorkers: 2,
            maxMemory: 256,
            resetProviders: ['App\\SessionProvider', 'App\\CacheProvider'],
        );

        $this->assertSame(8, $config->httpWorkers);
        $this->assertSame(2, $config->grpcWorkers);
        $this->assertSame(256, $config->maxMemory);
        $this->assertSame(['App\\SessionProvider', 'App\\CacheProvider'], $config->resetProviders);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $config = RoadRunnerConfig::fromArray([
            'httpWorkers' => 16,
            'grpcWorkers' => 8,
            'maxMemory' => 512,
            'resetProviders' => ['App\\Provider'],
        ]);

        $this->assertSame(16, $config->httpWorkers);
        $this->assertSame(8, $config->grpcWorkers);
        $this->assertSame(512, $config->maxMemory);
        $this->assertSame(['App\\Provider'], $config->resetProviders);
    }

    #[Test]
    public function it_creates_from_partial_array_using_defaults(): void
    {
        $config = RoadRunnerConfig::fromArray([
            'httpWorkers' => 12,
        ]);

        $this->assertSame(12, $config->httpWorkers);
        $this->assertSame(4, $config->grpcWorkers);
        $this->assertSame(128, $config->maxMemory);
        $this->assertSame([], $config->resetProviders);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $config = new RoadRunnerConfig(
            httpWorkers: 8,
            grpcWorkers: 2,
            maxMemory: 256,
            resetProviders: ['App\\Provider'],
        );

        $this->assertSame([
            'httpWorkers' => 8,
            'grpcWorkers' => 2,
            'maxMemory' => 256,
            'resetProviders' => ['App\\Provider'],
        ], $config->toArray());
    }
}
