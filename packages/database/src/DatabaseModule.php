<?php

declare(strict_types=1);

namespace Lattice\Database;

use Lattice\Contracts\Module\DynamicModuleInterface;
use Lattice\Contracts\Module\ModuleDefinitionInterface;
use Lattice\Database\Illuminate\IlluminateDatabaseManager;
use Lattice\Database\Illuminate\IlluminateMigrationRunner;
use Lattice\Database\Illuminate\IlluminateQueryBuilder;
use Lattice\Database\Illuminate\IlluminateSchemaBuilder;

/**
 * Database module for LatticePHP.
 *
 * The DEFAULT path uses illuminate/database under the hood via IlluminateDatabaseManager.
 * This gives you Eloquent, the full query builder, schema builder, and migrations.
 *
 * A lightweight PDO-only path is still available via the legacy ConnectionManager
 * for users who don't want the full Illuminate dependency (though it IS installed
 * as a package dependency).
 */
final class DatabaseModule implements DynamicModuleInterface, ModuleDefinitionInterface
{
    private function __construct(
        private readonly ConnectionConfig $config,
        private ?IlluminateDatabaseManager $illuminateManager = null,
    ) {}

    public static function register(mixed ...$options): ModuleDefinitionInterface
    {
        $config = $options[0] ?? ConnectionConfig::sqlite(':memory:');

        if (!$config instanceof ConnectionConfig) {
            throw new \InvalidArgumentException('First argument must be a ConnectionConfig instance.');
        }

        return new self($config);
    }

    /**
     * Create a module configured for SQLite (recommended for testing).
     */
    public static function forSqlite(string $database = ':memory:'): self
    {
        $config = ConnectionConfig::sqlite($database);
        $instance = new self($config);
        $instance->illuminateManager = new IlluminateDatabaseManager([
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        return $instance;
    }

    /**
     * Create a module configured for PostgreSQL.
     */
    public static function forPostgres(
        string $host = '127.0.0.1',
        string $database = '',
        string $username = 'postgres',
        string $password = '',
        int $port = 5432,
    ): self {
        $config = ConnectionConfig::postgres(
            host: $host,
            port: $port,
            database: $database,
            username: $username,
            password: $password,
        );
        $instance = new self($config);
        $instance->illuminateManager = new IlluminateDatabaseManager([
            'driver' => 'pgsql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ]);

        return $instance;
    }

    /**
     * Create a module configured for MySQL/MariaDB.
     */
    public static function forMysql(
        string $host = '127.0.0.1',
        string $database = '',
        string $username = 'root',
        string $password = '',
        int $port = 3306,
    ): self {
        $config = ConnectionConfig::mysql(
            host: $host,
            port: $port,
            database: $database,
            username: $username,
            password: $password,
        );
        $instance = new self($config);
        $instance->illuminateManager = new IlluminateDatabaseManager([
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);

        return $instance;
    }

    /**
     * Create a module from raw Illuminate-style config array.
     *
     * @param array<string, mixed> $illuminateConfig
     */
    public static function fromIlluminateConfig(array $illuminateConfig): self
    {
        $driver = $illuminateConfig['driver'] ?? 'sqlite';
        $connectionConfig = new ConnectionConfig(
            driver: $driver,
            host: $illuminateConfig['host'] ?? '',
            port: (int) ($illuminateConfig['port'] ?? 0),
            database: $illuminateConfig['database'] ?? '',
            username: $illuminateConfig['username'] ?? '',
            password: $illuminateConfig['password'] ?? '',
            charset: $illuminateConfig['charset'] ?? 'utf8mb4',
            collation: $illuminateConfig['collation'] ?? 'utf8mb4_unicode_ci',
            prefix: $illuminateConfig['prefix'] ?? '',
        );
        $instance = new self($connectionConfig);
        $instance->illuminateManager = new IlluminateDatabaseManager($illuminateConfig);

        return $instance;
    }

    /**
     * Get the Illuminate database manager (RECOMMENDED).
     *
     * Returns the IlluminateDatabaseManager which wraps Laravel's Capsule.
     * Use this for Eloquent, the full query builder, schema builder, etc.
     */
    public function getIlluminateManager(): IlluminateDatabaseManager
    {
        if ($this->illuminateManager === null) {
            $this->illuminateManager = new IlluminateDatabaseManager(
                $this->buildIlluminateConfig(),
            );
        }

        return $this->illuminateManager;
    }

    public function getConnectionConfig(): ConnectionConfig
    {
        return $this->config;
    }

    /** @return array<class-string> */
    public function getImports(): array
    {
        return [];
    }

    /** @return array<class-string> */
    public function getProviders(): array
    {
        return [
            ConnectionManager::class,
            TransactionalInterceptor::class,
        ];
    }

    /** @return array<class-string> */
    public function getControllers(): array
    {
        return [];
    }

    /** @return array<class-string> */
    public function getExports(): array
    {
        return [
            ConnectionManager::class,
            ConnectionInterface::class,
            IlluminateDatabaseManager::class,
        ];
    }

    /**
     * Build an Illuminate-style config array from our ConnectionConfig.
     *
     * @return array<string, mixed>
     */
    private function buildIlluminateConfig(): array
    {
        $config = [
            'driver' => $this->config->driver,
            'database' => $this->config->database,
            'prefix' => $this->config->prefix,
        ];

        if ($this->config->driver === 'sqlite') {
            $config['foreign_key_constraints'] = true;
        } else {
            $config['host'] = $this->config->host;
            $config['port'] = $this->config->port;
            $config['username'] = $this->config->username;
            $config['password'] = $this->config->password;
            $config['charset'] = $this->config->charset;

            if ($this->config->collation !== '') {
                $config['collation'] = $this->config->collation;
            }
        }

        return $config;
    }
}
