<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeDtoCommand extends Command
{
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
        parent::__construct('make:dto');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new DTO with validation attributes')
            ->addArgument('name', InputArgument::REQUIRED, 'The DTO name (e.g., CreateUser)')
            ->addOption('fields', null, InputOption::VALUE_OPTIONAL, 'Comma-separated fields (name:type,email:string)', '')
            ->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'The module to create the DTO in');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        $fieldsStr = (string) $input->getOption('fields');
        $module = $input->getOption('module');
        $style = new LatticeStyle($output);

        $style->banner();

        $className = str_ends_with($name, 'Dto') ? $name : $name . 'Dto';
        $namespace = $module ? "App\\Modules\\{$module}\\DTOs" : 'App\\Dto';
        $basePath = $module ? "src/Modules/{$module}/DTOs" : 'app/Dto';

        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        $fields = $this->parseFields($fieldsStr);
        $properties = $this->generateProperties($fields);
        $useStatements = $this->generateUseStatements($fields);

        $content = <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            {$useStatements}

            final class {$className}
            {
                public function __construct(
            {$properties}
                ) {}
            }
            PHP;

        $filePath = $basePath . '/' . $className . '.php';
        file_put_contents($filePath, $content);

        $style->success("DTO <fg=white>{$className}</> created at <fg=gray>{$filePath}</>");

        if ($fields !== []) {
            $style->info("Properties: " . implode(', ', array_map(
                fn(string $name, string $type): string => "{$type} \${$name}",
                array_keys($fields),
                array_values($fields),
            )));
        }

        $style->newLine();

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function parseFields(string $fieldsStr): array
    {
        if ($fieldsStr === '') {
            return [];
        }

        $fields = [];
        foreach (explode(',', $fieldsStr) as $field) {
            $parts = explode(':', trim($field));
            $fieldName = trim($parts[0]);
            $fieldType = trim($parts[1] ?? 'string');
            if ($fieldName !== '') {
                $fields[$fieldName] = $fieldType;
            }
        }
        return $fields;
    }

    /**
     * @param array<string, string> $fields
     */
    private function generateProperties(array $fields): string
    {
        if ($fields === []) {
            return '        // Add properties here';
        }

        $lines = [];
        foreach ($fields as $fieldName => $fieldType) {
            $validationAttr = self::TYPE_ATTRIBUTE_MAP[$fieldType] ?? 'StringType';
            $lines[] = "        #[Required]";
            $lines[] = "        #[{$validationAttr}]";
            $lines[] = "        public readonly {$fieldType} \${$fieldName},";
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, string> $fields
     */
    private function generateUseStatements(array $fields): string
    {
        $uses = [];

        if ($fields !== []) {
            $uses[] = 'use Lattice\\Validation\\Attributes\\Required;';

            $needed = [];
            foreach ($fields as $type) {
                $attr = self::TYPE_ATTRIBUTE_MAP[$type] ?? 'StringType';
                $needed[$attr] = true;
            }

            foreach (array_keys($needed) as $attr) {
                $uses[] = "use Lattice\\Validation\\Attributes\\{$attr};";
            }

            sort($uses);
        }

        return implode("\n", $uses);
    }
}
