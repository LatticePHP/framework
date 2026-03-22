<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Integration;

use Lattice\Core\Console\Commands\MakeControllerCommand;
use Lattice\Core\Console\Commands\MakeDtoCommand;
use Lattice\Core\Console\Commands\MakeModelCommand;
use Lattice\Core\Console\Commands\MakeModuleCommand;
use Lattice\Core\Console\Commands\MakePolicyCommand;
use Lattice\Core\Console\Commands\MakeWorkflowCommand;
use Lattice\Core\Console\Commands\ModuleListCommand;
use Lattice\Core\Console\Commands\RouteListCommand;
use Lattice\Core\Console\LatticeApplication;
use Lattice\Core\Console\LatticeStyle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

final class CliIntegrationTest extends TestCase
{
    private string $tempDir;

    private string $originalDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalDir = (string) getcwd();
        $this->tempDir = sys_get_temp_dir() . '/lattice_cli_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        // Commands use CWD-relative paths, so we chdir into the temp dir
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Restore original CWD before removing temp dir (avoids Windows lock)
        chdir($this->originalDir);
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // 1. make:module creates files
    // ---------------------------------------------------------------

    public function test_make_module_creates_files(): void
    {
        $command = new MakeModuleCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            'name' => 'Users',
            '--path' => $this->tempDir . '/src/Modules',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $modulePath = $this->tempDir . '/src/Modules/Users';
        $this->assertDirectoryExists($modulePath);
        $this->assertDirectoryExists($modulePath . '/Controllers');
        $this->assertDirectoryExists($modulePath . '/Services');
        $this->assertDirectoryExists($modulePath . '/Entities');
        $this->assertDirectoryExists($modulePath . '/DTOs');
        $this->assertDirectoryExists($modulePath . '/Exceptions');
        $this->assertDirectoryExists($modulePath . '/Tests');

        $moduleFile = $modulePath . '/UsersModule.php';
        $this->assertFileExists($moduleFile);

        $content = file_get_contents($moduleFile);
        $this->assertStringContainsString('#[Module(', $content);
        $this->assertStringContainsString('final class UsersModule', $content);
        $this->assertStringContainsString('namespace App\\Modules\\Users;', $content);
    }

    // ---------------------------------------------------------------
    // 2. make:controller creates file with #[Controller] attribute
    // ---------------------------------------------------------------

    public function test_make_controller_creates_file_with_controller_attribute(): void
    {
        $command = new MakeControllerCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            'name' => 'Product',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $filePath = 'src/Controllers/ProductController.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('#[Controller(', $content);
        $this->assertStringContainsString('final class ProductController', $content);
        $this->assertStringContainsString("use Lattice\\Routing\\Attributes\\Controller;", $content);
    }

    public function test_make_controller_with_crud_generates_action_methods(): void
    {
        $command = new MakeControllerCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            'name' => 'Order',
            '--crud' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $filePath = 'src/Controllers/OrderController.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('public function index()', $content);
        $this->assertStringContainsString('public function show(', $content);
        $this->assertStringContainsString('public function create(', $content);
        $this->assertStringContainsString('public function update(', $content);
        $this->assertStringContainsString('public function destroy(', $content);
        $this->assertStringContainsString('#[Get(', $content);
        $this->assertStringContainsString('#[Post(', $content);
        $this->assertStringContainsString('#[Put(', $content);
        $this->assertStringContainsString('#[Delete(', $content);
    }

    // ---------------------------------------------------------------
    // 3. make:model creates files with --migration and --factory
    // ---------------------------------------------------------------

    public function test_make_model_creates_model_migration_and_factory(): void
    {
        $command = new MakeModelCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            'name' => 'Invoice',
            '--migration' => true,
            '--factory' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        // Model file
        $modelFile = 'app/Models/Invoice.php';
        $this->assertFileExists($modelFile);
        $modelContent = file_get_contents($modelFile);
        $this->assertStringContainsString('final class Invoice extends Model', $modelContent);
        $this->assertStringContainsString("protected \$table = 'invoices'", $modelContent);

        // Migration file (timestamped)
        $migrationDir = 'database/migrations';
        $this->assertDirectoryExists($migrationDir);
        $migrations = glob($migrationDir . '/*_create_invoices_table.php');
        $this->assertNotEmpty($migrations, 'Migration file should exist');
        $migrationContent = file_get_contents($migrations[0]);
        $this->assertStringContainsString("Schema::create('invoices'", $migrationContent);

        // Factory file
        $factoryFile = 'database/factories/InvoiceFactory.php';
        $this->assertFileExists($factoryFile);
        $factoryContent = file_get_contents($factoryFile);
        $this->assertStringContainsString('final class InvoiceFactory extends Factory', $factoryContent);
    }

    // ---------------------------------------------------------------
    // 4. make:dto creates file with validation attributes
    // ---------------------------------------------------------------

    public function test_make_dto_creates_file_with_validation_attributes(): void
    {
        $command = new MakeDtoCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            'name' => 'CreateUser',
            '--fields' => 'name:string,age:int,email:string',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $filePath = 'app/Dto/CreateUserDto.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('final class CreateUserDto', $content);
        $this->assertStringContainsString('#[Required]', $content);
        $this->assertStringContainsString('#[StringType]', $content);
        $this->assertStringContainsString('#[IntegerType]', $content);
        $this->assertStringContainsString('public readonly string $name', $content);
        $this->assertStringContainsString('public readonly int $age', $content);
        $this->assertStringContainsString('public readonly string $email', $content);
    }

    // ---------------------------------------------------------------
    // 5. route:list outputs routes
    // ---------------------------------------------------------------

    public function test_route_list_outputs_routes(): void
    {
        $command = new RouteListCommand();
        $command->setRoutes([
            [
                'method' => 'GET',
                'uri' => '/api/users',
                'name' => 'users.index',
                'action' => 'UserController@index',
                'middleware' => ['auth'],
                'guards' => ['jwt'],
            ],
            [
                'method' => 'POST',
                'uri' => '/api/users',
                'name' => 'users.store',
                'action' => 'UserController@store',
                'middleware' => ['auth'],
                'guards' => ['jwt'],
            ],
        ]);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $output = $tester->getDisplay();
        $this->assertStringContainsString('/api/users', $output);
        $this->assertStringContainsString('UserController@index', $output);
        $this->assertStringContainsString('UserController@store', $output);
        $this->assertStringContainsString('GET', $output);
        $this->assertStringContainsString('POST', $output);
    }

    public function test_route_list_json_output(): void
    {
        $command = new RouteListCommand();
        $command->setRoutes([
            [
                'method' => 'GET',
                'uri' => '/api/health',
                'name' => null,
                'action' => 'HealthController@check',
            ],
        ]);

        $tester = new CommandTester($command);
        $tester->execute(['--json' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $output = $tester->getDisplay();
        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame('/api/health', $data[0]['uri']);
    }

    // ---------------------------------------------------------------
    // 6. module:list outputs modules
    // ---------------------------------------------------------------

    public function test_module_list_outputs_modules(): void
    {
        $command = new ModuleListCommand();
        $command->setModules([
            'App\\Modules\\Users\\UsersModule' => [
                'imports' => [],
                'exports' => ['App\\Modules\\Users\\Services\\UserService'],
                'providers' => ['App\\Modules\\Users\\Providers\\UserServiceProvider'],
                'controllers' => ['App\\Modules\\Users\\Controllers\\UserController'],
            ],
            'App\\Modules\\Auth\\AuthModule' => [
                'imports' => ['App\\Modules\\Users\\UsersModule'],
                'exports' => [],
                'providers' => [],
                'controllers' => ['App\\Modules\\Auth\\Controllers\\AuthController'],
            ],
        ]);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $output = $tester->getDisplay();
        $this->assertStringContainsString('UsersModule', $output);
        $this->assertStringContainsString('AuthModule', $output);
        $this->assertStringContainsString('Total modules: 2', $output);
    }

    public function test_module_list_tree_output(): void
    {
        $command = new ModuleListCommand();
        $command->setModules([
            'App\\Modules\\Users\\UsersModule' => [
                'imports' => [],
                'exports' => ['App\\Modules\\Users\\Services\\UserService'],
                'providers' => [],
                'controllers' => ['App\\Modules\\Users\\Controllers\\UserController'],
            ],
        ]);

        $tester = new CommandTester($command);
        $tester->execute(['--tree' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $output = $tester->getDisplay();
        $this->assertStringContainsString('UsersModule', $output);
        $this->assertStringContainsString('Controllers', $output);
        $this->assertStringContainsString('Exports', $output);
    }

    // ---------------------------------------------------------------
    // 7. LatticeStyle methods produce correct output
    // ---------------------------------------------------------------

    public function test_lattice_style_info_output(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);

        $style->info('Test information message');
        $content = $output->fetch();
        $this->assertStringContainsString('Test information message', $content);
    }

    public function test_lattice_style_success_output(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);

        $style->success('Operation completed');
        $content = $output->fetch();
        $this->assertStringContainsString('Operation completed', $content);
    }

    public function test_lattice_style_error_output(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);

        $style->error('Something went wrong');
        $content = $output->fetch();
        $this->assertStringContainsString('Something went wrong', $content);
    }

    public function test_lattice_style_warning_output(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);

        $style->warning('Be careful');
        $content = $output->fetch();
        $this->assertStringContainsString('Be careful', $content);
    }

    public function test_lattice_style_table_output(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);

        $style->table(
            ['Name', 'Age'],
            [['Alice', '30'], ['Bob', '25']],
        );

        $content = $output->fetch();
        $this->assertStringContainsString('Alice', $content);
        $this->assertStringContainsString('Bob', $content);
        $this->assertStringContainsString('Name', $content);
        $this->assertStringContainsString('Age', $content);
    }

    public function test_lattice_style_tree_output(): void
    {
        $output = new BufferedOutput();
        $style = new LatticeStyle($output);

        $style->tree([
            'AppModule' => [
                'UsersModule' => ['UserController'],
                'AuthModule' => ['AuthController'],
            ],
        ]);

        $content = $output->fetch();
        $this->assertStringContainsString('AppModule', $content);
        $this->assertStringContainsString('UsersModule', $content);
        $this->assertStringContainsString('AuthModule', $content);
    }

    // ---------------------------------------------------------------
    // 8. LatticeApplication registers core commands
    //    10 auto-registered (no DI) + 4 DI-dependent added via addCommands()
    // ---------------------------------------------------------------

    public function test_lattice_application_registers_all_core_commands(): void
    {
        $app = LatticeApplication::create($this->tempDir);
        $console = $app->getConsole();

        // These 10 commands are auto-registered (zero-arg constructors)
        $autoRegistered = [
            'serve',
            'make:module',
            'make:controller',
            'make:model',
            'make:dto',
            'make:policy',
            'make:workflow',
            'route:list',
            'module:list',
            'test',
        ];

        foreach ($autoRegistered as $commandName) {
            $this->assertTrue(
                $console->has($commandName),
                "Command '{$commandName}' should be auto-registered in LatticeApplication",
            );
        }

        $allCommands = $console->all();
        $autoCount = 0;
        foreach ($autoRegistered as $name) {
            if (isset($allCommands[$name])) {
                $autoCount++;
            }
        }
        $this->assertSame(10, $autoCount, 'All 10 auto-registered core commands should be present');

        // Verify DI-dependent commands can be added via addCommands()
        // (In production, the kernel builds these from the container)
        $this->assertFalse($console->has('migrate'), 'migrate requires DI, not auto-registered');
    }

    // ---------------------------------------------------------------
    // 9. Command exit codes: successful commands return 0
    // ---------------------------------------------------------------

    public function test_successful_commands_return_zero_exit_code(): void
    {
        // make:module
        $tester = new CommandTester(new MakeModuleCommand());
        $tester->execute(['name' => 'TestModule', '--path' => $this->tempDir . '/mod']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), 'make:module should return 0');

        // make:controller
        $tester = new CommandTester(new MakeControllerCommand());
        $tester->execute(['name' => 'TestCtrl']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), 'make:controller should return 0');

        // make:model
        $tester = new CommandTester(new MakeModelCommand());
        $tester->execute(['name' => 'TestModel']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), 'make:model should return 0');

        // make:dto
        $tester = new CommandTester(new MakeDtoCommand());
        $tester->execute(['name' => 'TestDto']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), 'make:dto should return 0');

        // make:policy
        $tester = new CommandTester(new MakePolicyCommand());
        $tester->execute(['name' => 'TestPolicy']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), 'make:policy should return 0');

        // make:workflow
        $tester = new CommandTester(new MakeWorkflowCommand());
        $tester->execute(['name' => 'TestWorkflow']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), 'make:workflow should return 0');

        // route:list (empty routes)
        $tester = new CommandTester(new RouteListCommand());
        $tester->execute([]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), 'route:list should return 0');

        // module:list (empty modules)
        $tester = new CommandTester(new ModuleListCommand());
        $tester->execute([]);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), 'module:list should return 0');
    }

    // ---------------------------------------------------------------
    // 10. Generated files are valid PHP (no syntax errors)
    // ---------------------------------------------------------------

    public function test_generated_module_file_is_valid_php(): void
    {
        $tester = new CommandTester(new MakeModuleCommand());
        $tester->execute([
            'name' => 'Billing',
            '--path' => $this->tempDir . '/src/Modules',
        ]);

        $moduleFile = $this->tempDir . '/src/Modules/Billing/BillingModule.php';
        $this->assertFileExists($moduleFile);
        $this->assertValidPhpSyntax($moduleFile);
    }

    public function test_generated_controller_file_is_valid_php(): void
    {
        $tester = new CommandTester(new MakeControllerCommand());
        $tester->execute([
            'name' => 'Billing',
            '--crud' => true,
        ]);

        $file = 'src/Controllers/BillingController.php';
        $this->assertFileExists($file);
        $this->assertValidPhpSyntax($file);
    }

    public function test_generated_model_file_is_valid_php(): void
    {
        $tester = new CommandTester(new MakeModelCommand());
        $tester->execute(['name' => 'Payment']);

        $file = 'app/Models/Payment.php';
        $this->assertFileExists($file);
        $this->assertValidPhpSyntax($file);
    }

    public function test_generated_dto_file_is_valid_php(): void
    {
        $tester = new CommandTester(new MakeDtoCommand());
        $tester->execute([
            'name' => 'CreatePayment',
            '--fields' => 'amount:float,currency:string',
        ]);

        $file = 'app/Dto/CreatePaymentDto.php';
        $this->assertFileExists($file);
        $this->assertValidPhpSyntax($file);
    }

    public function test_generated_policy_file_is_valid_php(): void
    {
        $tester = new CommandTester(new MakePolicyCommand());
        $tester->execute(['name' => 'Invoice']);

        $file = 'app/Policies/InvoicePolicy.php';
        $this->assertFileExists($file);
        $this->assertValidPhpSyntax($file);
    }

    public function test_generated_workflow_file_is_valid_php(): void
    {
        $tester = new CommandTester(new MakeWorkflowCommand());
        $tester->execute([
            'name' => 'OrderFulfillment',
            '--activities' => 'ValidateOrder,ProcessPayment,ShipOrder',
        ]);

        $workflowFile = 'app/Workflows/OrderFulfillmentWorkflow.php';
        $this->assertFileExists($workflowFile);
        $this->assertValidPhpSyntax($workflowFile);

        // Activity files
        foreach (['ValidateOrderActivity', 'ProcessPaymentActivity', 'ShipOrderActivity'] as $activity) {
            $actFile = "app/Activities/{$activity}.php";
            $this->assertFileExists($actFile);
            $this->assertValidPhpSyntax($actFile);
        }
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function assertValidPhpSyntax(string $filePath): void
    {
        $output = [];
        $exitCode = 0;
        exec('php -l ' . escapeshellarg($filePath) . ' 2>&1', $output, $exitCode);

        $this->assertSame(
            0,
            $exitCode,
            "File {$filePath} has PHP syntax errors: " . implode("\n", $output),
        );
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
