<?php

declare(strict_types=1);

namespace Lattice\Ai\Messages;

final readonly class SystemMessage
{
    public function __construct(
        public string $content,
    ) {}

    public static function create(string $content): self
    {
        return new self($content);
    }

    public function role(): string
    {
        return 'system';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => 'system',
            'content' => $this->content,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['content']);
    }
}
