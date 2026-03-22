<?php

declare(strict_types=1);

namespace Lattice\Database;

/**
 * Base migration class for LatticePHP.
 *
 * This is the root-level migration that works with both the lightweight
 * PDO-based runner and the Illuminate-based runner. Module migrations
 * should extend this class.
 */
abstract class Migration
{
    /**
     * Run the migration forward.
     */
    abstract public function up(\PDO $pdo): void;

    /**
     * Reverse the migration.
     */
    abstract public function down(\PDO $pdo): void;

    /**
     * Get the migration name (defaults to class short name).
     */
    public function getName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }
}
