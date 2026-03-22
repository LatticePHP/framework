<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit\Console;

use Lattice\Core\Console\Commands\DbSeedCommand;
use Lattice\Core\Console\Commands\MakeControllerCommand;
use Lattice\Core\Console\Commands\MakeDtoCommand;
use Lattice\Core\Console\Commands\MakeModelCommand;
use Lattice\Core\Console\Commands\MakeModuleCommand;
use Lattice\Core\Console\Commands\MakePolicyCommand;
use Lattice\Core\Console\Commands\MakeWorkflowCommand;
use Lattice\Core\Console\Commands\MigrateCommand;
use Lattice\Core\Console\Commands\MigrateFreshCommand;
use Lattice\Core\Console\Commands\MigrateRollbackCommand;
use Lattice\Core\Console\Commands\ModuleListCommand;
use Lattice\Core\Console\Commands\RouteListCommand;
use Lattice\Core\Console\Commands\ServeCommand;
use Lattice\Core\Console\Commands\TestCommand;
use Lattice\Core\Console\LatticeApplication;
use Lattice\Core\Console\LatticeStyle;
use Lattice\Database\Illuminate\IlluminateDatabaseManager;
use Lattice\Database\ModuleMigrationDiscoverer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

final class LatticeApplicationTest extends TestCase
{
    #[Test]
    public function it_creates_application_with_correct_name_and_version(): void
    {
        $app = LatticeApplication::create(__DIR__);

        $this->assertSame("\u{2B21} LatticePHP", $app->getConsole()->getName());
        $this->assertSame('1.0.0', $app->getConsole()->getVersion());
    }

    #[Test]
    public function it_stores_base_path(): void
    {
        $app = LatticeApplication::create('/tmp/test-app');
        $this->assertSame('/tmp/test-app', $app->getBasePath());
    }

    #[Test]
    public function it_registers_all_core_commands(): void
    {
        $app = LatticeApplication::create(__DIR__);
        $console = $app->getConsole();

        // Auto-registered commands (no DI dependencies)
        $this->assertTrue($console->has('serve'));
        $this->assertTrue($console->has('make:module'));
        $this->assertTrue($console->has('make:controller'));
        $this->assertTrue($console->has('make:model'));
        $this->assertTrue($console->has('make:dto'));
        $this->assertTrue($console->has('make:policy'));
        $this->assertTrue($console->has('make:workflow'));
        $this->assertTrue($console->has('route:list'));
        $this->assertTrue($console->has('module:list'));
        $this->assertTrue($console->has('test'));

        // DI-dependent commands are NOT auto-registered; they are added via addCommands()
        // after the container is built. Verify they can be added.
        $db = new IlluminateDatabaseManager(['driver' => 'sqlite', 'database' => ':memory:']);
        $discoverer = new ModuleMigrationDiscoverer();

        $app->addCommands(
            new MigrateCommand($db, $discoverer),
            new MigrateRollbackCommand($db, $discoverer),
            new MigrateFreshCommand($db, $discoverer),
            new DbSeedCommand($db, $discoverer),
        );

        $this->assertTrue($console->has('migrate'));
        $this->assertTrue($console->has('migrate:rollback'));
        $this->assertTrue($console->has('migrate:fresh'));
        $this->assertTrue($console->has('db:seed'));
    }

    #[Test]
    public function it_exposes_symfony_console(): void
    {
        $app = LatticeApplication::create(__DIR__);
        $this->assertInstanceOf(\Symfony\Component\Console\Application::class, $app->getConsole());
    }

    #[Test]
    public function serve_command_constructs_properly(): void
    {
        $command = new ServeCommand();
        $this->assertSame('serve', $command->getName());
        $this->assertSame('Start the development server', $command->getDescription());
    }

    #[Test]
    public function serve_command_has_host_and_port_options(): void
    {
        $command = new ServeCommand();
        $def = $command->getDefinition();

        $this->assertTrue($def->hasOption('host'));
        $this->assertTrue($def->hasOption('port'));
        $this->assertSame('127.0.0.1', $def->getOption('host')->getDefault());
        $this->assertSame('8080', $def->getOption('port')->getDefault());
    }

    #[Test]
    public function make_module_command_constructs_properly(): void
    {
        $command = new MakeModuleCommand();
        $this->assertSame('make:module', $command->getName());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
        $this->assertTrue($command->getDefinition()->hasOption('path'));
    }

    #[Test]
    public function make_controller_command_constructs_properly(): void
    {
        $command = new MakeControllerCommand();
        $this->assertSame('make:controller', $command->getName());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
        $this->assertTrue($command->getDefinition()->hasOption('module'));
        $this->assertTrue($command->getDefinition()->hasOption('crud'));
    }

    #[Test]
    public function make_model_command_constructs_properly(): void
    {
        $command = new MakeModelCommand();
        $this->assertSame('make:model', $command->getName());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
        $this->assertTrue($command->getDefinition()->hasOption('migration'));
        $this->assertTrue($command->getDefinition()->hasOption('factory'));
    }

    #[Test]
    public function make_dto_command_constructs_properly(): void
    {
        $command = new MakeDtoCommand();
        $this->assertSame('make:dto', $command->getName());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
        $this->assertTrue($command->getDefinition()->hasOption('fields'));
    }

    #[Test]
    public function make_policy_command_constructs_properly(): void
    {
        $command = new MakePolicyCommand();
        $this->assertSame('make:policy', $command->getName());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
        $this->assertTrue($command->getDefinition()->hasOption('abilities'));
    }

    #[Test]
    public function make_workflow_command_constructs_properly(): void
    {
        $command = new MakeWorkflowCommand();
        $this->assertSame('make:workflow', $command->getName());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
        $this->assertTrue($command->getDefinition()->hasOption('activities'));
    }

    #[Test]
    public function migrate_command_constructs_properly(): void
    {
        $db = new IlluminateDatabaseManager(['driver' => 'sqlite', 'database' => ':memory:']);
        $discoverer = new ModuleMigrationDiscoverer();
        $command = new MigrateCommand($db, $discoverer);
        $this->assertSame('migrate', $command->getName());
        $this->assertTrue($command->getDefinition()->hasOption('path'));
        $this->assertTrue($command->getDefinition()->hasOption('pretend'));
    }

    #[Test]
    public function migrate_rollback_command_constructs_properly(): void
    {
        $db = new IlluminateDatabaseManager(['driver' => 'sqlite', 'database' => ':memory:']);
        $discoverer = new ModuleMigrationDiscoverer();
        $command = new MigrateRollbackCommand($db, $discoverer);
        $this->assertSame('migrate:rollback', $command->getName());
        $this->assertTrue($command->getDefinition()->hasOption('step'));
    }

    #[Test]
    public function migrate_fresh_command_constructs_properly(): void
    {
        $db = new IlluminateDatabaseManager(['driver' => 'sqlite', 'database' => ':memory:']);
        $discoverer = new ModuleMigrationDiscoverer();
        $command = new MigrateFreshCommand($db, $discoverer);
        $this->assertSame('migrate:fresh', $command->getName());
        $this->assertTrue($command->getDefinition()->hasOption('seed'));
    }

    #[Test]
    public function db_seed_command_constructs_properly(): void
    {
        $db = new IlluminateDatabaseManager(['driver' => 'sqlite', 'database' => ':memory:']);
        $discoverer = new ModuleMigrationDiscoverer();
        $command = new DbSeedCommand($db, $discoverer);
        $this->assertSame('db:seed', $command->getName());
        $this->assertTrue($command->getDefinition()->hasArgument('class'));
    }

    #[Test]
    public function test_command_constructs_properly(): void
    {
        $command = new TestCommand();
        $this->assertSame('test', $command->getName());
        $this->assertTrue($command->getDefinition()->hasArgument('filter'));
        $this->assertTrue($command->getDefinition()->hasOption('coverage'));
    }

    #[Test]
    public function route_list_command_constructs_properly(): void
    {
        $command = new RouteListCommand();
        $this->assertSame('route:list', $command->getName());
        $this->assertTrue($command->getDefinition()->hasOption('method'));
        $this->assertTrue($command->getDefinition()->hasOption('path'));
        $this->assertTrue($command->getDefinition()->hasOption('json'));
    }

    #[Test]
    public function route_list_command_shows_no_routes_message(): void
    {
        $command = new RouteListCommand();
        $tester = new CommandTester($command);
        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('No routes registered', $display);
    }

    #[Test]
    public function route_list_command_displays_routes_in_table(): void
    {
        $command = new RouteListCommand();
        $command->setRoutes([
            [
                'method' => 'GET',
                'uri' => '/users',
                'name' => 'users.index',
                'action' => 'UserController@index',
                'middleware' => ['auth'],
                'guards' => ['jwt'],
            ],
            [
                'method' => 'POST',
                'uri' => '/users',
                'name' => 'users.store',
                'action' => 'UserController@store',
                'middleware' => ['auth'],
                'guards' => ['jwt'],
            ],
        ]);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('/users', $display);
        $this->assertStringContainsString('UserController@index', $display);
        $this->assertStringContainsString('Total routes: 2', $display);
    }

    #[Test]
    public function route_list_command_filters_by_method(): void
    {
        $command = new RouteListCommand();
        $command->setRoutes([
            ['method' => 'GET', 'uri' => '/users', 'name' => null, 'action' => 'UserController@index', 'guards' => []],
            ['method' => 'POST', 'uri' => '/users', 'name' => null, 'action' => 'UserController@store', 'guards' => []],
        ]);

        $tester = new CommandTester($command);
        $tester->execute(['--method' => 'POST']);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('UserController@store', $display);
        $this->assertStringContainsString('Total routes: 1', $display);
    }

    #[Test]
    public function route_list_command_outputs_json(): void
    {
        $command = new RouteListCommand();
        $command->setRoutes([
            ['method' => 'GET', 'uri' => '/health', 'name' => 'health', 'action' => 'HealthController@check', 'guards' => []],
        ]);

        $tester = new CommandTester($command);
        $tester->execute(['--json' => true]);

        $display = $tester->getDisplay();
        $data = json_decode($display, true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame('/health', $data[0]['uri']);
    }

    #[Test]
    public function module_list_command_constructs_properly(): void
    {
        $command = new ModuleListCommand();
        $this->assertSame('module:list', $command->getName());
        $this->assertTrue($command->getDefinition()->hasOption('tree'));
        $this->assertTrue($command->getDefinition()->hasOption('json'));
    }

    #[Test]
    public function module_list_command_shows_no_modules_message(): void
    {
        $command = new ModuleListCommand();
        $tester = new CommandTester($command);
        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('No modules registered', $display);
    }

    #[Test]
    public function module_list_command_displays_modules_in_table(): void
    {
        $command = new ModuleListCommand();
        $command->setModules([
            'App\\Modules\\User\\UserModule' => [
                'imports' => ['App\\Modules\\Auth\\AuthModule'],
                'exports' => ['App\\Modules\\User\\UserService'],
                'providers' => ['App\\Modules\\User\\UserProvider'],
                'controllers' => ['App\\Modules\\User\\UserController'],
            ],
        ]);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('UserModule', $display);
        $this->assertStringContainsString('Total modules: 1', $display);
    }

    #[Test]
    public function module_list_command_renders_tree_view(): void
    {
        $command = new ModuleListCommand();
        $command->setModules([
            'App\\Modules\\User\\UserModule' => [
                'imports' => ['App\\Modules\\Auth\\AuthModule'],
                'exports' => [],
                'providers' => [],
                'controllers' => ['App\\Modules\\User\\UserController'],
            ],
        ]);

        $tester = new CommandTester($command);
        $tester->execute(['--tree' => true]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('UserModule', $display);
        $this->assertStringContainsString('Imports', $display);
        $this->assertStringContainsString('Controllers', $display);
    }

    #[Test]
    public function module_list_command_outputs_json(): void
    {
        $command = new ModuleListCommand();
        $command->setModules([
            'App\\UserModule' => [
                'imports' => [],
                'exports' => [],
                'providers' => [],
                'controllers' => [],
            ],
        ]);

        $tester = new CommandTester($command);
        $tester->execute(['--json' => true]);

        $data = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('App\\UserModule', $data);
    }

    #[Test]
    public function route_list_get_routes_returns_set_data(): void
    {
        $command = new RouteListCommand();
        $routes = [
            ['method' => 'GET', 'uri' => '/api', 'name' => null, 'action' => 'ApiController@index', 'guards' => []],
        ];
        $command->setRoutes($routes);

        $returned = $command->getRoutes();
        $this->assertCount(1, $returned);
        $this->assertSame('/api', $returned[0]['uri']);
    }

    #[Test]
    public function module_list_get_modules_returns_set_data(): void
    {
        $command = new ModuleListCommand();
        $command->setModules([
            'TestModule' => ['imports' => [], 'exports' => [], 'providers' => [], 'controllers' => []],
        ]);

        $modules = $command->getModules();
        $this->assertCount(1, $modules);
        $this->assertArrayHasKey('TestModule', $modules);
    }

    // LatticeStyle tests

    #[Test]
    public function style_banner_outputs_framework_name(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);
        $style->banner();

        $result = $output->fetch();
        $this->assertStringContainsString('LatticePHP Framework', $result);
        $this->assertStringContainsString('Laravel engine', $result);
    }

    #[Test]
    public function style_info_outputs_message(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);
        $style->info('Test message');

        $result = $output->fetch();
        $this->assertStringContainsString('Test message', $result);
    }

    #[Test]
    public function style_success_outputs_message(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);
        $style->success('Operation completed');

        $result = $output->fetch();
        $this->assertStringContainsString('Operation completed', $result);
    }

    #[Test]
    public function style_error_outputs_message(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);
        $style->error('Something failed');

        $result = $output->fetch();
        $this->assertStringContainsString('Something failed', $result);
    }

    #[Test]
    public function style_warning_outputs_message(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);
        $style->warning('Caution needed');

        $result = $output->fetch();
        $this->assertStringContainsString('Caution needed', $result);
    }

    #[Test]
    public function style_header_outputs_title_with_separator(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);
        $style->header('My Header');

        $result = $output->fetch();
        $this->assertStringContainsString('My Header', $result);
        $this->assertStringContainsString("\u{2500}", $result);
    }

    #[Test]
    public function style_key_value_outputs_pair(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);
        $style->keyValue('Name', 'LatticePHP');

        $result = $output->fetch();
        $this->assertStringContainsString('Name', $result);
        $this->assertStringContainsString('LatticePHP', $result);
    }

    #[Test]
    public function style_panel_outputs_bordered_content(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);
        $style->panel('Title', 'Some content here');

        $result = $output->fetch();
        $this->assertStringContainsString('Title', $result);
        $this->assertStringContainsString('Some content here', $result);
        $this->assertStringContainsString("\u{250C}", $result);
        $this->assertStringContainsString("\u{2518}", $result);
    }

    #[Test]
    public function style_tree_outputs_hierarchical_data(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);
        $style->tree([
            'Root' => [
                'child1',
                'child2',
            ],
        ]);

        $result = $output->fetch();
        $this->assertStringContainsString('Root', $result);
        $this->assertStringContainsString('child1', $result);
        $this->assertStringContainsString('child2', $result);
    }

    #[Test]
    public function style_progress_bar_creates_bar(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);
        $bar = $style->progressBar(10);

        $this->assertSame(10, $bar->getMaxSteps());
    }

    #[Test]
    public function style_new_line_outputs_blank_lines(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);
        $style->newLine(2);

        $result = $output->fetch();
        // Normalize line endings for cross-platform compatibility
        $normalized = str_replace("\r\n", "\n", $result);
        $this->assertSame("\n\n", $normalized);
    }

    #[Test]
    public function style_separator_outputs_line(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);
        $style->separator();

        $result = $output->fetch();
        $this->assertStringContainsString("\u{2500}", $result);
    }

    #[Test]
    public function style_exposes_output_interface(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);
        $this->assertSame($output, $style->getOutput());
    }

    #[Test]
    public function migrate_command_handles_missing_directory(): void
    {
        $db = new IlluminateDatabaseManager(['driver' => 'sqlite', 'database' => ':memory:']);
        $discoverer = new ModuleMigrationDiscoverer();
        $command = new MigrateCommand($db, $discoverer);
        $tester = new CommandTester($command);
        $tester->execute(['--path' => '/nonexistent/path']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Migration directory not found', $tester->getDisplay());
    }

    #[Test]
    public function migrate_rollback_command_handles_missing_directory(): void
    {
        $db = new IlluminateDatabaseManager(['driver' => 'sqlite', 'database' => ':memory:']);
        $discoverer = new ModuleMigrationDiscoverer();
        $command = new MigrateRollbackCommand($db, $discoverer);
        $tester = new CommandTester($command);
        $tester->execute(['--path' => '/nonexistent/path']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Migration directory not found', $tester->getDisplay());
    }

    #[Test]
    public function migrate_fresh_command_handles_missing_directory(): void
    {
        $db = new IlluminateDatabaseManager(['driver' => 'sqlite', 'database' => ':memory:']);
        $discoverer = new ModuleMigrationDiscoverer();
        $command = new MigrateFreshCommand($db, $discoverer);
        $tester = new CommandTester($command);
        $tester->execute(['--path' => '/nonexistent/path']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    #[Test]
    public function db_seed_command_handles_missing_directory(): void
    {
        $db = new IlluminateDatabaseManager(['driver' => 'sqlite', 'database' => ':memory:']);
        $discoverer = new ModuleMigrationDiscoverer();
        $command = new DbSeedCommand($db, $discoverer);
        $tester = new CommandTester($command);
        $tester->execute(['--path' => '/nonexistent/path']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Seeder directory not found', $tester->getDisplay());
    }

    #[Test]
    public function make_module_command_creates_files_on_disk(): void
    {
        $tmpDir = sys_get_temp_dir() . '/lattice-test-' . uniqid();
        mkdir($tmpDir, 0755, true);

        $command = new MakeModuleCommand();
        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'TestModule',
            '--path' => $tmpDir,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertDirectoryExists($tmpDir . '/TestModule');
        $this->assertDirectoryExists($tmpDir . '/TestModule/Controllers');
        $this->assertFileExists($tmpDir . '/TestModule/TestModuleModule.php');

        // Cleanup
        $this->removeDirectory($tmpDir);
    }

    #[Test]
    public function make_controller_command_creates_file_on_disk(): void
    {
        $tmpDir = sys_get_temp_dir() . '/lattice-test-' . uniqid();
        $originalDir = getcwd();
        mkdir($tmpDir, 0755, true);
        chdir($tmpDir);

        $command = new MakeControllerCommand();
        $tester = new CommandTester($command);
        $tester->execute(['name' => 'User']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertFileExists($tmpDir . '/src/Controllers/UserController.php');

        chdir($originalDir);
        $this->removeDirectory($tmpDir);
    }

    #[Test]
    public function make_model_command_creates_model_with_migration(): void
    {
        $tmpDir = sys_get_temp_dir() . '/lattice-test-' . uniqid();
        $originalDir = getcwd();
        mkdir($tmpDir, 0755, true);
        chdir($tmpDir);

        $command = new MakeModelCommand();
        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'Product',
            '--migration' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertFileExists($tmpDir . '/app/Models/Product.php');
        $this->assertDirectoryExists($tmpDir . '/database/migrations');

        // Check migration file exists
        $migrations = glob($tmpDir . '/database/migrations/*.php');
        $this->assertNotEmpty($migrations);

        chdir($originalDir);
        $this->removeDirectory($tmpDir);
    }

    #[Test]
    public function make_dto_command_creates_file_with_fields(): void
    {
        $tmpDir = sys_get_temp_dir() . '/lattice-test-' . uniqid();
        $originalDir = getcwd();
        mkdir($tmpDir, 0755, true);
        chdir($tmpDir);

        $command = new MakeDtoCommand();
        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'CreateUser',
            '--fields' => 'name:string,age:int',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertFileExists($tmpDir . '/app/Dto/CreateUserDto.php');

        $content = file_get_contents($tmpDir . '/app/Dto/CreateUserDto.php');
        $this->assertStringContainsString('string $name', $content);
        $this->assertStringContainsString('int $age', $content);

        chdir($originalDir);
        $this->removeDirectory($tmpDir);
    }

    #[Test]
    public function make_policy_command_creates_file(): void
    {
        $tmpDir = sys_get_temp_dir() . '/lattice-test-' . uniqid();
        $originalDir = getcwd();
        mkdir($tmpDir, 0755, true);
        chdir($tmpDir);

        $command = new MakePolicyCommand();
        $tester = new CommandTester($command);
        $tester->execute(['name' => 'Order']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertFileExists($tmpDir . '/app/Policies/OrderPolicy.php');

        $content = file_get_contents($tmpDir . '/app/Policies/OrderPolicy.php');
        $this->assertStringContainsString('PolicyInterface', $content);
        $this->assertStringContainsString("'view'", $content);
        $this->assertStringContainsString("'delete'", $content);

        chdir($originalDir);
        $this->removeDirectory($tmpDir);
    }

    #[Test]
    public function make_workflow_command_creates_workflow_and_activities(): void
    {
        $tmpDir = sys_get_temp_dir() . '/lattice-test-' . uniqid();
        $originalDir = getcwd();
        mkdir($tmpDir, 0755, true);
        chdir($tmpDir);

        $command = new MakeWorkflowCommand();
        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'OrderFulfillment',
            '--activities' => 'ValidateOrder,ChargePayment,ShipOrder',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertFileExists($tmpDir . '/app/Workflows/OrderFulfillmentWorkflow.php');
        $this->assertFileExists($tmpDir . '/app/Activities/ValidateOrderActivity.php');
        $this->assertFileExists($tmpDir . '/app/Activities/ChargePaymentActivity.php');
        $this->assertFileExists($tmpDir . '/app/Activities/ShipOrderActivity.php');
        $this->assertFileExists($tmpDir . '/tests/Workflows/OrderFulfillmentWorkflowTest.php');

        chdir($originalDir);
        $this->removeDirectory($tmpDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
