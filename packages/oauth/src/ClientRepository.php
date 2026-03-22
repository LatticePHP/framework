<?php

declare(strict_types=1);

namespace Lattice\OAuth;

final class ClientRepository
{
    /** @var array<string, OAuthClient> */
    private array $clients = [];

    public function create(OAuthClient $client): void
    {
        if (isset($this->clients[$client->id])) {
            throw new \InvalidArgumentException("Client already exists: {$client->id}");
        }

        $this->clients[$client->id] = $client;
    }

    public function find(string $id): ?OAuthClient
    {
        return $this->clients[$id] ?? null;
    }

    public function findOrFail(string $id): OAuthClient
    {
        return $this->clients[$id] ?? throw new \InvalidArgumentException("Client not found: {$id}");
    }

    /**
     * @return array<OAuthClient>
     */
    public function all(): array
    {
        return array_values($this->clients);
    }

    public function update(string $id, OAuthClient $client): void
    {
        if (!isset($this->clients[$id])) {
            throw new \InvalidArgumentException("Client not found: {$id}");
        }

        $this->clients[$id] = $client;
    }

    public function delete(string $id): void
    {
        if (!isset($this->clients[$id])) {
            throw new \InvalidArgumentException("Client not found: {$id}");
        }

        unset($this->clients[$id]);
    }

    public function validateSecret(string $clientId, string $plainSecret): bool
    {
        $client = $this->find($clientId);

        if ($client === null) {
            return false;
        }

        if ($client->isPublic()) {
            return true;
        }

        return password_verify($plainSecret, $client->secretHash);
    }
}
