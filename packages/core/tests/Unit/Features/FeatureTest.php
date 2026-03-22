<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit\Features;

use Lattice\Core\Features\Attributes\RequiresFeature;
use Lattice\Core\Features\Feature;
use Lattice\Core\Features\FeatureGuard;
use Lattice\Core\Features\FeatureScopeable;
use Lattice\Core\Features\ScopedFeature;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Feature::reset();
    }

    #[Test]
    public function test_define_feature_as_active_bool(): void
    {
        Feature::define('dark-mode', true);

        $this->assertTrue(Feature::active('dark-mode'));
    }

    #[Test]
    public function test_define_feature_as_inactive_bool(): void
    {
        Feature::define('dark-mode', false);

        $this->assertFalse(Feature::active('dark-mode'));
    }

    #[Test]
    public function test_undefined_feature_returns_false(): void
    {
        $this->assertFalse(Feature::active('nonexistent'));
    }

    #[Test]
    public function test_define_feature_with_callable_resolver(): void
    {
        Feature::define('beta', fn(?object $scope) => $scope !== null);

        $this->assertFalse(Feature::active('beta'));
        $this->assertTrue(Feature::active('beta', new \stdClass()));
    }

    #[Test]
    public function test_scoped_feature_checks_per_scope(): void
    {
        $user = new class implements FeatureScopeable {
            public string $id = 'user-1';
            public function featureScopeIdentifier(): string { return $this->id; }
        };

        Feature::define('beta', fn(?object $scope) => $scope?->id === 'user-1');

        $scoped = Feature::for($user);
        $this->assertInstanceOf(ScopedFeature::class, $scoped);
        $this->assertTrue($scoped->active('beta'));
    }

    #[Test]
    public function test_enable_override_makes_feature_active(): void
    {
        Feature::define('dark-mode', false);
        Feature::enable('dark-mode');

        $this->assertTrue(Feature::active('dark-mode'));
    }

    #[Test]
    public function test_disable_override_makes_feature_inactive(): void
    {
        Feature::define('dark-mode', true);
        Feature::disable('dark-mode');

        $this->assertFalse(Feature::active('dark-mode'));
    }

    #[Test]
    public function test_scoped_enable_only_affects_that_scope(): void
    {
        $user1 = new class implements FeatureScopeable {
            public function featureScopeIdentifier(): string { return 'u1'; }
        };
        $user2 = new class implements FeatureScopeable {
            public function featureScopeIdentifier(): string { return 'u2'; }
        };

        Feature::define('beta', false);
        Feature::enable('beta', $user1);

        $this->assertTrue(Feature::active('beta', $user1));
        $this->assertFalse(Feature::active('beta', $user2));
        $this->assertFalse(Feature::active('beta'));
    }

    #[Test]
    public function test_purge_removes_all_store_entries_for_feature(): void
    {
        Feature::define('beta', false);
        Feature::enable('beta');
        $this->assertTrue(Feature::active('beta'));

        Feature::purge('beta');
        $this->assertFalse(Feature::active('beta'));
    }

    #[Test]
    public function test_all_returns_defined_feature_names(): void
    {
        Feature::define('a', true);
        Feature::define('b', false);
        Feature::define('c', fn() => true);

        $this->assertSame(['a', 'b', 'c'], Feature::all());
    }

    #[Test]
    public function test_reset_clears_everything(): void
    {
        Feature::define('x', true);
        Feature::enable('x');
        Feature::reset();

        $this->assertSame([], Feature::all());
        $this->assertFalse(Feature::active('x'));
    }

    #[Test]
    public function test_feature_guard_allows_when_no_attribute(): void
    {
        $context = new FakeExecutionContext(
            class: FeatureTestController::class,
            method: 'noAttribute',
        );

        $guard = new FeatureGuard();
        $this->assertTrue($guard->canActivate($context));
    }

    #[Test]
    public function test_feature_guard_blocks_when_feature_disabled(): void
    {
        Feature::define('premium', false);

        $context = new FakeExecutionContext(
            class: FeatureTestController::class,
            method: 'premiumAction',
        );

        $guard = new FeatureGuard();
        $this->assertFalse($guard->canActivate($context));
    }

    #[Test]
    public function test_feature_guard_allows_when_feature_enabled(): void
    {
        Feature::define('premium', true);

        $context = new FakeExecutionContext(
            class: FeatureTestController::class,
            method: 'premiumAction',
        );

        $guard = new FeatureGuard();
        $this->assertTrue($guard->canActivate($context));
    }

    #[Test]
    public function test_feature_guard_checks_class_level_attribute(): void
    {
        Feature::define('v2', false);

        $context = new FakeExecutionContext(
            class: FeatureTestV2Controller::class,
            method: 'index',
        );

        $guard = new FeatureGuard();
        $this->assertFalse($guard->canActivate($context));
    }
}

// Test fixtures

class FeatureTestController
{
    public function noAttribute(): void {}

    #[RequiresFeature('premium')]
    public function premiumAction(): void {}
}

#[RequiresFeature('v2')]
class FeatureTestV2Controller
{
    public function index(): void {}
}

class FakeExecutionContext implements \Lattice\Contracts\Context\ExecutionContextInterface
{
    public function __construct(
        private readonly string $class,
        private readonly string $method,
    ) {}

    public function getType(): \Lattice\Contracts\Context\ExecutionType { return \Lattice\Contracts\Context\ExecutionType::HTTP; }
    public function getModule(): string { return 'test'; }
    public function getHandler(): string { return $this->class . '::' . $this->method; }
    public function getClass(): string { return $this->class; }
    public function getMethod(): string { return $this->method; }
    public function getCorrelationId(): string { return 'test-123'; }
    public function getPrincipal(): ?\Lattice\Contracts\Context\PrincipalInterface { return null; }
}
