<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Console\Commands;

use Lattice\Core\Console\Commands\RouteCacheCommand;
use Lattice\Routing\RouteDefinition;
use Lattice\Routing\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RouteCacheCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/lattice_route_cache_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function it_constructs_with_correct_name(): void
    {
        $router = new Router();
        $command = new RouteCacheCommand($router, $this->tempDir);

        $this->assertSame('route:cache', $command->getName());
        $this->assertSame('Create a route cache file for faster route registration', $command->getDescription());
    }

    #[Test]
    public function it_caches_routes_to_file(): void
    {
        $router = new Router();
        $router->addRoute(new RouteDefinition(
            httpMethod: 'GET',
            path: '/users',
            controllerClass: 'App\\Controllers\\UserController',
            methodName: 'index',
            parameterBindings: [],
            name: 'users.index',
        ));
        $router->addRoute(new RouteDefinition(
            httpMethod: 'POST',
            path: '/users',
            controllerClass: 'App\\Controllers\\UserController',
            methodName: 'store',
            parameterBindings: [],
        ));

        $command = new RouteCacheCommand($router, $this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Routes cached successfully', $tester->getDisplay());
        $this->assertStringContainsString('Routes', $tester->getDisplay());

        $cachePath = $this->tempDir . '/bootstrap/cache/routes.php';
        $this->assertFileExists($cachePath);

        $cached = require $cachePath;
        $this->assertIsArray($cached);
        $this->assertCount(2, $cached);
        $this->assertSame('GET', $cached[0]['httpMethod']);
        $this->assertSame('/users', $cached[0]['path']);
        $this->assertSame('users.index', $cached[0]['name']);
    }

    #[Test]
    public function it_creates_cache_directory_if_missing(): void
    {
        $router = new Router();
        $command = new RouteCacheCommand($router, $this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertDirectoryExists($this->tempDir . '/bootstrap/cache');
    }

    #[Test]
    public function it_caches_empty_routes(): void
    {
        $router = new Router();
        $command = new RouteCacheCommand($router, $this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $cachePath = $this->tempDir . '/bootstrap/cache/routes.php';
        $cached = require $cachePath;
        $this->assertSame([], $cached);
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
