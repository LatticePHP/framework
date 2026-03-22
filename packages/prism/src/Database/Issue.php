<?php

declare(strict_types=1);

namespace Lattice\Prism\Database;

use Lattice\Prism\Event\ErrorLevel;
use Lattice\Prism\Event\IssueStatus;

final class Issue
{
    public function __construct(
        public readonly string $id,
        public readonly string $projectId,
        public readonly string $fingerprint,
        public readonly string $title,
        public readonly ErrorLevel $level,
        public readonly IssueStatus $status,
        public readonly int $count,
        public readonly string $firstSeen,
        public readonly string $lastSeen,
        public readonly ?string $culprit = null,
        public readonly ?string $platform = null,
        public readonly ?string $environment = null,
        public readonly ?string $release = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            projectId: (string) $data['project_id'],
            fingerprint: (string) $data['fingerprint'],
            title: (string) $data['title'],
            level: ErrorLevel::from((string) $data['level']),
            status: IssueStatus::from((string) ($data['status'] ?? 'unresolved')),
            count: (int) ($data['count'] ?? $data['event_count'] ?? 0),
            firstSeen: (string) $data['first_seen'],
            lastSeen: (string) $data['last_seen'],
            culprit: isset($data['culprit']) ? (string) $data['culprit'] : null,
            platform: isset($data['platform']) ? (string) $data['platform'] : null,
            environment: isset($data['environment']) ? (string) $data['environment'] : null,
            release: isset($data['release']) ? (string) $data['release'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->projectId,
            'fingerprint' => $this->fingerprint,
            'title' => $this->title,
            'level' => $this->level->value,
            'status' => $this->status->value,
            'count' => $this->count,
            'first_seen' => $this->firstSeen,
            'last_seen' => $this->lastSeen,
            'culprit' => $this->culprit,
            'platform' => $this->platform,
            'environment' => $this->environment,
            'release' => $this->release,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
