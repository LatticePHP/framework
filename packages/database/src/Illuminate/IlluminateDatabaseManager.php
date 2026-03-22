<?php

declare(strict_types=1);

namespace Lattice\Database\Illuminate;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Events\Dispatcher;

/**
 * Manages the Illuminate Database (Capsule) lifecycle.
 *
 * This is the RECOMMENDED database manager for LatticePHP applications.
 * It wraps Laravel's illuminate/database Capsule, giving you access to
 * Eloquent, the full query builder, schema builder, and migrations.
 */
final class IlluminateDatabaseManager
{
    private Capsule $capsule;

    /**
     * @param array<string, mixed> $config Illuminate-style connection config
     *   e.g. ['driver' => 'sqlite', 'database' => ':memory:']
     *   e.g. ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'app', ...]
     */
    public function __construct(array $config)
    {
        $this->capsule = new Capsule();
        $this->capsule->addConnection($config);
        $this->capsule->setEventDispatcher(new Dispatcher());
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }

    /**
     * Get an Illuminate database connection by name.
     */
    public function connection(?string $name = null): Connection
    {
        return $this->capsule->getConnection($name ?? 'default');
    }

    /**
     * Get an Illuminate query builder for the given table.
     */
    public function table(string $table): Builder
    {
        return $this->capsule->table($table);
    }

    /**
     * Get the Illuminate schema builder for DDL operations.
     */
    public function schema(?string $connection = null): SchemaBuilder
    {
        return $this->capsule->schema($connection);
    }

    /**
     * Add an additional named connection.
     *
     * @param array<string, mixed> $config
     */
    public function addConnection(array $config, string $name = 'default'): void
    {
        $this->capsule->addConnection($config, $name);
    }

    /**
     * Get the underlying Capsule instance for advanced usage.
     */
    public function getCapsule(): Capsule
    {
        return $this->capsule;
    }
}
