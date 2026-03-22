<?php

declare(strict_types=1);

namespace Lattice\Social;

final readonly class SocialUser
{
    public function __construct(
        public string $providerId,
        public string $providerName,
        public ?string $email,
        public ?string $name,
        public ?string $avatar,
        public string $accessToken,
        public ?string $refreshToken,
        public array $raw,
    ) {}
}
