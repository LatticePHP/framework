<?php

declare(strict_types=1);

namespace Lattice\OAuth;

final class OAuthClient
{
    /**
     * @param array<string> $redirectUris
     * @param array<string> $scopes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $secretHash,
        public readonly string $name,
        public readonly array $redirectUris = [],
        public readonly array $scopes = [],
        public readonly string $type = 'confidential',
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        public readonly ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function isConfidential(): bool
    {
        return $this->type === 'confidential';
    }

    public function isPublic(): bool
    {
        return $this->type === 'public';
    }

    public function hasRedirectUri(string $uri): bool
    {
        return in_array($uri, $this->redirectUris, true);
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
