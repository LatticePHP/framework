<?php

declare(strict_types=1);

namespace Lattice\Queue\Driver;

use Lattice\Queue\SerializedJob;

final class DatabaseDriver implements QueueDriverInterface
{
    private const string TABLE = 'queue_jobs';
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly \PDO $pdo,
    ) {
    }

    public function push(string $queue, SerializedJob $job): string
    {
        $stmt = $this->pdo->prepare(sprintf(
            'INSERT INTO %s (id, queue, payload, attempts, max_attempts, timeout, available_at, created_at)
             VALUES (:id, :queue, :payload, :attempts, :max_attempts, :timeout, :available_at, :created_at)',
            self::TABLE,
        ));

        $stmt->execute([
            'id' => $job->id,
            'queue' => $queue,
            'payload' => $job->payload,
            'attempts' => $job->attempts,
            'max_attempts' => $job->maxAttempts,
            'timeout' => $job->timeout,
            'available_at' => $job->availableAt->format(self::DATETIME_FORMAT),
            'created_at' => $job->createdAt->format(self::DATETIME_FORMAT),
        ]);

        return $job->id;
    }

    public function pushDelayed(string $queue, SerializedJob $job, int $delaySeconds): string
    {
        $delayed = $job->withAvailableAt(
            new \DateTimeImmutable("+{$delaySeconds} seconds"),
        );

        return $this->push($queue, $delayed);
    }

    public function pop(string $queue): ?SerializedJob
    {
        $now = (new \DateTimeImmutable())->format(self::DATETIME_FORMAT);

        $stmt = $this->pdo->prepare(sprintf(
            'SELECT * FROM %s
             WHERE queue = :queue
               AND reserved_at IS NULL
               AND failed_at IS NULL
               AND available_at <= :now
             ORDER BY created_at ASC
             LIMIT 1',
            self::TABLE,
        ));

        $stmt->execute([
            'queue' => $queue,
            'now' => $now,
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        // Mark as reserved
        $update = $this->pdo->prepare(sprintf(
            'UPDATE %s SET reserved_at = :reserved_at WHERE id = :id',
            self::TABLE,
        ));
        $update->execute([
            'reserved_at' => $now,
            'id' => $row['id'],
        ]);

        return new SerializedJob(
            id: $row['id'],
            queue: $row['queue'],
            payload: $row['payload'],
            attempts: (int) $row['attempts'],
            maxAttempts: (int) $row['max_attempts'],
            timeout: (int) $row['timeout'],
            availableAt: new \DateTimeImmutable($row['available_at']),
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
    }

    public function acknowledge(string $jobId): void
    {
        $stmt = $this->pdo->prepare(sprintf(
            'DELETE FROM %s WHERE id = :id',
            self::TABLE,
        ));

        $stmt->execute(['id' => $jobId]);
    }

    public function fail(string $jobId, \Throwable $reason): void
    {
        $stmt = $this->pdo->prepare(sprintf(
            'UPDATE %s SET failed_at = :failed_at, error = :error WHERE id = :id',
            self::TABLE,
        ));

        $stmt->execute([
            'failed_at' => (new \DateTimeImmutable())->format(self::DATETIME_FORMAT),
            'error' => $reason->getMessage() . "\n" . $reason->getTraceAsString(),
            'id' => $jobId,
        ]);
    }

    public function size(string $queue): int
    {
        $stmt = $this->pdo->prepare(sprintf(
            'SELECT COUNT(*) FROM %s WHERE queue = :queue AND failed_at IS NULL',
            self::TABLE,
        ));

        $stmt->execute(['queue' => $queue]);

        return (int) $stmt->fetchColumn();
    }
}
