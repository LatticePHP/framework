<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Tests\Unit;

use Lattice\Contracts\Pipeline\PipeInterface;
use Lattice\Pipeline\Pipe\PipeChain;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PipeChainTest extends TestCase
{
    private PipeChain $chain;

    protected function setUp(): void
    {
        $this->chain = new PipeChain();
    }

    #[Test]
    public function it_returns_input_with_no_pipes(): void
    {
        $result = $this->chain->execute([], 'hello', []);

        $this->assertSame('hello', $result);
    }

    #[Test]
    public function it_transforms_input_through_single_pipe(): void
    {
        $pipe = new class implements PipeInterface {
            public function transform(mixed $value, array $metadata = []): mixed
            {
                return strtoupper($value);
            }
        };

        $result = $this->chain->execute([$pipe], 'hello', []);

        $this->assertSame('HELLO', $result);
    }

    #[Test]
    public function it_transforms_input_through_multiple_pipes_sequentially(): void
    {
        $trimPipe = new class implements PipeInterface {
            public function transform(mixed $value, array $metadata = []): mixed
            {
                return trim($value);
            }
        };

        $upperPipe = new class implements PipeInterface {
            public function transform(mixed $value, array $metadata = []): mixed
            {
                return strtoupper($value);
            }
        };

        $result = $this->chain->execute([$trimPipe, $upperPipe], '  hello  ', []);

        $this->assertSame('HELLO', $result);
    }

    #[Test]
    public function it_passes_metadata_to_pipes(): void
    {
        $pipe = new class implements PipeInterface {
            public function transform(mixed $value, array $metadata = []): mixed
            {
                return $value . ':' . ($metadata['suffix'] ?? '');
            }
        };

        $result = $this->chain->execute([$pipe], 'hello', ['suffix' => 'world']);

        $this->assertSame('hello:world', $result);
    }
}
