<?php

declare(strict_types=1);

namespace Lattice\OAuth\Store;

use Lattice\OAuth\ClientInterface;
use Lattice\OAuth\ClientStoreInterface;

final class InMemoryClientStore implements ClientStoreInterface
{
    /** @var array<string, ClientInterface> */
    private array $clients = [];

    public function register(ClientInterface $client): void
    {
        $this->clients[$client->getId()] = $client;
    }

    public function find(string $clientId): ?ClientInterface
    {
        return $this->clients[$clientId] ?? null;
    }

    public function validateSecret(string $clientId, string $secret): bool
    {
        $client = $this->find($clientId);

        if ($client === null) {
            return false;
        }

        return hash_equals($client->getSecret(), $secret);
    }
}
