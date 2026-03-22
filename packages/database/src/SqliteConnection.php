<?php

declare(strict_types=1);

namespace Lattice\Database;

/**
 * SQLite-specific connection for testing.
 * Creates an in-memory SQLite database by default.
 */
final class SqliteConnection extends PdoConnection
{
    public function __construct(?ConnectionConfig $config = null)
    {
        parent::__construct($config ?? ConnectionConfig::sqlite(':memory:'));
    }
}
