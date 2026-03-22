<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Tests\Unit;

use Lattice\Pipeline\PipelineBuilder;
use Lattice\Pipeline\Tests\Fixtures\FixtureController;
use Lattice\Pipeline\Tests\Fixtures\MethodGuard;
use Lattice\Pipeline\Tests\Fixtures\MethodInterceptor;
use Lattice\Pipeline\Tests\Fixtures\NoAttributeController;
use Lattice\Pipeline\Tests\Fixtures\StubFilter;
use Lattice\Pipeline\Tests\Fixtures\StubGuard;
use Lattice\Pipeline\Tests\Fixtures\StubInterceptor;
use Lattice\Pipeline\Tests\Fixtures\StubPipe;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PipelineBuilderTest extends TestCase
{
    private PipelineBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new PipelineBuilder();
    }

    #[Test]
    public function it_builds_config_from_class_and_method_attributes(): void
    {
        $config = $this->builder->forHandler(FixtureController::class, 'index');

        $this->assertSame([StubGuard::class], $config->getGuardClasses());
        $this->assertSame([StubPipe::class], $config->getPipeClasses());
        $this->assertSame([StubInterceptor::class], $config->getInterceptorClasses());
        $this->assertSame([StubFilter::class], $config->getFilterClasses());
    }

    #[Test]
    public function it_uses_only_class_attributes_when_method_has_none(): void
    {
        $config = $this->builder->forHandler(FixtureController::class, 'noAttributes');

        $this->assertSame([StubGuard::class], $config->getGuardClasses());
        $this->assertSame([], $config->getPipeClasses());
        $this->assertSame([StubInterceptor::class], $config->getInterceptorClasses());
        $this->assertSame([], $config->getFilterClasses());
    }

    #[Test]
    public function method_level_overrides_class_level(): void
    {
        $config = $this->builder->forHandler(FixtureController::class, 'withMethodOverrides');

        // Method-level guards override class-level
        $this->assertSame([MethodGuard::class], $config->getGuardClasses());
        $this->assertSame([MethodInterceptor::class], $config->getInterceptorClasses());
    }

    #[Test]
    public function it_returns_empty_config_for_no_attributes(): void
    {
        $config = $this->builder->forHandler(NoAttributeController::class, 'handle');

        $this->assertSame([], $config->getGuardClasses());
        $this->assertSame([], $config->getPipeClasses());
        $this->assertSame([], $config->getInterceptorClasses());
        $this->assertSame([], $config->getFilterClasses());
    }
}
