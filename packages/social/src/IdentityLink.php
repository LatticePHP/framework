<?php

declare(strict_types=1);

namespace Lattice\Social;

final readonly class IdentityLink
{
    public function __construct(
        public string|int $localUserId,
        public string $providerName,
        public string $providerUserId,
        public \DateTimeImmutable $linkedAt,
    ) {}
}
