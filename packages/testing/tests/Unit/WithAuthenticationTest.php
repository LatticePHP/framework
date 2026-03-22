<?php

declare(strict_types=1);

namespace Lattice\Testing\Tests\Unit;

use Lattice\Testing\Fakes\FakePrincipal;
use Lattice\Testing\TestCase;
use Lattice\Testing\Traits\WithAuthentication;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

final class WithAuthenticationTest extends PHPUnitTestCase
{
    #[Test]
    public function test_setup_creates_authenticated_user(): void
    {
        $tc = new WithAuthenticationStub('test_setup_creates_authenticated_user');
        $tc->setUp();

        $this->assertInstanceOf(FakePrincipal::class, $tc->getAuthenticatedUser());
        $this->assertSame('user', $tc->getAuthenticatedUser()->getType());
        $this->assertTrue($tc->getAuthenticatedUser()->hasRole('user'));

        $tc->tearDown();
    }

    #[Test]
    public function test_setup_sets_user_on_container(): void
    {
        $tc = new WithAuthenticationStub('test_setup_sets_user_on_container');
        $tc->setUp();

        /** @var FakeApp $app */
        $app = $tc->getApp();
        $authUser = $app->getContainer()->get('auth.user');

        $this->assertSame($tc->getAuthenticatedUser(), $authUser);

        $tc->tearDown();
    }

    #[Test]
    public function test_teardown_clears_authenticated_user(): void
    {
        $tc = new WithAuthenticationStub('test_teardown_clears_authenticated_user');
        $tc->setUp();

        $this->assertNotNull($tc->getAuthenticatedUser());

        $tc->tearDown();

        $this->assertNull($tc->getAuthenticatedUser());
    }

    #[Test]
    public function test_user_id_is_unique_per_setup(): void
    {
        $tc1 = new WithAuthenticationStub('test_user_id_is_unique_per_setup');
        $tc1->setUp();
        $id1 = $tc1->getAuthenticatedUser()->getId();
        $tc1->tearDown();

        $tc2 = new WithAuthenticationStub('test_user_id_is_unique_per_setup');
        $tc2->setUp();
        $id2 = $tc2->getAuthenticatedUser()->getId();
        $tc2->tearDown();

        $this->assertNotSame($id1, $id2);
    }
}

// --- Test Stub ---

class WithAuthenticationStub extends TestCase
{
    use WithAuthentication;

    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    protected function createApplication(): ?object
    {
        return new FakeApp();
    }

    public function getApp(): ?object
    {
        return $this->app;
    }

    public function getAuthenticatedUser(): ?FakePrincipal
    {
        return $this->authenticatedUser;
    }

    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }
}
