<?php

declare(strict_types=1);

namespace App\Dto;

final class UserResource
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
    ) {}

    /**
     * @param array{id: string, name: string, email: string} $data
     * @return array<string, mixed>
     */
    public static function fromArray(array $data): array
    {
        return [
            'data' => [
                'id' => $data['id'],
                'name' => $data['name'],
                'email' => $data['email'],
            ],
        ];
    }
}
