<?php

declare(strict_types=1);

namespace Lattice\Database;

use PDO;
use PDOStatement;

class PdoConnection implements ConnectionInterface
{
    private ?PDO $pdo = null;

    public function __construct(
        protected readonly ConnectionConfig $config,
    ) {}

    protected function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->createPdo();
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }

        return $this->pdo;
    }

    protected function createPdo(): PDO
    {
        $dsn = match ($this->config->driver) {
            'mysql' => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->config->host,
                $this->config->port,
                $this->config->database,
                $this->config->charset,
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $this->config->host,
                $this->config->port,
                $this->config->database,
            ),
            'sqlite' => sprintf('sqlite:%s', $this->config->database),
            default => throw new \InvalidArgumentException("Unsupported driver: {$this->config->driver}"),
        };

        return new PDO(
            $dsn,
            $this->config->username ?: null,
            $this->config->password ?: null,
            $this->config->options,
        );
    }

    public function query(string $sql, array $bindings = []): array
    {
        $statement = $this->prepareAndExecute($sql, $bindings);

        return $statement->fetchAll();
    }

    public function execute(string $sql, array $bindings = []): int
    {
        $statement = $this->prepareAndExecute($sql, $bindings);

        return $statement->rowCount();
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function beginTransaction(): void
    {
        $this->getPdo()->beginTransaction();
    }

    public function commit(): void
    {
        $this->getPdo()->commit();
    }

    public function rollBack(): void
    {
        $this->getPdo()->rollBack();
    }

    public function lastInsertId(): string|int
    {
        $id = $this->getPdo()->lastInsertId();

        return is_numeric($id) ? (int) $id : $id;
    }

    public function getDriverName(): string
    {
        return $this->config->driver;
    }

    private function prepareAndExecute(string $sql, array $bindings): PDOStatement
    {
        $statement = $this->getPdo()->prepare($sql);
        $statement->execute($bindings);

        return $statement;
    }
}
