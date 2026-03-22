<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Tests\Unit;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Pipeline\Exceptions\ForbiddenException;
use Lattice\Pipeline\Guard\GuardChain;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GuardChainTest extends TestCase
{
    private GuardChain $chain;

    protected function setUp(): void
    {
        $this->chain = new GuardChain();
    }

    #[Test]
    public function it_passes_when_all_guards_return_true(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);

        $guard1 = $this->createMock(GuardInterface::class);
        $guard1->method('canActivate')->willReturn(true);

        $guard2 = $this->createMock(GuardInterface::class);
        $guard2->method('canActivate')->willReturn(true);

        $result = $this->chain->execute([$guard1, $guard2], $context);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_throws_forbidden_when_a_guard_returns_false(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);

        $guard1 = $this->createMock(GuardInterface::class);
        $guard1->method('canActivate')->willReturn(true);

        $guard2 = $this->createMock(GuardInterface::class);
        $guard2->method('canActivate')->willReturn(false);

        $this->expectException(ForbiddenException::class);

        $this->chain->execute([$guard1, $guard2], $context);
    }

    #[Test]
    public function it_passes_with_empty_guards(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);

        $result = $this->chain->execute([], $context);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_stops_at_first_failing_guard(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);

        $guard1 = $this->createMock(GuardInterface::class);
        $guard1->method('canActivate')->willReturn(false);

        $guard2 = $this->createMock(GuardInterface::class);
        $guard2->expects($this->never())->method('canActivate');

        $this->expectException(ForbiddenException::class);

        $this->chain->execute([$guard1, $guard2], $context);
    }
}
