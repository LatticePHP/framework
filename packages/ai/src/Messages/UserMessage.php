<?php

declare(strict_types=1);

namespace Lattice\Ai\Messages;

final readonly class UserMessage
{
    /**
     * @param string|array<int, array<string, mixed>> $content Text content or multipart content array (for vision)
     */
    public function __construct(
        public string|array $content,
    ) {}

    public static function create(string $text): self
    {
        return new self($text);
    }

    /**
     * Create a message with text and image URLs for vision models.
     *
     * @param list<string> $imageUrls
     */
    public static function withImages(string $text, array $imageUrls): self
    {
        $parts = [
            ['type' => 'text', 'text' => $text],
        ];

        foreach ($imageUrls as $url) {
            $parts[] = ['type' => 'image_url', 'url' => $url];
        }

        return new self($parts);
    }

    public function role(): string
    {
        return 'user';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => 'user',
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
