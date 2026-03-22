<?php

declare(strict_types=1);

namespace Lattice\ApiKey;

final readonly class CreateApiKeyResult
{
    public function __construct(
        public string $keyId,
        public string $plainKey,
        public string $name,
        public array $scopes,
    ) {}
}
