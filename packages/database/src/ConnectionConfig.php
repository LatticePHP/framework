<?php

declare(strict_types=1);

namespace Lattice\Database;

final class ConnectionConfig
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $driver,
        public readonly string $host = '',
        public readonly int $port = 0,
        public readonly string $database = '',
        public readonly string $username = '',
        public readonly string $password = '',
        public readonly string $charset = 'utf8mb4',
        public readonly string $collation = 'utf8mb4_unicode_ci',
        public readonly string $prefix = '',
        public readonly array $options = [],
    ) {}

    public static function sqlite(string $database): self
    {
        return new self(
            driver: 'sqlite',
            database: $database,
        );
    }

    public static function mysql(
        string $host = '127.0.0.1',
        int $port = 3306,
        string $database = '',
        string $username = 'root',
        string $password = '',
        string $charset = 'utf8mb4',
        string $collation = 'utf8mb4_unicode_ci',
        string $prefix = '',
    ): self {
        return new self(
            driver: 'mysql',
            host: $host,
            port: $port,
            database: $database,
            username: $username,
            password: $password,
            charset: $charset,
            collation: $collation,
            prefix: $prefix,
        );
    }

    public static function postgres(
        string $host = '127.0.0.1',
        int $port = 5432,
        string $database = '',
        string $username = 'postgres',
        string $password = '',
        string $charset = 'utf8',
        string $prefix = '',
    ): self {
        return new self(
            driver: 'pgsql',
            host: $host,
            port: $port,
            database: $database,
            username: $username,
            password: $password,
            charset: $charset,
            prefix: $prefix,
        );
    }
}
