<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeModelCommand extends Command
{
    public function __construct()
    {
        parent::__construct('make:model');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new Eloquent model class')
            ->addArgument('name', InputArgument::REQUIRED, 'The model name (PascalCase)')
            ->addOption('migration', 'm', InputOption::VALUE_NONE, 'Create a migration file for the model')
            ->addOption('factory', 'f', InputOption::VALUE_NONE, 'Create a factory class for the model')
            ->addOption('module', null, InputOption::VALUE_OPTIONAL, 'The module to create the model in');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        $withMigration = (bool) $input->getOption('migration');
        $withFactory = (bool) $input->getOption('factory');
        $module = $input->getOption('module');
        $style = new LatticeStyle($output);

        $style->banner();
        $style->header("Creating model: {$name}");
        $style->newLine();

        $namespace = $module ? "App\\Modules\\{$module}\\Entities" : 'App\\Models';
        $basePath = $module ? "src/Modules/{$module}/Entities" : 'app/Models';

        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        $tableName = $this->toTableName($name);

        $modelContent = <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use Illuminate\\Database\\Eloquent\\Model;

            final class {$name} extends Model
            {
                protected \$table = '{$tableName}';

                protected \$fillable = [
                    // Define fillable attributes
                ];

                protected \$casts = [
                    // Define attribute casts
                ];
            }
            PHP;

        $modelFile = $basePath . '/' . $name . '.php';
        file_put_contents($modelFile, $modelContent);
        $style->success("Model created at <fg=white>{$modelFile}</>");

        if ($withMigration) {
            $timestamp = date('Y_m_d_His');
            $migrationDir = 'database/migrations';

            if (!is_dir($migrationDir)) {
                mkdir($migrationDir, 0755, true);
            }

            $migrationName = "create_{$tableName}_table";

            $migrationContent = <<<PHP
                <?php

                declare(strict_types=1);

                use Illuminate\\Database\\Migrations\\Migration;
                use Illuminate\\Database\\Schema\\Blueprint;
                use Illuminate\\Support\\Facades\\Schema;

                return new class extends Migration
                {
                    public function up(): void
                    {
                        Schema::create('{$tableName}', function (Blueprint \$table): void {
                            \$table->id();
                            // Add columns here
                            \$table->timestamps();
                        });
                    }

                    public function down(): void
                    {
                        Schema::dropIfExists('{$tableName}');
                    }
                };
                PHP;

            $migrationFile = "{$migrationDir}/{$timestamp}_{$migrationName}.php";
            file_put_contents($migrationFile, $migrationContent);
            $style->success("Migration created at <fg=white>{$migrationFile}</>");
        }

        if ($withFactory) {
            $factoryDir = 'database/factories';

            if (!is_dir($factoryDir)) {
                mkdir($factoryDir, 0755, true);
            }

            $factoryContent = <<<PHP
                <?php

                declare(strict_types=1);

                namespace Database\\Factories;

                use {$namespace}\\{$name};
                use Illuminate\\Database\\Eloquent\\Factories\\Factory;

                /**
                 * @extends Factory<{$name}>
                 */
                final class {$name}Factory extends Factory
                {
                    protected \$model = {$name}::class;

                    public function definition(): array
                    {
                        return [
                            // Define factory attributes
                        ];
                    }
                }
                PHP;

            $factoryFile = "{$factoryDir}/{$name}Factory.php";
            file_put_contents($factoryFile, $factoryContent);
            $style->success("Factory created at <fg=white>{$factoryFile}</>");
        }

        $style->newLine();
        $style->info("Model <fg=white>{$name}</> created successfully");
        $style->newLine();

        return Command::SUCCESS;
    }

    private function toTableName(string $name): string
    {
        $snake = strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
        if (!str_ends_with($snake, 's')) {
            $snake .= 's';
        }
        return $snake;
    }
}
