<?php

declare(strict_types=1);

namespace Lattice\Database;

final class ConnectionManager
{
    /** @var array<string, ConnectionConfig> */
    private array $configs = [];

    /** @var array<string, ConnectionInterface> */
    private array $connections = [];

    private string $defaultConnection = 'default';

    public function addConnection(string $name, ConnectionConfig $config): void
    {
        $this->configs[$name] = $config;
    }

    public function connection(string $name = 'default'): ConnectionInterface
    {
        if (!isset($this->configs[$name])) {
            throw new \InvalidArgumentException("Database connection '{$name}' is not configured.");
        }

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->createConnection($this->configs[$name]);
        }

        return $this->connections[$name];
    }

    public function getDefaultConnectionName(): string
    {
        return $this->defaultConnection;
    }

    private function createConnection(ConnectionConfig $config): ConnectionInterface
    {
        return match ($config->driver) {
            'sqlite' => new SqliteConnection($config),
            default => new PdoConnection($config),
        };
    }
}
