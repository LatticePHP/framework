<?php

declare(strict_types=1);

namespace Lattice\DevTools\Generator;

use Lattice\DevTools\GeneratedFile;
use Lattice\DevTools\GeneratorInterface;
use Lattice\DevTools\Template\TemplateEngine;

final class MigrationGenerator implements GeneratorInterface
{
    private readonly TemplateEngine $template;

    public function __construct()
    {
        $this->template = new TemplateEngine();
    }

    public function getName(): string
    {
        return 'migration';
    }

    public function getDescription(): string
    {
        return 'Creates a database migration file with up/down methods';
    }

    /**
     * @param array<string, mixed> $options
     * @return GeneratedFile[]
     */
    public function generate(array $options): array
    {
        $name = $options['name'] ?? throw new \InvalidArgumentException('Migration name is required');
        $table = $options['table'] ?? 'table_name';
        /** @var array<string, string> $columns */
        $columns = $options['columns'] ?? [];

        $timestamp = date('Y_m_d_His');
        $className = $this->toClassName($name);
        $columnDefinitions = $this->generateColumns($columns);

        return [
            new GeneratedFile(
                path: 'database/migrations/' . $timestamp . '_' . $this->toSnakeCase($name) . '.php',
                content: $this->template->render($this->migrationTemplate(), [
                    'className' => $className,
                    'tableName' => $table,
                    'columns' => $columnDefinitions,
                ]),
            ),
        ];
    }

    /**
     * @param array<string, string> $columns
     */
    private function generateColumns(array $columns): string
    {
        if ($columns === []) {
            return "            // Define columns\n            \$schema->addColumn('id', 'bigint', ['autoIncrement' => true]);";
        }

        $lines = [];
        foreach ($columns as $colName => $colType) {
            $lines[] = "            \$schema->addColumn('{$colName}', '{$colType}');";
        }
        return implode("\n", $lines);
    }

    private function toClassName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
    }

    private function toSnakeCase(string $name): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($name)) ?? $name);
    }

    private function migrationTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use Lattice\Database\Migration\Migration;
use Lattice\Database\Schema\SchemaBuilder;

final class {{className}} extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('{{tableName}}', function (SchemaBuilder $schema): void {
{{columns}}
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->drop('{{tableName}}');
    }
}
PHP;
    }
}
