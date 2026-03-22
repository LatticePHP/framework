<?php

declare(strict_types=1);

namespace Lattice\Anvil\Tests\Deploy\Steps;

use Lattice\Anvil\Deploy\Steps\ComposerInstallStep;
use PHPUnit\Framework\TestCase;

final class ComposerInstallStepTest extends TestCase
{
    public function test_name_returns_expected(): void
    {
        $step = new ComposerInstallStep('/tmp/project');
        $this->assertSame('Composer install (production)', $step->name());
    }

    public function test_execute_throws_on_invalid_path(): void
    {
        $step = new ComposerInstallStep('/nonexistent/path/that/does/not/exist');

        $this->expectException(\RuntimeException::class);
        $step->execute();
    }
}
