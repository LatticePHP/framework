<?php

declare(strict_types=1);

namespace Lattice\Mcp\Tests\Fixtures;

use Lattice\Mcp\Attributes\Tool;
use Lattice\Mcp\Attributes\ToolParam;

final class SchemaTestService
{
    #[Tool(description: 'All basic types')]
    public function allTypes(
        string $name,
        int $age,
        float $score,
        bool $active,
        array $tags,
    ): array {
        return [];
    }

    #[Tool(description: 'With defaults')]
    public function withDefaults(
        string $name,
        int $limit = 10,
        bool $verbose = false,
    ): array {
        return [];
    }

    #[Tool(description: 'With nullable')]
    public function withNullable(
        string $name,
        ?string $nickname = null,
    ): array {
        return [];
    }

    #[Tool(description: 'With enum parameter')]
    public function withEnum(
        string $title,
        Priority $priority,
    ): array {
        return [];
    }

    /**
     * A tool with docblock param descriptions.
     *
     * @param string $firstName The first name of the person
     * @param string $lastName The last name of the person
     */
    #[Tool(description: 'With docblock descriptions')]
    public function withDocblock(
        string $firstName,
        string $lastName,
    ): array {
        return [];
    }

    #[Tool(description: 'No parameters')]
    public function noParams(): array
    {
        return [];
    }

    #[Tool(description: 'Throws exception')]
    public function throwsException(string $input): array
    {
        throw new \RuntimeException('Something went wrong: ' . $input);
    }
}
