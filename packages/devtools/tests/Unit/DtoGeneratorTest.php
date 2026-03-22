<?php

declare(strict_types=1);

namespace Lattice\DevTools\Tests\Unit;

use Lattice\DevTools\GeneratedFile;
use Lattice\DevTools\Generator\DtoGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DtoGeneratorTest extends TestCase
{
    private DtoGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new DtoGenerator();
    }

    #[Test]
    public function it_has_name_and_description(): void
    {
        $this->assertSame('dto', $this->generator->getName());
        $this->assertNotEmpty($this->generator->getDescription());
    }

    #[Test]
    public function it_generates_dto_with_typed_properties(): void
    {
        $files = $this->generator->generate([
            'name' => 'CreateUser',
            'fields' => ['name' => 'string', 'email' => 'string', 'age' => 'int'],
        ]);

        $this->assertNotEmpty($files);

        $dtoFile = $files[0];
        $this->assertSame('created', $dtoFile->type);
        $this->assertStringContainsString('declare(strict_types=1)', $dtoFile->content);
        $this->assertStringContainsString('final class CreateUserDto', $dtoFile->content);
        $this->assertStringContainsString('public readonly string $name', $dtoFile->content);
        $this->assertStringContainsString('public readonly string $email', $dtoFile->content);
        $this->assertStringContainsString('public readonly int $age', $dtoFile->content);
    }

    #[Test]
    public function it_generates_dto_with_validation_attributes(): void
    {
        $files = $this->generator->generate([
            'name' => 'CreateUser',
            'fields' => ['name' => 'string', 'email' => 'string'],
        ]);

        $dtoFile = $files[0];
        $this->assertStringContainsString('#[Required]', $dtoFile->content);
        $this->assertStringContainsString('#[StringType]', $dtoFile->content);
    }

    #[Test]
    public function it_maps_field_types_to_validation_attributes(): void
    {
        $files = $this->generator->generate([
            'name' => 'TestDto',
            'fields' => ['count' => 'int', 'amount' => 'float', 'active' => 'bool'],
        ]);

        $dtoFile = $files[0];
        $this->assertStringContainsString('#[IntegerType]', $dtoFile->content);
        $this->assertStringContainsString('#[FloatType]', $dtoFile->content);
        $this->assertStringContainsString('#[BooleanType]', $dtoFile->content);
    }
}
