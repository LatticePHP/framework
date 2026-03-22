<?php

declare(strict_types=1);

namespace Lattice\Testing\Tests\Unit;

use Lattice\Testing\TestCase;
use Lattice\Testing\Traits\WithWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

final class WithWorkspaceTest extends PHPUnitTestCase
{
    #[Test]
    public function test_setup_creates_workspace(): void
    {
        $tc = new WithWorkspaceStub('test_setup_creates_workspace');
        $tc->setUp();

        $this->assertNotNull($tc->getWorkspace());
        $this->assertSame('Test Workspace', $tc->getWorkspace()->name);
        $this->assertSame('test-workspace', $tc->getWorkspace()->slug);

        $tc->tearDown();
    }

    #[Test]
    public function test_setup_sets_workspace_on_container(): void
    {
        $tc = new WithWorkspaceStub('test_setup_sets_workspace_on_container');
        $tc->setUp();

        /** @var FakeApp $app */
        $app = $tc->getApp();
        $ws = $app->getContainer()->get('workspace.current');

        $this->assertSame($tc->getWorkspace(), $ws);

        $tc->tearDown();
    }

    #[Test]
    public function test_teardown_clears_workspace(): void
    {
        $tc = new WithWorkspaceStub('test_teardown_clears_workspace');
        $tc->setUp();

        $this->assertNotNull($tc->getWorkspace());

        $tc->tearDown();

        $this->assertNull($tc->getWorkspace());
    }

    #[Test]
    public function test_workspace_id_is_unique_per_setup(): void
    {
        $tc1 = new WithWorkspaceStub('test_workspace_id_is_unique_per_setup');
        $tc1->setUp();
        $id1 = $tc1->getWorkspace()->id;
        $tc1->tearDown();

        $tc2 = new WithWorkspaceStub('test_workspace_id_is_unique_per_setup');
        $tc2->setUp();
        $id2 = $tc2->getWorkspace()->id;
        $tc2->tearDown();

        $this->assertNotSame($id1, $id2);
    }
}

// --- Test Stub ---

class WithWorkspaceStub extends TestCase
{
    use WithWorkspace;

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

    public function getWorkspace(): ?object
    {
        return $this->workspace;
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
