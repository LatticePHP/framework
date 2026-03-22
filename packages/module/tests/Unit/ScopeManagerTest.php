<?php

declare(strict_types=1);

namespace Lattice\Module\Tests\Unit;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Module\Scope\ScopeManager;
use Lattice\Module\Scope\ScopeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScopeManager::class)]
final class ScopeManagerTest extends TestCase
{
    private ScopeManager $manager;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->manager = new ScopeManager();
    }

    #[Test]
    public function it_registers_a_singleton_scope(): void
    {
        $this->manager->register('App\\UserService', ScopeType::Singleton);

        self::assertSame(ScopeType::Singleton, $this->manager->getScopeFor('App\\UserService'));
    }

    #[Test]
    public function it_registers_a_request_scope(): void
    {
        $this->manager->register('App\\RequestContext', ScopeType::Request);

        self::assertSame(ScopeType::Request, $this->manager->getScopeFor('App\\RequestContext'));
    }

    #[Test]
    public function it_registers_a_worker_scope(): void
    {
        $this->manager->register('App\\WorkerPool', ScopeType::Worker);

        self::assertSame(ScopeType::Worker, $this->manager->getScopeFor('App\\WorkerPool'));
    }

    #[Test]
    public function it_registers_a_transient_scope(): void
    {
        $this->manager->register('App\\Temporary', ScopeType::Transient);

        self::assertSame(ScopeType::Transient, $this->manager->getScopeFor('App\\Temporary'));
    }

    #[Test]
    public function it_defaults_to_transient_scope(): void
    {
        self::assertSame(ScopeType::Transient, $this->manager->getScopeFor('App\\Unknown'));
    }

    #[Test]
    public function it_applies_singleton_to_container(): void
    {
        $this->container->expects(self::once())
            ->method('singleton')
            ->with('App\\UserService', 'App\\UserService');

        $this->manager->register('App\\UserService', ScopeType::Singleton);
        $this->manager->applyScope('App\\UserService', 'App\\UserService', $this->container);
    }

    #[Test]
    public function it_applies_transient_to_container(): void
    {
        $this->container->expects(self::once())
            ->method('bind')
            ->with('App\\Temporary', 'App\\Temporary');

        $this->manager->register('App\\Temporary', ScopeType::Transient);
        $this->manager->applyScope('App\\Temporary', 'App\\Temporary', $this->container);
    }

    #[Test]
    public function it_applies_request_scope_as_singleton(): void
    {
        // Request-scoped providers are singletons that get cleared on request end
        $this->container->expects(self::once())
            ->method('singleton')
            ->with('App\\RequestContext', 'App\\RequestContext');

        $this->manager->register('App\\RequestContext', ScopeType::Request);
        $this->manager->applyScope('App\\RequestContext', 'App\\RequestContext', $this->container);
    }

    #[Test]
    public function it_applies_worker_scope_as_singleton(): void
    {
        // Worker-scoped providers are singletons that persist for the worker lifetime
        $this->container->expects(self::once())
            ->method('singleton')
            ->with('App\\WorkerPool', 'App\\WorkerPool');

        $this->manager->register('App\\WorkerPool', ScopeType::Worker);
        $this->manager->applyScope('App\\WorkerPool', 'App\\WorkerPool', $this->container);
    }

    #[Test]
    public function it_returns_request_scoped_providers(): void
    {
        $this->manager->register('App\\A', ScopeType::Request);
        $this->manager->register('App\\B', ScopeType::Singleton);
        $this->manager->register('App\\C', ScopeType::Request);

        $requestScoped = $this->manager->getProvidersByScope(ScopeType::Request);

        self::assertSame(['App\\A', 'App\\C'], $requestScoped);
    }

    #[Test]
    public function it_clears_request_scoped_providers(): void
    {
        $this->manager->register('App\\A', ScopeType::Request);
        $this->manager->register('App\\B', ScopeType::Singleton);

        // clearScope should reset the container for request-scoped items
        $this->container->expects(self::once())
            ->method('reset');

        $this->manager->clearRequestScope($this->container);
    }
}
