<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Unit;

use Lattice\Compiler\Exceptions\CircularDependencyException;
use Lattice\Compiler\Exceptions\ExportViolationException;
use Lattice\Compiler\Exceptions\StaleManifestException;
use Lattice\Compiler\Exceptions\UnresolvedImportException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CompilerExceptionsTest extends TestCase
{
    #[Test]
    public function test_circular_dependency_exception_message(): void
    {
        $exception = new CircularDependencyException(['ModuleA', 'ModuleB', 'ModuleA']);

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame(
            'Circular dependency detected: ModuleA -> ModuleB -> ModuleA',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function test_circular_dependency_exception_with_single_module(): void
    {
        $exception = new CircularDependencyException(['ModuleA']);

        self::assertSame('Circular dependency detected: ModuleA', $exception->getMessage());
    }

    #[Test]
    public function test_export_violation_exception_message(): void
    {
        $exception = new ExportViolationException('UserModule', 'App\\ExternalService');

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame(
            "Module 'UserModule' exports 'App\\ExternalService' which is not listed in its providers.",
            $exception->getMessage(),
        );
    }

    #[Test]
    public function test_unresolved_import_exception_message(): void
    {
        $exception = new UnresolvedImportException('UserModule', 'App\\MissingModule');

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame(
            "Module 'UserModule' imports 'App\\MissingModule' which is not registered in the module graph.",
            $exception->getMessage(),
        );
    }

    #[Test]
    public function test_stale_manifest_exception_message(): void
    {
        $exception = new StaleManifestException('/path/to/manifest.php');

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame(
            "Manifest at '/path/to/manifest.php' is stale or invalid and must be recompiled.",
            $exception->getMessage(),
        );
    }
}
