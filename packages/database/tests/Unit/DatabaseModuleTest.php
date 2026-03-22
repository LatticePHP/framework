<?php

declare(strict_types=1);

namespace Lattice\Database\Tests\Unit;

use Lattice\Contracts\Module\ModuleDefinitionInterface;
use Lattice\Database\ConnectionConfig;
use Lattice\Database\DatabaseModule;
use Lattice\Database\Illuminate\IlluminateDatabaseManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DatabaseModuleTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Illuminate\Database\Capsule\Manager::class)) {
            $this->markTestSkipped('illuminate/database not installed');
        }
    }

    #[Test]
    public function it_creates_sqlite_module(): void
    {
        $module = DatabaseModule::forSqlite(':memory:');

        $this->assertInstanceOf(ModuleDefinitionInterface::class, $module);
        $config = $module->getConnectionConfig();
        $this->assertInstanceOf(ConnectionConfig::class, $config);
        $this->assertSame('sqlite', $config->driver);
        $this->assertSame(':memory:', $config->database);
    }

    #[Test]
    public function it_creates_postgres_module(): void
    {
        $module = DatabaseModule::forPostgres(
            host: 'localhost',
            database: 'app',
            username: 'postgres',
            password: 'secret',
        );

        $this->assertInstanceOf(ModuleDefinitionInterface::class, $module);
        $config = $module->getConnectionConfig();
        $this->assertSame('pgsql', $config->driver);
        $this->assertSame('localhost', $config->host);
        $this->assertSame('app', $config->database);
        $this->assertSame('postgres', $config->username);
        $this->assertSame('secret', $config->password);
        $this->assertSame(5432, $config->port);
    }

    #[Test]
    public function it_creates_mysql_module(): void
    {
        $module = DatabaseModule::forMysql(
            host: 'localhost',
            database: 'app',
            username: 'root',
            password: 'secret',
        );

        $this->assertInstanceOf(ModuleDefinitionInterface::class, $module);
        $config = $module->getConnectionConfig();
        $this->assertSame('mysql', $config->driver);
        $this->assertSame('localhost', $config->host);
        $this->assertSame('app', $config->database);
        $this->assertSame('root', $config->username);
        $this->assertSame('secret', $config->password);
        $this->assertSame(3306, $config->port);
    }

    #[Test]
    public function it_returns_providers(): void
    {
        $module = DatabaseModule::forSqlite(':memory:');
        $this->assertIsArray($module->getProviders());
    }

    #[Test]
    public function it_returns_exports(): void
    {
        $module = DatabaseModule::forSqlite(':memory:');
        $exports = $module->getExports();
        $this->assertIsArray($exports);
    }

    #[Test]
    public function it_provides_illuminate_manager_from_sqlite(): void
    {
        if (!class_exists(\Illuminate\Database\Capsule\Manager::class)) {
            $this->markTestSkipped('illuminate/database not installed');
        }
        $module = DatabaseModule::forSqlite(':memory:');
        $manager = $module->getIlluminateManager();

        $this->assertInstanceOf(IlluminateDatabaseManager::class, $manager);

        // Verify it actually works
        $manager->schema()->create('test', function ($table): void {
            $table->id();
            $table->string('name');
        });

        $manager->table('test')->insert(['name' => 'Alice']);
        $result = $manager->table('test')->first();
        $this->assertSame('Alice', $result->name);
    }

    #[Test]
    public function it_creates_module_from_illuminate_config(): void
    {
        if (!class_exists(\Illuminate\Database\Capsule\Manager::class)) {
            $this->markTestSkipped('illuminate/database not installed');
        }
        $module = DatabaseModule::fromIlluminateConfig([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->assertInstanceOf(ModuleDefinitionInterface::class, $module);
        $this->assertSame('sqlite', $module->getConnectionConfig()->driver);

        $manager = $module->getIlluminateManager();
        $this->assertInstanceOf(IlluminateDatabaseManager::class, $manager);
    }
}
