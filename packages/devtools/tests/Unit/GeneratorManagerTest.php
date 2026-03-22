<?php

declare(strict_types=1);

namespace Lattice\DevTools\Tests\Unit;

use Lattice\DevTools\GeneratedFile;
use Lattice\DevTools\GeneratorInterface;
use Lattice\DevTools\GeneratorManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GeneratorManagerTest extends TestCase
{
    #[Test]
    public function it_registers_and_lists_generators(): void
    {
        $manager = new GeneratorManager();
        $generator = $this->createMockGenerator('test', 'Test generator');

        $manager->register('test', $generator);

        $list = $manager->list();
        $this->assertArrayHasKey('test', $list);
        $this->assertSame('Test generator', $list['test']);
    }

    #[Test]
    public function it_generates_files_via_registered_generator(): void
    {
        $manager = new GeneratorManager();

        $file = new GeneratedFile('test.php', '<?php // test', 'created');
        $generator = $this->createMockGenerator('test', 'Test', [$file]);

        $manager->register('test', $generator);
        $result = $manager->generate('test', []);

        $this->assertCount(1, $result);
        $this->assertSame('test.php', $result[0]->path);
    }

    #[Test]
    public function it_throws_for_unknown_generator(): void
    {
        $manager = new GeneratorManager();

        $this->expectException(\InvalidArgumentException::class);
        $manager->generate('nonexistent', []);
    }

    /**
     * @param GeneratedFile[] $files
     */
    private function createMockGenerator(string $name, string $description, array $files = []): GeneratorInterface
    {
        $mock = $this->createMock(GeneratorInterface::class);
        $mock->method('getName')->willReturn($name);
        $mock->method('getDescription')->willReturn($description);
        $mock->method('generate')->willReturn($files);

        return $mock;
    }
}
