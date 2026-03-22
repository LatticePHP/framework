<?php

declare(strict_types=1);

namespace Lattice\OAuth;

interface ClientStoreInterface
{
    public function find(string $clientId): ?ClientInterface;

    public function validateSecret(string $clientId, string $secret): bool;
}
