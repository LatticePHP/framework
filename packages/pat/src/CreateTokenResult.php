<?php

declare(strict_types=1);

namespace Lattice\Pat;

final readonly class CreateTokenResult
{
    public function __construct(
        public string $tokenId,
        public string $plainToken,
        public string $name,
        public array $scopes,
        public ?\DateTimeImmutable $expiresAt,
    ) {}
}
