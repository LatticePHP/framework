<?php

declare(strict_types=1);

namespace Lattice\Anvil\Tests\Deploy\Steps;

use Lattice\Anvil\Deploy\Steps\GitPullStep;
use PHPUnit\Framework\TestCase;

final class GitPullStepTest extends TestCase
{
    public function test_name_includes_branch(): void
    {
        $step = new GitPullStep('/tmp/project', 'main');
        $this->assertSame('Git pull (main)', $step->name());
    }

    public function test_name_with_custom_branch(): void
    {
        $step = new GitPullStep('/tmp/project', 'develop');
        $this->assertSame('Git pull (develop)', $step->name());
    }

    public function test_default_branch_is_main(): void
    {
        $step = new GitPullStep('/tmp/project');
        $this->assertSame('Git pull (main)', $step->name());
    }

    public function test_execute_throws_on_invalid_path(): void
    {
        $step = new GitPullStep('/nonexistent/path/that/does/not/exist', 'main');

        $this->expectException(\RuntimeException::class);
        $step->execute();
    }

    public function test_rollback_does_not_throw_on_null_head(): void
    {
        $step = new GitPullStep('/nonexistent/path', 'main');

        // Rollback without prior execute should be a no-op
        $step->rollback();

        $this->assertTrue(true); // No exception thrown
    }
}
