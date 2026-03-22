<?php

declare(strict_types=1);

namespace Lattice\WorkflowStore\Migration;

/**
 * Migration to create workflow persistence tables.
 *
 * Can be used standalone with PDO or integrated with a migration system.
 */
final class CreateWorkflowTables
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {}

    /**
     * Run the migration (create tables).
     */
    public function up(): void
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $this->upSqlite();
        } else {
            $this->upMysql();
        }
    }

    /**
     * Reverse the migration (drop tables).
     */
    public function down(): void
    {
        $this->pdo->exec('DROP TABLE IF EXISTS workflow_events');
        $this->pdo->exec('DROP TABLE IF EXISTS workflow_executions');
    }

    private function upSqlite(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS workflow_executions (
                id VARCHAR(36) PRIMARY KEY,
                workflow_type VARCHAR(255) NOT NULL,
                workflow_id VARCHAR(255) NOT NULL,
                run_id VARCHAR(255) NOT NULL,
                input TEXT,
                status VARCHAR(20) NOT NULL DEFAULT \'running\',
                result TEXT,
                started_at DATETIME NOT NULL,
                completed_at DATETIME,
                parent_workflow_id VARCHAR(255)
            )
        ');

        $this->pdo->exec('
            CREATE INDEX IF NOT EXISTS idx_workflow_id ON workflow_executions (workflow_id)
        ');

        $this->pdo->exec('
            CREATE INDEX IF NOT EXISTS idx_status ON workflow_executions (status)
        ');

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS workflow_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                execution_id VARCHAR(36) NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                sequence_number INTEGER NOT NULL,
                payload TEXT,
                timestamp DATETIME NOT NULL,
                FOREIGN KEY (execution_id) REFERENCES workflow_executions(id),
                UNIQUE(execution_id, sequence_number)
            )
        ');
    }

    private function upMysql(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS workflow_executions (
                id VARCHAR(36) PRIMARY KEY,
                workflow_type VARCHAR(255) NOT NULL,
                workflow_id VARCHAR(255) NOT NULL,
                run_id VARCHAR(255) NOT NULL,
                input TEXT,
                status VARCHAR(20) NOT NULL DEFAULT \'running\',
                result TEXT,
                started_at DATETIME NOT NULL,
                completed_at DATETIME,
                parent_workflow_id VARCHAR(255),
                INDEX idx_workflow_id (workflow_id),
                INDEX idx_status (status)
            )
        ');

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS workflow_events (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                execution_id VARCHAR(36) NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                sequence_number INTEGER NOT NULL,
                payload TEXT,
                timestamp DATETIME NOT NULL,
                FOREIGN KEY (execution_id) REFERENCES workflow_executions(id),
                UNIQUE(execution_id, sequence_number)
            )
        ');
    }
}
