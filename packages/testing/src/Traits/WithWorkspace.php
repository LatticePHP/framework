<?php

declare(strict_types=1);

namespace Lattice\Testing\Traits;

/**
 * Automatically creates a workspace context before each test.
 *
 * After setUp, `$this->workspace` contains a simple workspace object
 * and the test case is configured with that workspace context.
 */
trait WithWorkspace
{
    protected ?object $workspace = null;

    protected function setUpWithWorkspace(): void
    {
        $this->workspace = $this->createWorkspace();

        if ($this->app !== null && method_exists($this->app, 'getContainer')) {
            $this->app->getContainer()->instance('workspace.current', $this->workspace);
        }
    }

    protected function tearDownWithWorkspace(): void
    {
        $this->workspace = null;
    }

    /**
     * Create the default workspace for tests.
     *
     * Override to customize the workspace attributes.
     */
    protected function createWorkspace(): object
    {
        return new class {
            public readonly string $id;
            public readonly string $name;
            public readonly string $slug;

            public function __construct()
            {
                $this->id = 'ws-' . bin2hex(random_bytes(4));
                $this->name = 'Test Workspace';
                $this->slug = 'test-workspace';
            }
        };
    }
}
