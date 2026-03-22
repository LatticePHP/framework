<?php

declare(strict_types=1);

namespace Lattice\Social;

interface IdentityLinkStoreInterface
{
    public function link(string|int $localUserId, string $provider, string $providerUserId): IdentityLink;

    public function findByProvider(string $provider, string $providerUserId): ?IdentityLink;

    /** @return array<IdentityLink> */
    public function findByLocalUser(string|int $localUserId): array;

    public function unlink(string $provider, string $providerUserId): void;
}
