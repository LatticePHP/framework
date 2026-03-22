<?php

declare(strict_types=1);

namespace Lattice\Nightwatch;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

final class Entry implements JsonSerializable
{
    public readonly string $uuid;
    public readonly DateTimeImmutable $timestamp;

    /**
     * @param array<string, mixed> $data
     * @param list<string> $tags
     */
    public function __construct(
        public readonly EntryType $type,
        public readonly array $data = [],
        public readonly array $tags = [],
        public readonly ?string $batchId = null,
        ?string $uuid = null,
        ?DateTimeImmutable $timestamp = null,
    ) {
        $this->uuid = $uuid ?? self::generateUuid();
        $this->timestamp = $timestamp ?? new DateTimeImmutable();
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'timestamp' => $this->timestamp->format(DateTimeInterface::RFC3339_EXTENDED),
            'data' => $this->data,
            'tags' => $this->tags,
            'batch_id' => $this->batchId,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: EntryType::from($data['type']),
            data: $data['data'] ?? [],
            tags: $data['tags'] ?? [],
            batchId: $data['batch_id'] ?? null,
            uuid: $data['uuid'] ?? null,
            timestamp: isset($data['timestamp'])
                ? new DateTimeImmutable($data['timestamp'])
                : null,
        );
    }

    private static function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
