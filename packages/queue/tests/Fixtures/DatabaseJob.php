<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Fixtures;

use Lattice\Queue\AbstractJob;

/**
 * A job that inserts a row into an SQLite database.
 * The database path must be set before dispatching.
 */
final class DatabaseJob extends AbstractJob
{
    public static ?string $dbPath = null;

    public function __construct(
        public readonly string $key,
        public readonly string $value,
    ) {
    }

    public function handle(): void
    {
        if (self::$dbPath === null) {
            throw new \RuntimeException('DatabaseJob::$dbPath must be set before handling.');
        }

        $pdo = new \PDO('sqlite:' . self::$dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare('INSERT INTO job_results (key, value) VALUES (:key, :value)');
        $stmt->execute(['key' => $this->key, 'value' => $this->value]);
    }

    public static function reset(): void
    {
        self::$dbPath = null;
    }
}
