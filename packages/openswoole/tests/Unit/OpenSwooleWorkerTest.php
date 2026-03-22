<?php

declare(strict_types=1);

namespace Lattice\OpenSwoole\Tests\Unit;

use Lattice\OpenSwoole\OpenSwooleWorker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OpenSwooleWorkerTest extends TestCase
{
    #[Test]
    public function it_throws_runtime_exception_as_experimental(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenSwoole support is experimental and not yet available.');

        new OpenSwooleWorker();
    }
}
