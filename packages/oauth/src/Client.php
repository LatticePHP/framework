<?php

declare(strict_types=1);

namespace Lattice\OAuth;

final readonly class Client implements ClientInterface
{
    /**
     * @param array<string> $redirectUris
     * @param array<string> $grantTypes
     * @param array<string> $scopes
     */
    public function __construct(
        private string $id,
        private string $secret,
        private string $name,
        private array $redirectUris = [],
        private array $grantTypes = [],
        private array $scopes = [],
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return array<string> */
    public function getRedirectUris(): array
    {
        return $this->redirectUris;
    }

    /** @return array<string> */
    public function getGrantTypes(): array
    {
        return $this->grantTypes;
    }

    /** @return array<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }
}
