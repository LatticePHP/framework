<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit;

use Lattice\Core\Application;
use Lattice\Core\Config\ConfigRepository;
use Lattice\Http\Exception\ForbiddenException;
use Lattice\Http\Exception\HttpException;
use Lattice\Http\Exception\NotFoundException;
use Lattice\Http\Exception\UnauthorizedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    private string $tempDir;

    public static function setUpBeforeClass(): void
    {
        // Register autoloader for sibling Http package (monorepo cross-package testing)
        spl_autoload_register(static function (string $class): void {
            $prefix = 'Lattice\\Http\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = __DIR__ . '/../../../http/src/' . $relative . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });
    }

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/lattice_helpers_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Clear any previous instance
        Application::clearInstance();

        // Create a fresh application so helpers can resolve
        new Application(basePath: $this->tempDir);
    }

    protected function tearDown(): void
    {
        Application::clearInstance();

        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        // Clean up $_ENV keys we may have set
        unset($_ENV['APP_ENV'], $_ENV['APP_DEBUG']);
        unset($_SERVER['APP_ENV'], $_SERVER['APP_DEBUG']);
    }

    private function removeDirectory(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    // ------------------------------------------------------------------
    // app() and resolve()
    // ------------------------------------------------------------------

    #[Test]
    public function test_app_returns_application_instance(): void
    {
        $result = app();
        $this->assertInstanceOf(Application::class, $result);
    }

    #[Test]
    public function test_app_resolves_abstract(): void
    {
        $config = app('config');
        $this->assertInstanceOf(ConfigRepository::class, $config);
    }

    #[Test]
    public function test_resolve_delegates_to_app(): void
    {
        $config = resolve('config');
        $this->assertInstanceOf(ConfigRepository::class, $config);
    }

    // ------------------------------------------------------------------
    // config()
    // ------------------------------------------------------------------

    #[Test]
    public function test_config_returns_repository_when_no_key(): void
    {
        $result = config();
        $this->assertInstanceOf(ConfigRepository::class, $result);
    }

    #[Test]
    public function test_config_reads_from_config_repository(): void
    {
        $config = app('config');
        $config->set('app.name', 'LatticePHP');

        $this->assertSame('LatticePHP', config('app.name'));
    }

    #[Test]
    public function test_config_returns_default_when_key_not_found(): void
    {
        $this->assertSame('fallback', config('nonexistent.key', 'fallback'));
    }

    // ------------------------------------------------------------------
    // env()
    // ------------------------------------------------------------------

    #[Test]
    public function test_env_reads_from_env_superglobal(): void
    {
        $_ENV['LATTICE_TEST_KEY'] = 'hello';

        try {
            $this->assertSame('hello', env('LATTICE_TEST_KEY'));
        } finally {
            unset($_ENV['LATTICE_TEST_KEY']);
        }
    }

    #[Test]
    public function test_env_returns_default_when_not_set(): void
    {
        $this->assertSame('default_val', env('ABSOLUTELY_NOT_SET_KEY', 'default_val'));
    }

    #[Test]
    public function test_env_casts_true(): void
    {
        $_ENV['LATTICE_BOOL'] = 'true';
        try {
            $this->assertTrue(env('LATTICE_BOOL'));
        } finally {
            unset($_ENV['LATTICE_BOOL']);
        }
    }

    #[Test]
    public function test_env_casts_parenthesized_true(): void
    {
        $_ENV['LATTICE_BOOL'] = '(true)';
        try {
            $this->assertTrue(env('LATTICE_BOOL'));
        } finally {
            unset($_ENV['LATTICE_BOOL']);
        }
    }

    #[Test]
    public function test_env_casts_false(): void
    {
        $_ENV['LATTICE_BOOL'] = 'false';
        try {
            $this->assertFalse(env('LATTICE_BOOL'));
        } finally {
            unset($_ENV['LATTICE_BOOL']);
        }
    }

    #[Test]
    public function test_env_casts_null(): void
    {
        $_ENV['LATTICE_NULL'] = 'null';
        try {
            $this->assertNull(env('LATTICE_NULL'));
        } finally {
            unset($_ENV['LATTICE_NULL']);
        }
    }

    #[Test]
    public function test_env_casts_empty(): void
    {
        $_ENV['LATTICE_EMPTY'] = 'empty';
        try {
            $this->assertSame('', env('LATTICE_EMPTY'));
        } finally {
            unset($_ENV['LATTICE_EMPTY']);
        }
    }

    // ------------------------------------------------------------------
    // abort / abort_if / abort_unless
    // ------------------------------------------------------------------

    #[Test]
    public function test_abort_throws_unauthorized_exception(): void
    {
        $this->expectException(UnauthorizedException::class);
        abort(401);
    }

    #[Test]
    public function test_abort_throws_forbidden_exception(): void
    {
        $this->expectException(ForbiddenException::class);
        abort(403);
    }

    #[Test]
    public function test_abort_throws_not_found_exception(): void
    {
        $this->expectException(NotFoundException::class);
        abort(404);
    }

    #[Test]
    public function test_abort_throws_http_exception_for_429(): void
    {
        $this->expectException(HttpException::class);
        abort(429);
    }

    #[Test]
    public function test_abort_throws_http_exception_for_generic_code(): void
    {
        $this->expectException(HttpException::class);
        abort(500, 'Server Error');
    }

    #[Test]
    public function test_abort_uses_custom_message(): void
    {
        try {
            abort(404, 'Page not found');
        } catch (NotFoundException $e) {
            $this->assertSame('Page not found', $e->getMessage());
            return;
        }

        $this->fail('Expected NotFoundException was not thrown.');
    }

    #[Test]
    public function test_abort_if_throws_when_condition_true(): void
    {
        $this->expectException(NotFoundException::class);
        abort_if(true, 404);
    }

    #[Test]
    public function test_abort_if_does_not_throw_when_condition_false(): void
    {
        abort_if(false, 404);
        $this->assertTrue(true); // No exception thrown
    }

    #[Test]
    public function test_abort_unless_throws_when_condition_false(): void
    {
        $this->expectException(NotFoundException::class);
        abort_unless(false, 404);
    }

    #[Test]
    public function test_abort_unless_does_not_throw_when_condition_true(): void
    {
        abort_unless(true, 404);
        $this->assertTrue(true); // No exception thrown
    }

    // ------------------------------------------------------------------
    // Path helpers
    // ------------------------------------------------------------------

    #[Test]
    public function test_base_path_returns_application_base(): void
    {
        $this->assertSame($this->tempDir, base_path());
    }

    #[Test]
    public function test_base_path_appends_relative_path(): void
    {
        $expected = $this->tempDir . DIRECTORY_SEPARATOR . 'some/file.php';
        $this->assertSame($expected, base_path('some/file.php'));
    }

    #[Test]
    public function test_config_path_returns_config_directory(): void
    {
        $expected = $this->tempDir . DIRECTORY_SEPARATOR . 'config';
        $this->assertSame($expected, config_path());
    }

    #[Test]
    public function test_config_path_appends_relative_path(): void
    {
        $expected = $this->tempDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
        $this->assertSame($expected, config_path('app.php'));
    }

    #[Test]
    public function test_database_path_returns_database_directory(): void
    {
        $expected = $this->tempDir . DIRECTORY_SEPARATOR . 'database';
        $this->assertSame($expected, database_path());
    }

    #[Test]
    public function test_database_path_appends_relative_path(): void
    {
        $expected = $this->tempDir . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        $this->assertSame($expected, database_path('migrations'));
    }

    #[Test]
    public function test_storage_path_returns_storage_directory(): void
    {
        $expected = $this->tempDir . DIRECTORY_SEPARATOR . 'storage';
        $this->assertSame($expected, storage_path());
    }

    #[Test]
    public function test_storage_path_appends_relative_path(): void
    {
        $expected = $this->tempDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        $this->assertSame($expected, storage_path('logs'));
    }

    // ------------------------------------------------------------------
    // now()
    // ------------------------------------------------------------------

    #[Test]
    public function test_now_returns_datetimeimmutable(): void
    {
        $result = now();
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    #[Test]
    public function test_now_returns_current_time(): void
    {
        $before = new \DateTimeImmutable();
        $result = now();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $result->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $result->getTimestamp());
    }

    // ------------------------------------------------------------------
    // Application environment helpers
    // ------------------------------------------------------------------

    #[Test]
    public function test_environment_defaults_to_production(): void
    {
        $app = app();
        $this->assertSame('production', $app->environment());
    }

    #[Test]
    public function test_environment_reads_app_env(): void
    {
        $_ENV['APP_ENV'] = 'testing';
        $this->assertSame('testing', app()->environment());
    }

    #[Test]
    public function test_is_production(): void
    {
        // Default is production
        $this->assertTrue(app()->isProduction());
    }

    #[Test]
    public function test_is_local(): void
    {
        $_ENV['APP_ENV'] = 'local';
        $this->assertTrue(app()->isLocal());
        $this->assertFalse(app()->isProduction());
    }

    #[Test]
    public function test_is_testing(): void
    {
        $_ENV['APP_ENV'] = 'testing';
        $this->assertTrue(app()->isTesting());
    }

    #[Test]
    public function test_is_debug(): void
    {
        $_ENV['APP_DEBUG'] = 'true';
        $this->assertTrue(app()->isDebug());

        $_ENV['APP_DEBUG'] = 'false';
        $this->assertFalse(app()->isDebug());
    }

    // ------------------------------------------------------------------
    // StorageDirectories
    // ------------------------------------------------------------------

    #[Test]
    public function test_storage_directories_are_created(): void
    {
        \Lattice\Core\Bootstrap\StorageDirectories::ensure($this->tempDir);

        $this->assertDirectoryExists($this->tempDir . '/storage');
        $this->assertDirectoryExists($this->tempDir . '/storage/app');
        $this->assertDirectoryExists($this->tempDir . '/storage/framework');
        $this->assertDirectoryExists($this->tempDir . '/storage/framework/cache');
        $this->assertDirectoryExists($this->tempDir . '/storage/framework/views');
        $this->assertDirectoryExists($this->tempDir . '/storage/logs');
    }

    #[Test]
    public function test_storage_directories_idempotent(): void
    {
        \Lattice\Core\Bootstrap\StorageDirectories::ensure($this->tempDir);
        \Lattice\Core\Bootstrap\StorageDirectories::ensure($this->tempDir);

        $this->assertDirectoryExists($this->tempDir . '/storage');
    }

    // ------------------------------------------------------------------
    // Application::getInstance() without instantiation
    // ------------------------------------------------------------------

    #[Test]
    public function test_get_instance_throws_when_not_instantiated(): void
    {
        Application::clearInstance();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Application has not been instantiated.');
        Application::getInstance();
    }
}
