<?php

declare(strict_types=1);

namespace Lattice\WorkflowStore;

use DateTimeImmutable;
use Lattice\Contracts\Workflow\WorkflowEventInterface;
use Lattice\Contracts\Workflow\WorkflowEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Contracts\Workflow\WorkflowExecutionInterface;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Workflow\Event\WorkflowEvent;
use Lattice\Workflow\WorkflowExecution;

final class DatabaseEventStore implements WorkflowEventStoreInterface
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Creates the required tables if they don't exist.
     */
    public function ensureSchema(): void
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $this->createSqliteSchema();
        } else {
            $this->createMysqlSchema();
        }
    }

    public function createExecution(string $workflowType, string $workflowId, string $runId, mixed $input): string
    {
        $executionId = $this->generateId();
        $now = new DateTimeImmutable();

        $stmt = $this->pdo->prepare(
            'INSERT INTO workflow_executions (id, workflow_type, workflow_id, run_id, input, status, started_at) '
            . 'VALUES (:id, :workflow_type, :workflow_id, :run_id, :input, :status, :started_at)'
        );

        $stmt->execute([
            'id' => $executionId,
            'workflow_type' => $workflowType,
            'workflow_id' => $workflowId,
            'run_id' => $runId,
            'input' => json_encode($input, JSON_THROW_ON_ERROR),
            'status' => WorkflowStatus::Running->value,
            'started_at' => $now->format('Y-m-d H:i:s.u'),
        ]);

        return $executionId;
    }

    public function updateExecutionStatus(string $executionId, WorkflowStatus $status, mixed $result = null): void
    {
        $params = [
            'status' => $status->value,
            'id' => $executionId,
        ];

        $sql = 'UPDATE workflow_executions SET status = :status';

        if ($result !== null) {
            $sql .= ', result = :result, completed_at = :completed_at';
            $params['result'] = json_encode($result, JSON_THROW_ON_ERROR);
            $params['completed_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        }

        $sql .= ' WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException("Execution not found: {$executionId}");
        }
    }

    public function getExecution(string $executionId): ?WorkflowExecutionInterface
    {
        $stmt = $this->pdo->prepare('SELECT * FROM workflow_executions WHERE id = :id');
        $stmt->execute(['id' => $executionId]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrateExecution($row);
    }

    public function findExecutionByWorkflowId(string $workflowId): ?WorkflowExecutionInterface
    {
        $stmt = $this->pdo->prepare('SELECT * FROM workflow_executions WHERE workflow_id = :workflow_id ORDER BY started_at DESC LIMIT 1');
        $stmt->execute(['workflow_id' => $workflowId]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrateExecution($row);
    }

    public function appendEvent(string $executionId, WorkflowEventInterface $event): void
    {
        // Verify execution exists
        $check = $this->pdo->prepare('SELECT id FROM workflow_executions WHERE id = :id');
        $check->execute(['id' => $executionId]);

        if ($check->fetch() === false) {
            throw new \RuntimeException("Execution not found: {$executionId}");
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO workflow_events (execution_id, event_type, sequence_number, payload, timestamp) '
            . 'VALUES (:execution_id, :event_type, :sequence_number, :payload, :timestamp)'
        );

        $stmt->execute([
            'execution_id' => $executionId,
            'event_type' => $event->getEventType()->value,
            'sequence_number' => $event->getSequenceNumber(),
            'payload' => json_encode($event->getPayload(), JSON_THROW_ON_ERROR),
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<WorkflowEventInterface> */
    public function getEvents(string $executionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM workflow_events WHERE execution_id = :execution_id ORDER BY sequence_number ASC'
        );
        $stmt->execute(['execution_id' => $executionId]);

        $events = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $events[] = new WorkflowEvent(
                eventType: WorkflowEventType::from($row['event_type']),
                sequenceNumber: (int) $row['sequence_number'],
                payload: json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR),
                timestamp: new DateTimeImmutable($row['timestamp']),
            );
        }

        return $events;
    }

    private function hydrateExecution(array $row): WorkflowExecution
    {
        $execution = new WorkflowExecution(
            id: $row['id'],
            workflowType: $row['workflow_type'],
            workflowId: $row['workflow_id'],
            runId: $row['run_id'],
            input: json_decode($row['input'], true, 512, JSON_THROW_ON_ERROR),
            startedAt: new DateTimeImmutable($row['started_at']),
            parentWorkflowId: $row['parent_workflow_id'] ?? null,
        );

        $status = WorkflowStatus::from($row['status']);

        if ($status !== WorkflowStatus::Running) {
            $execution->setStatus($status);
        }

        if ($row['result'] !== null) {
            $execution->setResult(json_decode($row['result'], true, 512, JSON_THROW_ON_ERROR));
        }

        return $execution;
    }

    private function generateId(): string
    {
        // UUID v4
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function createSqliteSchema(): void
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

    private function createMysqlSchema(): void
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
