<?php

declare(strict_types=1);

namespace Lattice\DevTools\Generator;

use Lattice\DevTools\GeneratedFile;
use Lattice\DevTools\GeneratorInterface;
use Lattice\DevTools\Template\TemplateEngine;

final class DtoGenerator implements GeneratorInterface
{
    private readonly TemplateEngine $template;

    /** @var array<string, string> */
    private const array TYPE_ATTRIBUTE_MAP = [
        'string' => 'StringType',
        'int' => 'IntegerType',
        'float' => 'FloatType',
        'bool' => 'BooleanType',
        'array' => 'ArrayType',
    ];

    public function __construct()
    {
        $this->template = new TemplateEngine();
    }

    public function getName(): string
    {
        return 'dto';
    }

    public function getDescription(): string
    {
        return 'Creates request/response DTOs with validation attributes';
    }

    /**
     * @param array<string, mixed> $options
     * @return GeneratedFile[]
     */
    public function generate(array $options): array
    {
        $name = $options['name'] ?? throw new \InvalidArgumentException('DTO name is required');
        /** @var array<string, string> $fields */
        $fields = $options['fields'] ?? [];

        $className = $name . 'Dto';
        $properties = $this->generateProperties($fields);
        $useStatements = $this->generateUseStatements($fields);

        return [
            new GeneratedFile(
                path: 'app/Dto/' . $className . '.php',
                content: $this->template->render($this->dtoTemplate(), [
                    'className' => $className,
                    'properties' => $properties,
                    'useStatements' => $useStatements,
                ]),
            ),
        ];
    }

    /**
     * @param array<string, string> $fields
     */
    private function generateProperties(array $fields): string
    {
        $lines = [];

        foreach ($fields as $fieldName => $fieldType) {
            $validationAttr = self::TYPE_ATTRIBUTE_MAP[$fieldType] ?? 'StringType';

            $lines[] = '    #[Required]';
            $lines[] = '    #[' . $validationAttr . ']';
            $lines[] = '    public readonly ' . $fieldType . ' $' . $fieldName . ',';
            $lines[] = '';
        }

        // Remove trailing empty line
        if ($lines !== [] && $lines[array_key_last($lines)] === '') {
            array_pop($lines);
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, string> $fields
     */
    private function generateUseStatements(array $fields): string
    {
        $uses = ['use Lattice\Validation\Attributes\Required;'];

        $needed = [];
        foreach ($fields as $type) {
            $attr = self::TYPE_ATTRIBUTE_MAP[$type] ?? 'StringType';
            $needed[$attr] = true;
        }

        foreach (array_keys($needed) as $attr) {
            $uses[] = 'use Lattice\Validation\Attributes\\' . $attr . ';';
        }

        sort($uses);

        return implode("\n", $uses);
    }

    private function dtoTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Dto;

{{useStatements}}

final class {{className}}
{
    public function __construct(
{{properties}}
    ) {}
}
PHP;
    }
}
