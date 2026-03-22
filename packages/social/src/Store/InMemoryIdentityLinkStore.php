<?php

declare(strict_types=1);

namespace Lattice\Social\Store;

use Lattice\Social\IdentityLink;
use Lattice\Social\IdentityLinkStoreInterface;

final class InMemoryIdentityLinkStore implements IdentityLinkStoreInterface
{
    /** @var array<string, IdentityLink> keyed by "provider:providerUserId" */
    private array $links = [];

    public function link(string|int $localUserId, string $provider, string $providerUserId): IdentityLink
    {
        $link = new IdentityLink(
            localUserId: $localUserId,
            providerName: $provider,
            providerUserId: $providerUserId,
            linkedAt: new \DateTimeImmutable(),
        );

        $this->links[$this->key($provider, $providerUserId)] = $link;

        return $link;
    }

    public function findByProvider(string $provider, string $providerUserId): ?IdentityLink
    {
        return $this->links[$this->key($provider, $providerUserId)] ?? null;
    }

    /** @return array<IdentityLink> */
    public function findByLocalUser(string|int $localUserId): array
    {
        return array_values(
            array_filter(
                $this->links,
                fn(IdentityLink $link) => $link->localUserId === $localUserId,
            ),
        );
    }

    public function unlink(string $provider, string $providerUserId): void
    {
        unset($this->links[$this->key($provider, $providerUserId)]);
    }

    private function key(string $provider, string $providerUserId): string
    {
        return $provider . ':' . $providerUserId;
    }
}
